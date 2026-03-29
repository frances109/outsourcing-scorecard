/**
 * config.js
 * Single source of truth for all environment-driven configuration.
 * Vite bakes import.meta.env.VITE_* values into the bundle at build time.
 *
 * Import this anywhere:
 *   import { emailConfig, adminConfig, userConfig } from './config.js';
 */

/** Split a comma-separated env string into a trimmed, non-empty array */
function splitAddresses(raw) {
    if (!raw) return [];
    return raw.split(',').map(s => s.trim()).filter(Boolean);
}

// ── EmailJS ─────────────────────────────────────────────────────────────────

export const emailConfig = {
    serviceId:  import.meta.env.VITE_EMAILJS_SERVICE_ID ?? '',
    publicKey:  import.meta.env.VITE_EMAILJS_PUBLIC_KEY ?? '',
    adminTpl:   import.meta.env.VITE_EMAILJS_ADMIN_TPL  ?? 'template_admin',
    userTpl:    import.meta.env.VITE_EMAILJS_USER_TPL   ?? 'template_user',
    ctaTpl:     import.meta.env.VITE_EMAILJS_CTA_TPL    ?? 'template_cta',
};

// ── Admin email ──────────────────────────────────────────────────────────────

export const adminConfig = {
    /** Primary To address(es) — joined string for EmailJS */
    to:         import.meta.env.VITE_ADMIN_TO        ?? '',
    /** Array of To addresses */
    toList:     splitAddresses(import.meta.env.VITE_ADMIN_TO),

    /** Reply-To address(es) — blank = default to submitter's email */
    replyTo:    import.meta.env.VITE_ADMIN_REPLY_TO  ?? '',
    replyToList: splitAddresses(import.meta.env.VITE_ADMIN_REPLY_TO),

    /** CC address(es) */
    cc:         import.meta.env.VITE_ADMIN_CC        ?? '',
    ccList:     splitAddresses(import.meta.env.VITE_ADMIN_CC),
};

// ── User email ───────────────────────────────────────────────────────────────

export const userConfig = {
    /** Reply-To address(es) for the results email */
    replyTo:    import.meta.env.VITE_USER_REPLY_TO   ?? '',
    replyToList: splitAddresses(import.meta.env.VITE_USER_REPLY_TO),

    /** CC address(es) */
    cc:         import.meta.env.VITE_USER_CC         ?? '',
    ccList:     splitAddresses(import.meta.env.VITE_USER_CC),
};
