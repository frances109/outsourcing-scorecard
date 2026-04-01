<?php
/**
 * quiz-email-builder.php
 * Builds all HTML email bodies and sends them for the Outsourcing Scorecard.
 *
 * Included automatically by quiz-endpoint.php via require_once.
 * Place BOTH files in: /wp-content/mu-plugins/
 *
 * Public functions:
 *   quiz_build_headers()    — assembles wp_mail() header array
 *   quiz_send_admin_email() — full-answers notification to admin
 *   quiz_send_user_email()  — results email to user AND admin (separate sends)
 *   quiz_send_cta_email()   — contact-details-only email to admin (CTA clicks)
 *
 * Internal helpers:
 *   quiz_email_shell()      — outer DOCTYPE + table wrapper
 *   quiz_email_header()     — gradient header row (VML for Outlook, CSS for others)
 *   quiz_email_footer()     — branded footer row
 *
 * Email-client compatibility rules applied throughout:
 *   - No rgba() — all colors are solid hex computed on the dark background
 *   - Outlook gradient: VML v:rect + v:fill; padding via inner <table><td>
 *     (v:textbox inset is ignored by Outlook — <td> padding is the only reliable method)
 *   - No display:block on <span> — two separate <tr> rows per label+value pair
 *   - No CSS shorthand (padding/border) — all properties expanded individually
 *   - Inline styles only — Gmail strips anything in <style> blocks
 *   - font-family always ends with Arial,Helvetica,sans-serif for universal fallback
 *   - MSO conditional comments for Outlook column width and DPI fixes
 */


// ── Header builder ────────────────────────────────────────────────────────────

function quiz_build_headers( string $reply_to, string $cc, string $bcc ): array {
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    $reply_list = quiz_split_addresses( $reply_to );
    if ( $reply_list ) {
        $headers[] = 'Reply-To: ' . implode( ', ', $reply_list );
    }
    $cc_list = quiz_split_addresses( $cc );
    if ( $cc_list ) {
        $headers[] = 'Cc: ' . implode( ', ', $cc_list );
    }
    $bcc_list = quiz_split_addresses( $bcc );
    if ( $bcc_list ) {
        $headers[] = 'Bcc: ' . implode( ', ', $bcc_list );
    }

    return $headers;
}


// ── Outer shell ───────────────────────────────────────────────────────────────
// Wraps email rows in the full DOCTYPE, body, and outer + card tables.

function quiz_email_shell( string $rows ): string {
    return "<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml'
      xmlns:v='urn:schemas-microsoft-com:vml'
      xmlns:o='urn:schemas-microsoft-com:office:office'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <meta http-equiv='X-UA-Compatible' content='IE=edge'>
  <!--[if mso]>
  <xml>
    <o:OfficeDocumentSettings>
      <o:AllowPNG/>
      <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
  </xml>
  <![endif]-->
</head>
<body style='margin:0;padding:0;background-color:#f4f6fb;
             font-family:Arial,Helvetica,sans-serif;
             -webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;'>
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background-color:#f4f6fb;'>
  <tr>
    <td align='center' style='padding-top:40px;padding-bottom:40px;
                               padding-left:16px;padding-right:16px;'>
      <!--[if mso]>
      <table role='presentation' width='620' cellpadding='0' cellspacing='0' border='0'>
      <tr><td>
      <![endif]-->
      <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
             style='max-width:620px;width:100%;background-color:#0f1f3d;border-radius:16px;'>
        {$rows}
      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body>
</html>";
}


// ── Gradient header row ───────────────────────────────────────────────────────
// Outlook compatibility note:
//   Outlook's Word engine ignores CSS padding on <div> inside v:textbox.
//   The ONLY reliable way to add padding inside a VML shape in Outlook is via
//   a <table><tr><td style="padding:..."> nested inside the MSO conditional.
//   v:textbox inset values are also unreliable — inner <td> is the safe method.
//   Non-Outlook clients (Gmail, Apple Mail, Thunderbird) use the <div> with
//   CSS gradient; they never process the VML conditional block.

