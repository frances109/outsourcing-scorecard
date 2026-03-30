/**
 * main.js
 * Entry point for the Outsourcing Readiness Quiz.
 * Imports all dependencies via npm — no CDN scripts needed in HTML.
 */

import $ from 'jquery';
import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/build/css/intlTelInput.css';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '../style.css';

import { CONFIG, SCORING_RULES, TIERS } from './data.js';
import { sendSubmitEmails, sendCtaEmail } from './wp-service.js';

// ── Hosted Readiness Guide PDF download ───────────────────────────────────────
// Place your PDF at: public/assets/readiness-guide.pdf
// Vite copies public/ contents to dist/ root, so it will be served at:
//   https://yoursite.com/scorecard/assets/readiness-guide.pdf
// To swap the PDF: just replace the file in public/assets/ and rebuild.

function downloadReadinessGuide() {
    // import.meta.env.BASE_URL is the vite base path (e.g. '/scorecard/')
    // The PDF lives in public/assets/ which Vite copies to dist/assets/
    const pdfUrl = `${import.meta.env.BASE_URL}assets/readiness-guide.pdf`;
    const $btn    = $('[data-action="download"]');
    const origTxt = $btn.text();

    $btn.prop('disabled', true).text('Downloading…');

    fetch(pdfUrl)
        .then(res => {
            if (!res.ok) throw new Error(`PDF not found (${res.status})`);
            return res.blob();
        })
        .then(blob => {
            const url  = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href     = url;
            link.download = 'Outsourcing-Readiness-Guide.pdf';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
            $btn.prop('disabled', false).text(origTxt);
        })
        .catch(() => {
            $btn.prop('disabled', false).text(origTxt);
            alert('Could not download the guide. Please try again later.');
        });
}


/* ================================================================
   VALIDATION HELPERS
   ================================================================ */

const BLOCKED_EMAIL_DOMAINS = [
    'example.com', 'test.com', 'mailinator.com', 'guerrillamail.com',
    'tempmail.com', 'throwaway.email', 'yopmail.com', 'sharklasers.com',
    'guerrillamailblock.com', 'grr.la', 'guerrillamail.info', 'spam4.me',
    'trashmail.com', 'dispostable.com', 'fakeinbox.com', 'maildrop.cc',
    'discard.email', 'spamgourmet.com', 'mailnull.com', 'spamcorpse.com',
    'example.org', 'example.net', 'invalid.com',
];

function isTestEmail(email) {
    const lower  = email.toLowerCase().trim();
    const atIdx  = lower.indexOf('@');
    if (atIdx < 0) return true;
    const prefix = lower.slice(0, atIdx);
    const domain = lower.slice(atIdx + 1);
    if (BLOCKED_EMAIL_DOMAINS.includes(domain)) return true;
    if (/^tests?(?:\d+)?$/.test(prefix)) return true;
    if (/^(dummy|fake|sample|noreply|no-reply|admin)$/.test(prefix)) return true;
    return false;
}

function isValidEmailFormat(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.trim());
}

// intl-tel-input instance — set during init, shared with email/pdf builders
let itiInstance = null;


/* ================================================================
   FORM BUILDER
   ================================================================ */

function buildSelect(q) {
    const $sel = $('<select>').attr({ name: q.id, id: q.id }).addClass('form-select');
    if (q.required) $sel.attr('required', true);
    $sel.append(
        $('<option>').val('').attr({ disabled: true, selected: true, hidden: true })
            .text('-- Please choose an option --')
    );
    q.options.forEach(o => $sel.append($('<option>').val(o.value).text(o.label)));
    return $sel;
}

function buildCheckboxGroup(q) {
    const $wrap = $('<div>').addClass('checkbox-card-grid');

    q.options.forEach(o => {
        const uid = `${q.id}-${o.value}`;
        const $input = $('<input>')
            .attr({ type: 'checkbox', id: uid, name: `${q.id}[]`, value: o.value })
            .addClass('checkbox-card-input q4check');
        const $label = $('<label>')
            .attr('for', uid)
            .addClass('checkbox-card-label')
            .append(
                $('<span>').addClass('checkbox-card-tick'),
                $('<span>').addClass('checkbox-card-text').text(o.label)
            );
        $wrap.append($('<div>').addClass('checkbox-card-item').append($input, $label));
    });

    $wrap.append($('<div>').attr('id', 'q4error').addClass('invalid-feedback d-none').text(q.error));
    return $wrap;
}

