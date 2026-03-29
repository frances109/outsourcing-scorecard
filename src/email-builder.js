/**
 * email-builder.js
 * Generates HTML email bodies for admin, user, and CTA emails.
 * Pure functions — no side effects, no DOM access.
 *
 * Outlook compatibility rules:
 *  - No rgba() — converted to solid hex equivalents on the dark background
 *  - No CSS gradients — VML fallback used for header background
 *  - No display:block on <span> — use <table> rows for label+value pairs
 *  - No border-radius support — Outlook ignores gracefully (rounded on Gmail/Apple)
 *  - No CSS shorthand padding/border — expanded to individual properties
 *  - Inline styles only — Gmail strips <style> blocks
 *  - MSO conditional comments for Outlook column width fixes
 *  - font-family always ends with Arial,sans-serif
 */

import { CONFIG } from './data.js';

// ── Solid-hex color palette (no rgba) ────────────────────────────────────────
const C = {
    pageBg:       '#f4f6fb',
    card:         '#0f1f3d',
    headerBg:     '#1a3260',   // solid fallback for gradient
    accent:       '#54c8ef',
    rowBg:        '#162848',   // rgba(255,255,255,0.05) on #0f1f3d
    rowBorder:    '#1e3558',   // rgba(255,255,255,0.07) on #0f1f3d
    accentBg:     '#132030',   // rgba(84,200,239,0.08) on #0f1f3d
    accentBorder: '#1e4060',   // rgba(84,200,239,0.20) on #0f1f3d
    footerBg:     '#111d35',   // rgba(255,255,255,0.03) on #0f1f3d
    footerBorder: '#1a2e4a',   // rgba(255,255,255,0.08) on #0f1f3d
    white:        '#ffffff',
    textBody:     '#d9e8f5',   // rgba(255,255,255,0.85)
    textMuted:    '#7aadcc',   // rgba(255,255,255,0.45)
    textSub:      '#5a7a99',   // rgba(255,255,255,0.35)
    font:         "Arial,'Helvetica Neue',Helvetica,sans-serif",
};

// ── Shell ─────────────────────────────────────────────────────────────────────
function ebShell(rows) {
    return `<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <!--[if mso]>
  <xml>
    <o:OfficeDocumentSettings>
      <o:AllowPNG/>
      <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
  </xml>
  <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:${C.pageBg};
             font-family:${C.font};-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:${C.pageBg};">
  <tr>
    <td align="center" style="padding-top:40px;padding-bottom:40px;
                               padding-left:16px;padding-right:16px;">
      <!--[if mso]>
      <table role="presentation" width="620" cellpadding="0" cellspacing="0" border="0">
      <tr><td>
      <![endif]-->
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="max-width:620px;width:100%;background-color:${C.card};
                    border-radius:16px;overflow:hidden;">
        ${rows}
      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body></html>`;
}

// ── Header — VML gradient for Outlook, CSS gradient for others ───────────────
function ebHeader(title, sub) {
    return `
<tr>
  <td style="padding:0;border-bottom-width:3px;border-bottom-style:solid;
             border-bottom-color:${C.accent};border-radius:16px 16px 0 0;">
    <!--[if mso]>
    <v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false"
            style="width:620px;height:110px;">
      <v:fill type="gradient" color="${C.card}" color2="${C.headerBg}"
              angle="135" focus="100%"/>
      <v:textbox inset="0,0,0,0">
    <![endif]-->
    <div style="background:linear-gradient(135deg,${C.card} 0%,${C.headerBg} 100%);
                padding-top:40px;padding-bottom:30px;
                padding-left:40px;padding-right:40px;">
      <h1 style="margin:0;margin-bottom:6px;font-size:22px;font-weight:800;
                 color:${C.white};font-family:${C.font};letter-spacing:-0.03em;
                 line-height:1.2;">${title}</h1>
      <p style="margin:0;font-size:13px;color:${C.textMuted};font-family:${C.font};
                letter-spacing:0.05em;text-transform:uppercase;">${sub}</p>
    </div>
    <!--[if mso]>
      </v:textbox>
    </v:rect>
    <![endif]-->
  </td>
</tr>`;
}

// ── Section wrapper ───────────────────────────────────────────────────────────
function ebSection(content, pt = 24) {
    return `<tr>
  <td style="padding-top:${pt}px;padding-left:40px;padding-right:40px;padding-bottom:0;">
    ${content}
  </td>
</tr>`;
}