function quiz_email_header( string $title, string $subtitle ): string {
    return "
        <!-- HEADER — VML gradient for Outlook, CSS for all other clients -->
        <tr>
          <td style='padding:0;border-bottom-width:3px;border-bottom-style:solid;
                     border-bottom-color:#54c8ef;border-radius:16px 16px 0 0;'>

            <!--[if mso]>
            <v:rect xmlns:v='urn:schemas-microsoft-com:vml' fill='true' stroke='false'
                    style='width:620px;height:120px;'>
              <v:fill type='gradient' color='#0f1f3d' color2='#1a3260'
                      angle='135' focus='100%'/>
              <v:textbox inset='0,0,0,0'>
                <table role='presentation' width='620' cellpadding='0' cellspacing='0' border='0'>
                <tr>
                  <td style='padding-top:36px;padding-bottom:26px;
                             padding-left:40px;padding-right:40px;'>
            <![endif]-->

            <div style='background:linear-gradient(135deg,#0f1f3d 0%,#1a3260 100%);
                        padding-top:36px;padding-bottom:26px;
                        padding-left:40px;padding-right:40px;'>
              <h1 style='margin:0;margin-bottom:6px;font-size:22px;font-weight:800;
                         color:#ffffff;font-family:Arial,Helvetica,sans-serif;
                         letter-spacing:-0.03em;line-height:1.2;'>{$title}</h1>
              <p style='margin:0;font-size:13px;color:#7aadcc;
                        font-family:Arial,Helvetica,sans-serif;
                        letter-spacing:0.05em;text-transform:uppercase;'>{$subtitle}</p>
            </div>

            <!--[if mso]>
                  </td>
                </tr>
                </table>
              </v:textbox>
            </v:rect>
            <![endif]-->

          </td>
        </tr>";
}


// ── Footer row ────────────────────────────────────────────────────────────────

function quiz_email_footer( string $html ): string {
    return "
        <tr>
          <td style='padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:40px;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#111d35;border-width:1px;border-style:solid;
                          border-color:#1a2e4a;border-radius:10px;'>
              <tr>
                <td style='padding-top:16px;padding-bottom:16px;
                           padding-left:20px;padding-right:20px;'>
                  <p style='margin:0;font-size:12px;color:#5a7a99;text-align:center;
                            line-height:1.6;font-family:Arial,Helvetica,sans-serif;'>
                    {$html}
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>";
}


// ── Admin notification email ──────────────────────────────────────────────────
// Sent to every address in QUIZ_ADMIN_TO.
// Contains: contact info card, result tier + score badge, all 15 quiz answers.