function buildContactGroup(q) {
    const $wrap = $('<div>').addClass('row g-2');
    q.fields.forEach(f => {
        const $col = $('<div>').addClass('col-12 col-sm-6');
        if (f.sublabel) {
            $col.append($('<label>').attr('for', f.id).addClass('form-label').text(f.sublabel));
        }
        const $input = $('<input>')
            .attr({ type: f.type, id: f.id, name: f.name, placeholder: f.placeholder })
            .addClass('form-control');
        if (f.required) $input.attr('required', true);
        $col.append($input);
        if (f.error) $col.append($('<div>').addClass('invalid-feedback').text(f.error));
        $wrap.append($col);
    });
    return $wrap;
}

function buildQuestion(q) {
    const $div = $('<div>').addClass('question mb-3');
    $div.append($('<label>').attr('for', q.id).addClass('form-label').text(q.label));
    if (q.type === 'select') {
        $div.append(buildSelect(q));
        $div.append($('<div>').addClass('invalid-feedback').text(q.error));
    } else if (q.type === 'checkbox') {
        $div.append(buildCheckboxGroup(q));
    } else if (q.type === 'contact') {
        $div.append(buildContactGroup(q));
    }
    return $div;
}

function buildForm() {
    const $container = $('#clusterContainer');
    const total = CONFIG.clusters.length;

    CONFIG.clusters.forEach((cluster, idx) => {
        const $cluster = $('<div>').addClass('cluster');
        const stepNum  = idx + 1;

        // Step progress bar
        const $progressWrap = $('<div>').addClass('step-progress-wrap');
        $progressWrap.append(
            $('<div>').addClass('step-progress-info').append(
                $('<span>').addClass('step-progress-label').text(`STEP ${stepNum} OF ${total}`),
                $('<span>').addClass('step-progress-title').text(cluster.title.toUpperCase())
            ),
            $('<div>').addClass('step-progress-bar').append(
                $('<div>').addClass('step-progress-fill').css('width', `${(stepNum / total) * 100}%`)
            )
        );
        $cluster.append($progressWrap);

        // Step label + section title
        $cluster.append($('<div>').addClass('cluster-step-label').text(`STEP ${stepNum}`));
        $cluster.append($('<h3>').addClass('cluster-title').text(cluster.title));

        // Questions
        const hasMany     = cluster.questions.length >= 3;
        const hasCheckbox = cluster.questions.some(q => q.type === 'checkbox');
        const useGrid     = hasMany || hasCheckbox;
        const $qWrap      = useGrid ? $('<div>').addClass('question-grid') : $('<div>');

        cluster.questions.forEach(q => {
            const $q = buildQuestion(q);
            if (useGrid && (q.type === 'contact' || q.type === 'checkbox')) {
                $q.addClass('question-full');
            }
            $qWrap.append($q);
        });
        $cluster.append($qWrap);
        $container.append($cluster);
    });
}


/* ================================================================
   NAVIGATION
   ================================================================ */

function updateNav(step, $clusters) {
    const isFirst = step === 0;
    const isLast  = step === $clusters.length - 1;

    $('#prevBtn').toggleClass('d-none', isFirst);
    $('#nextBtn').toggleClass('d-none', isLast);

    $('#prevBtnMobile').css('visibility', isFirst ? 'hidden' : 'visible');
    $('#nextBtnMobile').css('visibility', isLast  ? 'hidden' : 'visible');

    $('#submitBtn').toggleClass('d-none', !isLast);
}

function goNext(state) {
    const { $clusters } = state;
    if (!validateCluster($clusters.eq(state.step))) return;
    $clusters.eq(state.step).hide();
    state.step++;
    $clusters.eq(state.step).show();
    updateNav(state.step, $clusters);
}

function goPrev(state) {
    const { $clusters } = state;
    $clusters.eq(state.step).hide();
    state.step--;
    $clusters.eq(state.step).show();
    updateNav(state.step, $clusters);
}


