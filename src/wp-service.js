/**
 * wp-service.js
 * Replaces email-service.js — posts quiz submissions to the
 * WordPress REST API endpoint instead of EmailJS.
 *
 * Drop this file into src/ and update the import in main.js:
 *   import { sendSubmitEmails, sendCtaEmail } from './wp-service.js';
 *
 * Set your WordPress site URL and reCAPTCHA key in .env:
 *   VITE_WP_URL             = https://yourwordpresssite.com
 *   VITE_RECAPTCHA_SITE_KEY = your-recaptcha-v3-site-key
 */

import { SCORING_RULES, TIERS } from './data.js';
import { generateResultsPDFBase64 } from './pdf-builder.js';

// ── Config ────────────────────────────────────────────────────────────────────

const WP_ENDPOINT   = `${import.meta.env.VITE_WP_URL ?? ''}/wp-json/outsourcing-scorecard/v1/submit`;
const RECAPTCHA_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY ?? '';

// ── reCAPTCHA v3 ──────────────────────────────────────────────────────────────

function getRecaptchaToken(action = 'quiz_submit') {
    return new Promise((resolve) => {
        if (!RECAPTCHA_KEY) {
            console.warn('[wp-service] VITE_RECAPTCHA_SITE_KEY not set — skipping reCAPTCHA.');
            resolve(null);
            return;
        }

        const execute = () => {
            window.grecaptcha.ready(() => {
                window.grecaptcha
                    .execute(RECAPTCHA_KEY, { action })
                    .then(resolve)
                    .catch(() => resolve(null));
            });
        };

        if (window.grecaptcha) {
            execute();
        } else {
            const script   = document.createElement('script');
            script.src     = `https://www.google.com/recaptcha/api.js?render=${RECAPTCHA_KEY}`;
            script.onload  = execute;
            script.onerror = () => resolve(null);
            document.head.appendChild(script);
        }
    });
}

// ── Score + answers helpers ───────────────────────────────────────────────────

function calcScore(formData) {
    let score = 0;
    SCORING_RULES.forEach(rule => {
        const r = rule.cases[formData.get(rule.field)] ?? rule.cases['_'];
        if (r) score += r.pts;
    });
    return score;
}

/**
 * Extract all quiz answers from formData as a plain key-value object.
 * Checkbox groups are joined into comma-separated strings.
 */
function extractAnswers(formData) {
    const answers = {};
    for (const [key, value] of formData.entries()) {
        if (['fullname', 'email', 'phone', 'company'].includes(key)) continue;
        const k = key.replace(/\[\]$/, '');     // strip [] suffix from checkbox names
        if (answers[k]) {
            answers[k] += `, ${value}`;          // join multiple checkbox values
        } else {
            answers[k] = value;
        }
    }
    return answers;
}

// ── Core POST helper ──────────────────────────────────────────────────────────

async function postToWordPress(payload) {
    const token = await getRecaptchaToken();

    const response = await fetch(WP_ENDPOINT, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ ...payload, recaptcha_token: token }),
    });

    if (!response.ok) {
        const err = await response.json().catch(() => ({}));
        throw new Error(err.message ?? `HTTP ${response.status}`);
    }

    return response.json();
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Called on form submit.
 * Sends full tier data (title, body, goalLine) + insights so the PHP
 * endpoint can save everything correctly to Flamingo.
 *
 * @param {FormData}    formData
 * @param {object}      tier        — matched TIERS entry { title, body, goalLine, ... }
 * @param {string[]}    insights    — array of insight messages
 * @param {object|null} itiInstance — intl-tel-input instance
 */
export function sendSubmitEmails(formData, tier, insights, itiInstance) {
    const fullPhone = itiInstance
        ? itiInstance.getNumber()       // E.164 format e.g. +639171234567
        : (formData.get('phone') ?? '');

    // Generate personalised Results PDF and send as base64 to PHP for email attachment
    const pdfBase64 = generateResultsPDFBase64(formData, tier.title, tier.body, tier.goalLine, insights, itiInstance);
    // Build the submitter's full name for the PDF filename
    const fullname = formData.get('fullname') ?? '';
    const pdfFilename = fullname
        ? `Magellan-Readiness-Results-${fullname.replace(/\s+/g, '-')}.pdf`
        : 'Magellan-Outsourcing-Readiness-Results.pdf';

    // Derive CTA labels for this tier (exclude 'download' — frontend only)
    const tierCtas = (tier.ctas ?? [])
        .filter(c => c.action !== 'download')
        .map(c => ({ label: c.label, action: c.action }));

    const payload = {
        fullname:     fullname,
        email:        formData.get('email')    ?? '',
        phone:        fullPhone,
        company:      formData.get('company')  ?? '',
        tier:         tier.title,
        tier_body:    tier.body,
        goal_line:    tier.goalLine,
        goal_answer:  formData.get('q14') ?? '', // raw value; PHP maps to label for user email
        score:        calcScore(formData),
        answers:      extractAnswers(formData),
        insights:     insights,
        ctas:         tierCtas,          // CTA buttons for user email
        pdf_base64:   pdfBase64,         // Results PDF as base64 for email attachment
        pdf_filename: pdfFilename,       // PDF filename for attachment
    };

    postToWordPress(payload)
        .then(res => console.log('[wp-service] Submission accepted:', res))
        .catch(err => console.error('[wp-service] Submission failed:', err));
}

/**
 * Called when a CTA button (Schedule / Consult) is clicked.
 * Sends contact details + which CTA was clicked. No quiz answers.
 *
 * @param {string}      action      — 'schedule' | 'consult'
 * @param {FormData}    formData
 * @param {object}      tier
 * @param {object|null} itiInstance
 * @returns {Promise}
 */
export function sendCtaEmail(action, formData, tier, itiInstance) {
    const fullPhone = itiInstance
        ? itiInstance.getNumber()
        : (formData.get('phone') ?? '');

    // CTA emails send contact details only (q16 group fields) + which CTA was clicked
    // Subject is determined by action type in the PHP endpoint via cta_action field
    const payload = {
        fullname:   formData.get('fullname') ?? '',
        email:      formData.get('email')    ?? '',
        phone:      fullPhone,
        company:    formData.get('company')  ?? '',
        tier:       tier.title,
        tier_body:  '',         // not needed for CTA emails
        goal_line:  '',         // not needed for CTA emails
        score:      calcScore(formData),
        answers:    { cta_action: action },
        insights:   [],
        is_cta:     true,       // flag so PHP sends admin-only contact email
    };

    return postToWordPress(payload);
}