function quiz_send_admin_email(
    string $fullname,
    string $email,
    string $phone,
    string $company,
    string $tier,
    int    $score,
    array  $answers
): bool {
    $to_list  = quiz_split_addresses( QUIZ_ADMIN_TO );
    if ( empty( $to_list ) ) {
        $to_list = [ get_option( 'admin_email' ) ];
    }
    $reply_to = trim( QUIZ_ADMIN_REPLY_TO ) !== '' ? QUIZ_ADMIN_REPLY_TO : $email;
    $subject  = "New Outsourcing Assessment — {$fullname} ({$company})";

    // Answer rows — two <tr> per question: label row + value row
    // (Outlook ignores display:block on <span>, so we use separate rows)
    $labels       = quiz_field_labels();
    $answers_rows = '';
    $keys         = array_keys( $answers );
    $last_key     = end( $keys );
    foreach ( $answers as $key => $value ) {
        $label  = isset( $labels[ $key ] ) ? esc_html( $labels[ $key ] ) : esc_html( $key );
        $v      = is_array( $value ) ? esc_html( implode( ', ', $value ) ) : esc_html( (string) $value );
        $border = ( $key === $last_key ) ? '' :
            'border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;';

        $answers_rows .= "
              <tr>
                <td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                  <p style='margin:0;font-size:11px;color:#7aadcc;
                            font-family:Arial,Helvetica,sans-serif;'>{$label}</p>
                </td>
              </tr>
              <tr>
                <td style='padding-top:0;padding-left:18px;padding-right:18px;
                           padding-bottom:12px;{$border}'>
                  <p style='margin:0;font-size:13px;color:#d9e8f5;
                            font-family:Arial,Helvetica,sans-serif;'>{$v}</p>
                </td>
              </tr>";
    }

    $contact_rows = quiz_email_contact_card( $fullname, $email, $phone, $company );

    $rows =
        quiz_email_header(
            'New Outsourcing Assessment',
            esc_html( $fullname ) . ' &mdash; ' . esc_html( $company )
        ) . "

        <!-- CONTACT INFORMATION -->
        <tr>
          <td style='padding-top:28px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:#54c8ef;
                      font-family:Arial,Helvetica,sans-serif;'>Contact Information</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              {$contact_rows}
            </table>
          </td>
        </tr>

        <!-- RESULT BADGE + SCORE -->
        <tr>
          <td style='padding-top:20px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#132030;border-width:1px;border-style:solid;
                          border-color:#1e4060;border-radius:10px;'>
              <tr>
                <td style='padding-top:16px;padding-bottom:16px;
                           padding-left:20px;padding-right:20px;'>
                  <p style='margin:0;margin-bottom:6px;font-size:10px;font-weight:700;
                             letter-spacing:0.12em;text-transform:uppercase;
                             color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Assessment Result</p>
                  <p style='margin:0;font-size:16px;font-weight:700;color:#ffffff;
                             font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $tier ) . "</p>
                </td>
                <td align='right' valign='middle'
                    style='padding-top:16px;padding-bottom:16px;
                           padding-left:20px;padding-right:20px;white-space:nowrap;'>
                  <p style='margin:0;margin-bottom:4px;font-size:10px;font-weight:700;
                             letter-spacing:0.12em;text-transform:uppercase;text-align:right;
                             color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Score</p>
                  <p style='margin:0;font-size:28px;font-weight:800;color:#ffffff;
                             text-align:right;font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $score ) . "</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ASSESSMENT ANSWERS -->
        <tr>
          <td style='padding-top:20px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:#54c8ef;
                      font-family:Arial,Helvetica,sans-serif;'>Assessment Answers</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              {$answers_rows}
            </table>
          </td>
        </tr>" .

        quiz_email_footer(
            'Submitted via the Outsourcing Readiness Assessment &mdash; ' .
            'Reply to respond to <strong style=\'color:#d9e8f5;\'>' . esc_html( $fullname ) . '</strong> ' .
            'at <strong style=\'color:#54c8ef;\'>' . esc_html( $email ) . '</strong>.'
        );

    $body    = quiz_email_shell( $rows );
    $headers = quiz_build_headers( $reply_to, QUIZ_ADMIN_CC, QUIZ_ADMIN_BCC );

    return wp_mail( $to_list, $subject, $body, $headers );
}


// ── User results email ────────────────────────────────────────────────────────
// Sent to BOTH the submitter and the admin list as two separate wp_mail() calls.
// Sending separately (not CC) ensures reliable delivery via WP Mail SMTP.
//
// User receives:  To:  submitter,  Reply-To: submitter
// Admin receives: To:  admin list, Reply-To: submitter (so admin can reply to lead)