/* ================================================================
   VALIDATION
   ================================================================ */

function validateCluster($cluster) {
    let valid = true;

    // Standard required fields
    $cluster.find('[required]').each(function () {
        const ok = !!$(this).val();
        $(this).toggleClass('is-invalid', !ok);
        if (!ok) valid = false;
    });

    // Email — format + test-email block
    const $email = $cluster.find('#email');
    if ($email.length && $email.val()) {
        const val = $email.val().trim();
        const $fb = $email.next('.invalid-feedback');
        if (!isValidEmailFormat(val)) {
            $email.addClass('is-invalid');
            $fb.text('Please enter a valid email address.');
            valid = false;
        } else if (isTestEmail(val)) {
            $email.addClass('is-invalid');
            $fb.text('Please use a real business email address.');
            valid = false;
        } else {
            $email.removeClass('is-invalid');
        }
    }

    // Phone — intl-tel-input
    const $phone = $cluster.find('#phone');
    if ($phone.length && itiInstance && $phone.val().trim()) {
        if (!itiInstance.isValidNumber()) {
            $phone.addClass('is-invalid');
            $phone.closest('.col-12').find('.invalid-feedback')
                .text('Please enter a valid phone number for the selected country.');
            valid = false;
        } else {
            $phone.removeClass('is-invalid');
        }
    }

    // Checkbox card group
    const $checks = $cluster.find('.q4check');
    if ($checks.length) {
        const checked = $checks.is(':checked');
        $('#q4error').toggleClass('d-none', checked);
        $checks.each(function () { $(this).toggleClass('is-invalid', !checked); });
        if (!checked) valid = false;
    }

    return valid;
}


/* ================================================================
   POPUP / RESULTS
   ================================================================ */

let _lastFormData = null;
let _lastTier     = null;
let _lastInsights = null;

function showCtaFeedback(action, success) {
    const id = `cta-msg-${action}`;
    if ($(`#${id}`).length) return;
    const $msg = $('<p>')
        .attr('id', id)
        .addClass(success ? 'cta-feedback cta-feedback--ok' : 'cta-feedback cta-feedback--err')
        .text(success
            ? '✓ Your request has been sent. We\'ll be in touch shortly.'
            : '⚠ Something went wrong. Please try again or contact us directly.')
        .appendTo('#popupContent');

    // Auto-remove after 4 seconds with a fade-out
    setTimeout(() => {
        $msg.fadeOut(400, function () { $(this).remove(); });
    }, 4000);
}

function handleCtaClick(action) {
    if (!_lastFormData || !_lastTier) return;

    if (action === 'schedule' || action === 'consult') {
        const $btn = $(`[data-action='${action}']`);
        $btn.prop('disabled', true).text('Sending…');
        sendCtaEmail(action, _lastFormData, _lastTier, itiInstance)
            .then(() => {
                $btn.text('Sent!');
                showCtaFeedback(action, true);
            })
            .catch(() => {
                $btn.prop('disabled', false).text(action === 'schedule' ? 'Schedule Your Strategy Call' : 'Book a Consultation');
                showCtaFeedback(action, false);
            });

    } else if (action === 'download') {
        // Download the hosted Readiness Guide PDF from public/assets/readiness-guide.pdf
        downloadReadinessGuide();
    }
}