// ── Section label ─────────────────────────────────────────────────────────────
function ebLabel(text) {
    return `<p style="margin:0;margin-bottom:12px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:${C.accent};
                      font-family:${C.font};">${text}</p>`;
}

// ── Sub-label ─────────────────────────────────────────────────────────────────
function ebSubLabel(text) {
    return `<p style="margin:0;margin-bottom:8px;font-size:11px;font-weight:600;
                      color:${C.textSub};letter-spacing:0.08em;text-transform:uppercase;
                      font-family:${C.font};">${text}</p>`;
}

// ── Data table — each field is a proper 2-row cell (label row + value row) ───
// Using separate <tr> for label and value fixes Outlook's display:block issue.
function ebDataTable(rows, mb = 0) {
    const trs = rows.map((r, i) => {
        const isLast   = i === rows.length - 1;
        const border   = isLast ? '' :
            `border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:${C.rowBorder};`;
        const color    = r.color ?? C.white;
        const bold     = r.bold  ? 'font-weight:700;' : '';
        return `<tr>
          <td style="padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;">
            <p style="margin:0;font-size:11px;color:${C.textMuted};
                      font-family:${C.font};">${r.label}</p>
          </td>
        </tr>
        <tr>
          <td style="padding-top:0;padding-left:18px;padding-right:18px;
                     padding-bottom:12px;${border}">
            <p style="margin:0;font-size:14px;color:${color};${bold}
                      font-family:${C.font};">${r.value}</p>
          </td>
        </tr>`;
    }).join('');

    return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
          style="background-color:${C.rowBg};border-radius:10px;
                 margin-bottom:${mb}px;">
      ${trs}
    </table>`;
}

// ── Accent box (Assessment Result badge) ──────────────────────────────────────
function ebAccentBox(labelText, valueText) {
    return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:${C.accentBg};border-width:1px;border-style:solid;
              border-color:${C.accentBorder};border-radius:10px;">
  <tr>
    <td style="padding-top:16px;padding-bottom:16px;
               padding-left:20px;padding-right:20px;">
      <p style="margin:0;margin-bottom:6px;font-size:10px;font-weight:700;
                letter-spacing:0.12em;text-transform:uppercase;
                color:${C.accent};font-family:${C.font};">${labelText}</p>
      <p style="margin:0;font-size:16px;font-weight:700;color:${C.white};
                font-family:${C.font};">${valueText}</p>
    </td>
  </tr>
</table>`;
}

// ── Result badge with score on the right ──────────────────────────────────────
function ebResultBadge(tier, score) {
    return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:${C.accentBg};border-width:1px;border-style:solid;
              border-color:${C.accentBorder};border-radius:10px;">
  <tr>
    <td style="padding-top:16px;padding-bottom:16px;
               padding-left:20px;padding-right:20px;">
      <p style="margin:0;margin-bottom:6px;font-size:10px;font-weight:700;
                letter-spacing:0.12em;text-transform:uppercase;
                color:${C.accent};font-family:${C.font};">Assessment Result</p>
      <p style="margin:0;font-size:16px;font-weight:700;color:${C.white};
                font-family:${C.font};">${tier}</p>
    </td>
    <td align="right" valign="middle"
        style="padding-top:16px;padding-bottom:16px;
               padding-left:20px;padding-right:20px;white-space:nowrap;">
      <p style="margin:0;margin-bottom:4px;font-size:10px;font-weight:700;
                letter-spacing:0.12em;text-transform:uppercase;text-align:right;
                color:${C.accent};font-family:${C.font};">Score</p>
      <p style="margin:0;font-size:28px;font-weight:800;color:${C.white};
                text-align:right;font-family:${C.font};">${score}</p>
    </td>
  </tr>
</table>`;
}

// ── Intro highlight box ───────────────────────────────────────────────────────
function ebIntroBox(html) {
    return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:${C.accentBg};border-width:1px;border-style:solid;
              border-color:${C.accentBorder};border-radius:10px;">
  <tr>
    <td style="padding-top:20px;padding-bottom:20px;
               padding-left:24px;padding-right:24px;">
      <p style="margin:0;font-size:14px;color:${C.textBody};line-height:1.75;
                font-family:${C.font};">${html}</p>
    </td>
  </tr>
</table>`;
}