function quiz_send_user_email(
    string $fullname,
    string $email,
    string $tier,
    string $tier_body,
    string $goal_line,
    string $goal_answer,
    array  $insights,
    array  $ctas         = [],
    string $pdf_base64   = '',
    string $pdf_filename = 'Magellan-Readiness-Results.pdf'
): bool {
    $subject = 'Your Outsourcing Readiness Results — Magellan Solutions';

    // ── Insights ──────────────────────────────────────────────────────────────
    $insights_html = '';
    foreach ( $insights as $msg ) {
        $insights_html .= "<li style='margin-bottom:8px;font-size:13px;color:#d9e8f5;"
            . "line-height:1.65;font-family:Arial,Helvetica,sans-serif;'>"
            . esc_html( $msg ) . "</li>";
    }
    $insights_section = $insights_html ? "
        <tr>
          <td style='padding-top:24px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:#54c8ef;
                      font-family:Arial,Helvetica,sans-serif;'>Your Key Insights</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr><td style='padding-top:18px;padding-bottom:18px;
                             padding-left:24px;padding-right:24px;'>
                <ul style='margin:0;padding-left:20px;'>{$insights_html}</ul>
              </td></tr>
            </table>
          </td>
        </tr>" : '';

    // ── Goal ──────────────────────────────────────────────────────────────────
    $goal_display = $goal_answer
        ? 'Since your primary goal is <strong style="color:#54c8ef;">'
            . esc_html( $goal_answer ) . '</strong>, ' . esc_html( $goal_line )
        : esc_html( $goal_line );

    $goal_section = $goal_line ? "
        <tr>
          <td style='padding-top:24px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:#54c8ef;
                      font-family:Arial,Helvetica,sans-serif;'>Your Goal</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr><td style='padding-top:16px;padding-bottom:16px;
                             padding-left:20px;padding-right:20px;'>
                <p style='margin:0;font-size:13px;color:#d9e8f5;line-height:1.7;
                          font-family:Arial,Helvetica,sans-serif;'>{$goal_display}</p>
              </td></tr>
            </table>
          </td>
        </tr>" : '';

    // ── Note ──────────────────────────────────────────────────────────────────
    $note_section = "
        <tr>
          <td style='padding-top:16px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#111d35;border-left-width:3px;border-left-style:solid;
                          border-left-color:#54c8ef;border-radius:0 6px 6px 0;'>
              <tr><td style='padding-top:14px;padding-bottom:14px;
                             padding-left:18px;padding-right:18px;'>
                <p style='margin:0;font-size:12px;color:#7aadcc;line-height:1.7;
                          font-family:Arial,Helvetica,sans-serif;'>
                  <strong style='color:#54c8ef;'>Note:</strong>
                  If you are not the sole decision-maker, you may need buy-in
                  from other stakeholders before proceeding with outsourcing.
                </p>
              </td></tr>
            </table>
          </td>
        </tr>";

    // ── CTA buttons ───────────────────────────────────────────────────────────
    $cta_section = '';
    if ( ! empty( $ctas ) ) {
        $btn_html = '';
        foreach ( $ctas as $cta ) {
            $lbl = esc_html( $cta['label']  ?? '' );
            $act = sanitize_text_field( $cta['action'] ?? '' );
            if ( ! $lbl || ! $act ) continue;

            $primary   = ( $act === 'schedule' );
            $btn_bg    = $primary ? '#54c8ef' : 'transparent';
            $btn_color = $primary ? '#0f1f3d' : '#54c8ef';

            $token = quiz_cta_token( $act, $email, $tier );
            $href  = rest_url( 'outsourcing-scorecard/v1/cta' ) . '?' . http_build_query( [
                'action'  => $act,
                'email'   => $email,
                'name'    => $fullname,
                'phone'   => '',
                'company' => $company,
                'tier'    => $tier,
                'token'   => $token,
            ] );

            $btn_html .= "
              <td align='center' style='padding-left:6px;padding-right:6px;padding-bottom:8px;'>
                <!--[if mso]>
                <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' href='{$href}'
                  style='height:44px;v-text-anchor:middle;width:220px;' arcsize='20%'
                  stroke='true' strokecolor='#54c8ef' fillcolor='{$btn_bg}'>
                  <w:anchorlock/>
                  <center style='color:{$btn_color};font-family:Arial,sans-serif;
                                  font-size:13px;font-weight:700;'>{$lbl}</center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-->
                <a href='{$href}'
                   style='background-color:{$btn_bg};border-width:2px;border-style:solid;
                          border-color:#54c8ef;border-radius:8px;color:{$btn_color};
                          display:inline-block;font-family:Arial,Helvetica,sans-serif;
                          font-size:13px;font-weight:700;padding-top:12px;padding-bottom:12px;
                          padding-left:24px;padding-right:24px;text-decoration:none;
                          -webkit-text-size-adjust:none;mso-hide:all;'>{$lbl}</a>
                <!--<![endif]-->
              </td>";
        }
        if ( $btn_html ) {
            $cta_section = "
        <tr>
          <td style='padding-top:28px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:16px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:#54c8ef;
                      font-family:Arial,Helvetica,sans-serif;'>Next Steps</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'>
              <tr><td align='center'>
                <table role='presentation' cellpadding='0' cellspacing='0' border='0'>
                  <tr style='vertical-align:top;'>{$btn_html}</tr>
                </table>
              </td></tr>
            </table>
          </td>
        </tr>";
        }
    }

    // ── Assemble HTML body ────────────────────────────────────────────────────
    $rows =
        quiz_email_header(
            'Magellan Solutions: Outsourcing Scorecard',
            esc_html( $company ) . ' &mdash; Outsourcing Readiness Results'
        ) . "

        <!-- INTRO -->
        <tr>
          <td style='padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#132030;border-width:1px;border-style:solid;
                          border-color:#1e4060;border-radius:10px;'>
              <tr>
                <td style='padding-top:20px;padding-bottom:20px;
                           padding-left:24px;padding-right:24px;'>
                  <p style='margin:0;font-size:14px;color:#d9e8f5;line-height:1.75;
                             font-family:Arial,Helvetica,sans-serif;'>
                    Thank you for completing the
                    <strong style='color:#54c8ef;'>Outsourcing Readiness Assessment</strong>.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- YOUR RESULT -->
        <tr>
          <td style='padding-top:24px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:#54c8ef;
                      font-family:Arial,Helvetica,sans-serif;'>Your Result</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr>
                <td style='padding-top:20px;padding-bottom:20px;
                           padding-left:24px;padding-right:24px;'>
                  <p style='margin:0;margin-bottom:10px;font-size:18px;font-weight:800;
                             color:#ffffff;letter-spacing:-0.02em;
                             font-family:Arial,Helvetica,sans-serif;'>
                    " . esc_html( $tier ) . "
                  </p>
                  <p style='margin:0;font-size:13px;color:#d9e8f5;line-height:1.7;
                             font-family:Arial,Helvetica,sans-serif;'>
                    " . esc_html( $tier_body ) . "
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {$insights_section}
        {$goal_section}
        {$note_section}
        {$cta_section}" .

        quiz_email_footer(
            'You are receiving this because you completed the Outsourcing Readiness Assessment.<br>
             If you did not submit this form, please ignore this email.'
        );

    $body = quiz_email_shell( $rows );

    // ── PDF attachment ────────────────────────────────────────────────────────
    $attachments = [];
    if ( ! empty( $pdf_base64 ) ) {
        $tmp  = get_temp_dir() . sanitize_file_name( $pdf_filename );
        $data = base64_decode( $pdf_base64, true );
        if ( $data !== false && file_put_contents( $tmp, $data ) !== false ) {
            $attachments[] = $tmp;
        }
    }

    // ── Send 1: To the submitter ──────────────────────────────────────────────
    $user_headers = quiz_build_headers( $email, QUIZ_USER_CC, QUIZ_USER_BCC );
    $user_sent    = wp_mail( $email, $subject, $body, $user_headers, $attachments );

    // ── Send 2: To each admin (same body, so admin sees what the user sees) ───
    // Sent as a fresh To-addressed email — CC is unreliable via WP Mail SMTP.
    // Reply-To points to the submitter so admin can reply directly to the lead.
    $admin_list = quiz_split_addresses( QUIZ_ADMIN_TO );
    $admin_to   = array_values( array_filter( $admin_list, function ( $addr ) use ( $email ) {
        return strtolower( trim( $addr ) ) !== strtolower( trim( $email ) );
    } ) );

    if ( ! empty( $admin_to ) ) {
        $admin_subject = "[Copy] {$subject}";
        $admin_headers = quiz_build_headers( $email, QUIZ_ADMIN_CC, QUIZ_ADMIN_BCC );
        wp_mail( $admin_to, $admin_subject, $body, $admin_headers, $attachments );
    }

    // Clean up temp PDF file
    if ( ! empty( $attachments ) && file_exists( $attachments[0] ) ) {
        wp_delete_file( $attachments[0] );
    }

    return $user_sent;
}