function buildPopup(formData) {
    let score    = 0;
    const insights = [];

    SCORING_RULES.forEach(rule => {
        const r = rule.cases[formData.get(rule.field)] ?? rule.cases['_'];
        score += r.pts;
        insights.push(r.msg);
    });

    const tier      = TIERS.find(t => score >= t.min);
    const goal      = formData.get('q14');
    const authority = formData.get('q15');
    const $c        = $('#popupContent').empty();

    _lastFormData = formData;
    _lastTier     = tier;
    _lastInsights = insights;

    $('<h2>').text(tier.title).appendTo($c);
    $('<p>').text(insights.join(' ')).appendTo($c);

    $('<p>').append(
        $('<strong>').text('Recommendation: ').append($('<br>')),
        document.createTextNode(tier.body)
    ).appendTo($c);

    $('<p>').append(
        document.createTextNode('Since your primary goal is '),
        $('<strong>').text(goal),
        document.createTextNode(`, ${tier.goalLine}`)
    ).appendTo($c);

    if (authority !== 'yes') {
        $('<p>').append(
            $('<strong>').text('Note: ').append($('<br>')),
            document.createTextNode('You may need buy-in from other decision-makers before proceeding.')
        ).appendTo($c);
    }

    const $btnRow = $('<div>').addClass('cta-btn-row').appendTo($c);
    tier.ctas.forEach(cta => {
        $('<button>')
            .addClass('btn btn-primary me-2 mb-2')
            .attr('data-action', cta.action)
            .text(cta.label)
            .on('click', () => handleCtaClick(cta.action))
            .appendTo($btnRow);
    });

    // NOTE: Emails (admin + user) are triggered by the form submit handler — NOT here.
    // The popup only handles CTA button interactions.
}


/* ================================================================
   INIT
   ================================================================ */

$(document).ready(function () {

    buildForm();

    const state = {
        step:      0,
        $clusters: $('.cluster'),
    };

    state.$clusters.hide();
    state.$clusters.eq(0).show();
    updateNav(state.step, state.$clusters);

    // intl-tel-input
    itiInstance = intlTelInput(document.getElementById('phone'), {
        initialCountry:     'auto',
        separateDialCode:   true,
        preferredCountries: ['us', 'ph', 'au', 'gb'],
        geoIpLookup: cb => {
            $.getJSON('https://ipapi.co/json')
                .done(d  => cb(d.country_code))
                .fail(() => cb('us'));
        },
        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@21.1.4/build/js/utils.js',
    });

    // Landing ↔ quiz transitions
    $('#start-btn').on('click', () => {
        $('.landing-grid').fadeOut(250, () => {
            $('#quizWrapper').removeClass('d-none').hide().fadeIn(300);
        });
    });

    $('#back-btn').on('click', () => {
        $('#quizWrapper').addClass('d-none');
        $('.landing-grid').fadeIn(300);
    });

    $('#prevBtn, #prevBtnMobile').on('click', () => goPrev(state));
    $('#nextBtn, #nextBtnMobile').on('click', () => goNext(state));

    $('#quizForm')
        .on('change input', '[required]', function () {
            if ($(this).val()) $(this).removeClass('is-invalid');
        })
        .on('change', '.q4check', function () {
            if ($('.q4check').is(':checked')) {
                $('#q4error').addClass('d-none');
                $('.q4check').removeClass('is-invalid');
            }
        })
        .on('blur', '#email', function () {
            const val = $(this).val().trim();
            if (!val) return;
            const $fb = $(this).next('.invalid-feedback');
            if (!isValidEmailFormat(val)) {
                $(this).addClass('is-invalid');
                $fb.text('Please enter a valid email address.');
            } else if (isTestEmail(val)) {
                $(this).addClass('is-invalid');
                $fb.text('Please use a real business email address.');
            } else {
                $(this).removeClass('is-invalid');
                $fb.text('');
            }
        })
        .on('blur', '#phone', function () {
            if (!itiInstance || !$(this).val().trim()) return;
            if (!itiInstance.isValidNumber()) {
                $(this).addClass('is-invalid');
                $(this).closest('.col-12').find('.invalid-feedback')
                    .text('Please enter a valid phone number for the selected country.');
            } else {
                $(this).removeClass('is-invalid');
            }
        })
        .on('submit', function (e) {
            e.preventDefault();
            if (!validateCluster(state.$clusters.eq(state.step))) return;
            const formData = new FormData(this);
            buildPopup(formData);
            // Trigger both admin + user emails on Check Readiness submit (once, here only)
            sendSubmitEmails(formData, _lastTier, _lastInsights, itiInstance);
            $('#overlay, #popup').removeClass('d-none');
            $('#popup').addClass('d-flex flex-column');
            $('#submitBtn').prop('disabled', true);
        });

    $('#closePopup').on('click', () => {
        $('#overlay, #popup').addClass('d-none');
        setTimeout(() => window.location.href = import.meta.env.VITE_WP_URL, 3000); // outsourcing-technical-guides
    });
});