// ── Content card (result / insights / goal) ───────────────────────────────────
function ebCard(content) {
    return `<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:${C.rowBg};border-radius:10px;">
  <tr>
    <td style="padding-top:20px;padding-bottom:20px;
               padding-left:24px;padding-right:24px;">
      ${content}
    </td>
  </tr>
</table>`;
}

// ── Footer note ───────────────────────────────────────────────────────────────
function ebFooter(html) {
    return `<tr>
  <td style="padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:40px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:${C.footerBg};border-width:1px;border-style:solid;
                  border-color:${C.footerBorder};border-radius:10px;">
      <tr>
        <td style="padding-top:16px;padding-bottom:16px;
                   padding-left:20px;padding-right:20px;">
          <p style="margin:0;font-size:12px;color:${C.textSub};text-align:center;
                    line-height:1.6;font-family:${C.font};">${html}</p>
        </td>
      </tr>
    </table>
  </td>
</tr>`;
}

// ── Label resolvers ───────────────────────────────────────────────────────────
function resolveLabel(questionId, rawValue) {
    let label = rawValue || '(no answer)';
    for (const cluster of CONFIG.clusters) {
        for (const q of cluster.questions) {
            if (q.id === questionId && q.options) {
                const opt = q.options.find(o => o.value === rawValue);
                if (opt) label = opt.label;
            }
        }
    }
    return label;
}

function resolveCheckboxLabels(questionId, formData) {
    const vals = formData.getAll(`${questionId}[]`);
    if (!vals.length) return '(none selected)';
    return vals.map(v => resolveLabel(questionId, v)).join(', ');
}

// ── Row builders ──────────────────────────────────────────────────────────────
function contactRows(formData, itiInstance) {
    const phone = itiInstance?.getNumber() ?? formData.get('phone') ?? '';
    return [
        { label: 'Full Name', value: formData.get('fullname') ?? '', bold: true },
        { label: 'Email',     value: formData.get('email')    ?? '', color: C.accent },
        { label: 'Phone',     value: phone },
        { label: 'Company',   value: formData.get('company')  ?? '', bold: true },
    ];
}

function companyProfileRows(formData) {
    return [
        { label: 'Role',         value: resolveLabel('q1', formData.get('q1')) },
        { label: 'Company Size', value: resolveLabel('q2', formData.get('q2')) },
        { label: 'Industry',     value: resolveLabel('q3', formData.get('q3')) },
    ];
}

function operationalRows(formData) {
    return [
        { label: 'Time-consuming Areas', value: resolveCheckboxLabels('q4', formData) },
        { label: 'Biggest Frustration',  value: resolveLabel('q5', formData.get('q5')) },
        { label: 'Challenge Severity',   value: resolveLabel('q6', formData.get('q6')) },
    ];
}

function processRows(formData) {
    return [
        { label: 'Documented Processes', value: resolveLabel('q7', formData.get('q7')) },
        { label: 'Collaboration Tools',  value: resolveLabel('q8', formData.get('q8')) },
    ];
}

function outsourcingRows(formData) {
    return [
        { label: 'Prior Outsourcing', value: resolveLabel('q9',  formData.get('q9'))  },
        { label: 'Main Concern',      value: resolveLabel('q10', formData.get('q10')) },
    ];
}

function decisionRows(formData) {
    return [
        { label: 'Comfort with Change', value: resolveLabel('q11', formData.get('q11')) },
        { label: 'Budget Allocated',    value: resolveLabel('q12', formData.get('q12')) },
        { label: 'Timeline',            value: resolveLabel('q13', formData.get('q13')) },
        { label: 'Primary Goal',        value: resolveLabel('q14', formData.get('q14')) },
        { label: 'Decision Maker',      value: resolveLabel('q15', formData.get('q15')) },
    ];
}

function allAnswerSections(formData) {
    return [
        ebSection(ebLabel('Company Profile')        + ebDataTable(companyProfileRows(formData))),
        ebSection(ebLabel('Operational Challenges') + ebDataTable(operationalRows(formData))),
        ebSection(ebLabel('Process &amp; Systems')  + ebDataTable(processRows(formData))),
        ebSection(ebLabel('Outsourcing Experience') + ebDataTable(outsourcingRows(formData))),
        ebSection(ebLabel('Decision Readiness')     + ebDataTable(decisionRows(formData))),
    ].join('');
}

// ── Public exports ────────────────────────────────────────────────────────────

/**
 * Admin email — all answers + result tier + score badge.
 * @param {number} [score=0] pre-calculated score
 */