// ── CTA contact-details email ─────────────────────────────────────────────────
// Sent to admin only — triggered by popup CTA buttons OR email CTA buttons.
// Contains contact details only (no quiz answers).
// Subjects:
//   schedule → "Request for a Discovery Call"
//   consult  → "Consultation for <tier>"

function quiz_send_cta_email(
    string $fullname,
    string $email,
    string $phone,
    string $company,
    string $tier,
    string $cta_action
): bool {
    $to_list = quiz_split_addresses( QUIZ_ADMIN_TO );
    if ( empty( $to_list ) ) {
        $to_list = [ get_option( 'admin_email' ) ];
    }

    if ( $cta_action === 'schedule' ) {
        $subject   = 'Request for a Discovery Call';
        $cta_label = 'Request Your Strategy Call';
    } else {
        $subject   = 'Consultation for ' . $tier;
        $cta_label = 'Book a Consultation';
    }

    $contact_rows = quiz_email_contact_card( $fullname, $email, $phone, $company );

    $rows =
        quiz_email_header( esc_html( $cta_label ), esc_html( $subject ) ) . "

        <!-- RESULT BADGE -->
        <tr>
          <td style='padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#132030;border-width:1px;border-style:solid;
                          border-color:#1e4060;border-radius:10px;'>
              <tr>
                <td style='padding-top:16px;padding-bottom:16px;
                           padding-left:20px;padding-right:20px;'>
                  <p style='margin:0;margin-bottom:6px;font-size:10px;font-weight:700;
                             letter-spacing:0.12em;text-transform:uppercase;
                             color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Assessment Result</p>
                  <p style='margin:0;font-size:16px;font-weight:700;color:#ffffff;
                             font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $tier ) . "</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CONTACT DETAILS -->
        <tr>
          <td style='padding-top:24px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;
                      letter-spacing:0.12em;text-transform:uppercase;color:#54c8ef;
                      font-family:Arial,Helvetica,sans-serif;'>Contact Details</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              {$contact_rows}
            </table>
          </td>
        </tr>" .

        quiz_email_footer(
            'This lead clicked <strong style=\'color:#54c8ef;\'>' . esc_html( $cta_label ) . '</strong> '
            . 'after completing the Outsourcing Readiness Assessment.'
        );

    $body    = quiz_email_shell( $rows );
    $headers = quiz_build_headers( $email, QUIZ_ADMIN_CC, QUIZ_ADMIN_BCC );

    return wp_mail( $to_list, $subject, $body, $headers );
}


// ── Contact card rows (shared by admin + CTA emails) ─────────────────────────
// Returns the inner <tr> rows for Full Name / Email / Phone / Company.

function quiz_email_contact_card(
    string $fullname,
    string $email,
    string $phone,
    string $company
): string {
    return "
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Full Name</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;
                             border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                <p style='margin:0;font-size:14px;color:#ffffff;font-weight:700;
                          font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $fullname ) . "</p>
              </td></tr>
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Email</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;
                             border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                <p style='margin:0;font-size:14px;color:#54c8ef;
                          font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $email ) . "</p>
              </td></tr>
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Phone</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;
                             border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                <p style='margin:0;font-size:14px;color:#d9e8f5;
                          font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $phone ) . "</p>
              </td></tr>
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Company</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;'>
                <p style='margin:0;font-size:14px;color:#ffffff;font-weight:700;
                          font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $company ) . "</p>
              </td></tr>";
}