export function buildAdminEmail(formData, tierTitle, itiInstance, score = 0) {
    const fullname = formData.get('fullname') ?? '';
    const email    = formData.get('email')    ?? '';

    return ebShell([
        ebHeader('New Outsourcing Assessment',
                 `${fullname} — ${formData.get('company') ?? ''}`),

        ebSection(ebLabel('Contact Information') +
                  ebDataTable(contactRows(formData, itiInstance)), 28),

        ebSection(ebResultBadge(tierTitle, score), 20),

        allAnswerSections(formData),

        ebFooter(`Submitted via the Outsourcing Readiness Assessment &mdash;
                  Reply to respond to
                  <strong style="color:${C.textBody};">${fullname}</strong>
                  at <strong style="color:${C.accent};">${email}</strong>.`),
    ].join(''));
}

/**
 * User results email — result + insights + goal + submission summary.
 */
export function buildUserEmail(formData, tierTitle, tierBody, goalLine, insights, itiInstance) {
    const fullname   = formData.get('fullname') ?? '';
    const goalAnswer = resolveLabel('q14', formData.get('q14'));

    const insightItems = insights.map(msg =>
        `<p style="margin:0;margin-bottom:8px;font-size:13px;color:${C.textBody};
                   line-height:1.65;font-family:${C.font};">&#8226; ${msg}</p>`
    ).join('');

    return ebShell([
        ebHeader(`Thank You, ${fullname}!`, 'Your Assessment Has Been Received'),

        ebSection(ebIntroBox(
            `Thank you for completing the ` +
            `<strong style="color:${C.accent};">Outsourcing Readiness Assessment</strong>. ` +
            `Our team will review your responses and reach out shortly.`
        ), 30),

        ebSection(
            ebLabel('Your Result') +
            ebCard(`<p style="margin:0;margin-bottom:10px;font-size:18px;font-weight:800;
                               color:${C.white};letter-spacing:-0.02em;
                               font-family:${C.font};">${tierTitle}</p>
                    <p style="margin:0;font-size:13px;color:${C.textBody};line-height:1.7;
                               font-family:${C.font};">${tierBody}</p>`)
        ),

        ebSection(
            ebLabel('Your Key Insights') +
            ebCard(insightItems)
        ),

        ebSection(
            ebLabel('Your Goal') +
            ebCard(`<p style="margin:0;font-size:13px;color:${C.textBody};line-height:1.7;
                               font-family:${C.font};">
                      Since your primary goal is
                      <strong style="color:${C.accent};">${goalAnswer}</strong>,
                      ${goalLine}
                    </p>`)
        ),

        ebSection(
            ebLabel('Your Submission Summary') +
            ebSubLabel('Contact') +
            ebDataTable(contactRows(formData, itiInstance), 16) +
            ebSubLabel('Company Profile') +
            ebDataTable(companyProfileRows(formData), 16) +
            ebSubLabel('Operational Challenges') +
            ebDataTable(operationalRows(formData), 16) +
            ebSubLabel('Process &amp; Systems') +
            ebDataTable(processRows(formData), 16) +
            ebSubLabel('Outsourcing Experience') +
            ebDataTable(outsourcingRows(formData), 16) +
            ebSubLabel('Decision Readiness') +
            ebDataTable(decisionRows(formData), 0)
        ),

        ebFooter(`You are receiving this because you completed the Outsourcing Readiness Assessment.<br>
                  If you did not submit this form, please ignore this email.`),
    ].join(''));
}

/**
 * CTA email — contact details only, sent to admin.
 */
export function buildCtaEmail(action, formData, tierTitle, itiInstance) {
    const ctaLabels = {
        schedule: 'Schedule Your Strategy Call',
        consult:  'Book a Consultation',
    };
    const subjects = {
        schedule: 'Discovery Call for Outsourcing Ready',
        consult:  `Consultation for ${tierTitle}`,
    };
    const ctaLabel = ctaLabels[action] ?? action;
    const subject  = subjects[action]  ?? 'CTA Follow-up';

    return ebShell([
        ebHeader(ctaLabel, subject),

        ebSection(ebAccentBox('Assessment Result', tierTitle), 30),

        ebSection(ebLabel('Contact Details') +
                  ebDataTable(contactRows(formData, itiInstance))),

        ebFooter(`This lead clicked <strong style="color:${C.accent};">${ctaLabel}</strong>
                  after completing the Outsourcing Readiness Assessment.`),
    ].join(''));
}
