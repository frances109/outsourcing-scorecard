<?php
/**
 * Outsourcing Quiz — WordPress REST API Endpoint
 *
 * Drop this file into: /wp-content/mu-plugins/quiz-endpoint.php
 * (create the mu-plugins folder if it doesn't exist)
 * It auto-loads — no activation needed.
 *
 * Endpoint: POST /wp-json/scorecard/v1/submit
 *
 * Required plugins:
 *   - WP Mail SMTP  (handles email delivery)
 *   - Flamingo      (saves submissions to WP dashboard)
 */


// =============================================================================
// !! CONFIGURATION — edit this section only !!
// =============================================================================

define('QUIZ_RECAPTCHA_SECRET', 'your-recaptcha-v3-secret-key');

// ── Admin notification email ──────────────────────────────────────────────────
// Who receives an email on every quiz submission.
// Separate multiple addresses with commas.

define('QUIZ_ADMIN_TO',       'admin@yourdomain.com, manager@yourdomain.com');
define('QUIZ_ADMIN_CC',       '');   // optional, e.g. 'sales@yourdomain.com, support@yourdomain.com'
define('QUIZ_ADMIN_BCC',      '');   // optional

// Reply-To on the admin notification.
// Leave empty — it will automatically use the quiz submitter's email.
define('QUIZ_ADMIN_REPLY_TO', '');

// ── User results email ────────────────────────────────────────────────────────
// Sent to the person who completed the quiz.
// Reply-To is always set to the submitter's own email — no config needed.

define('QUIZ_USER_CC',  '');   // optional
define('QUIZ_USER_BCC', '');   // optional

// ── CTA button URLs in the user results email ────────────────────────────────
// These appear as clickable buttons in the email the submitter receives.
// Leave as '#' (default) or set to real landing/booking page URLs.

define('QUIZ_CTA_SCHEDULE_URL', '#');   // URL for "Schedule Your Strategy Call" button
define('QUIZ_CTA_CONSULT_URL',  '#');   // URL for "Book a Consultation" button

// =============================================================================
// END OF CONFIGURATION — do not edit below this line
// =============================================================================


// ── Quiz question labels ──────────────────────────────────────────────────────
// Maps each form field key to a human-readable label for Flamingo storage.

function quiz_field_labels(): array {
    return [
        'fullname' => 'Full Name',
        'email'    => 'Email',
        'phone'    => 'Phone Number',
        'company'  => 'Company Name',
        'q1'       => '1. What best describes your role?',
        'q2'       => '2. Company size?',
        'q3'       => '3. Primary industry?',
        'q4'       => '4. Which areas take up most of your time?',
        'q5'       => '5. What is your biggest operational frustration right now?',
        'q6'       => '6. How severe are these challenges?',
        'q7'       => '7. Do you currently have documented processes?',
        'q8'       => '8. Do you use collaboration tools for remote work?',
        'q9'       => '9. Have you outsourced before?',
        'q10'      => '10. What is your main concern about outsourcing?',
        'q11'      => '11. How comfortable are you with change and risk in operations?',
        'q12'      => '12. Do you have budget allocated for outsourcing?',
        'q13'      => '13. Timeline for outsourcing?',
        'q14'      => '14. What is your primary goal for outsourcing?',
        'q15'      => '15. Are you the final decision-maker for outsourcing?',
        'score'    => 'Score',
        'tier'     => 'Result Tier',
    ];
}
 
 
// ── q14 goal label map ───────────────────────────────────────────────────────
// Maps raw q14 option values to human-readable labels (mirrors data.js options)

function quiz_q14_labels(): array {
    return [
        'reduce_costs'    => 'Reduce Costs',
        'scale_faster'    => 'Scale Faster',
        'improve_quality' => 'Improve Quality',
        'free_up_time'    => 'Free Up Time',
        'other'           => 'Other',
    ];
}

function quiz_q14_label( string $value ): string {
    $map = quiz_q14_labels();
    return $map[ $value ] ?? ucwords( str_replace( '_', ' ', $value ) );
}


// ── Address helper ────────────────────────────────────────────────────────────
 
function quiz_split_addresses( string $raw ): array {
    if ( trim( $raw ) === '' ) return [];
    return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
}
 
 
// ── Register REST route ───────────────────────────────────────────────────────
 
add_action( 'rest_api_init', function () {
    register_rest_route( 'scorecard/v1', '/submit', [
        'methods'             => 'POST',
        'callback'            => 'quiz_handle_submission',
        'permission_callback' => '__return_true',
    ] );
} );
 
 
// ── Main handler ──────────────────────────────────────────────────────────────
 
function quiz_handle_submission( WP_REST_Request $request ) {
    $data = $request->get_json_params();
 
    // 1. Validate reCAPTCHA
    $token  = sanitize_text_field( $data['recaptcha_token'] ?? '' );
    $result = quiz_verify_recaptcha( $token );
 
    if ( empty( $result['success'] ) ) {
        return new WP_Error( 'recaptcha_failed', 'reCAPTCHA verification failed.', [ 'status' => 403 ] );
    }
    if ( isset( $result['score'] ) && $result['score'] < 0.5 ) {
        return new WP_Error( 'recaptcha_score', 'reCAPTCHA score too low.', [ 'status' => 403 ] );
    }
 
    // 2. Sanitize all fields
    $fullname  = sanitize_text_field( $data['fullname']  ?? '' );
    $email     = sanitize_email( $data['email']          ?? '' );
    $phone     = sanitize_text_field( $data['phone']     ?? '' );
    $company   = sanitize_text_field( $data['company']   ?? '' );
    $tier      = sanitize_text_field( $data['tier']      ?? '' );
    $tier_body = sanitize_textarea_field( $data['tier_body'] ?? '' );
    $goal_line = sanitize_textarea_field( $data['goal_line'] ?? '' );
    $score     = intval( $data['score']                  ?? 0 );
    $answers      = is_array( $data['answers']  ?? null ) ? $data['answers']  : [];
    $insights     = is_array( $data['insights'] ?? null )
        ? array_map( 'sanitize_text_field', $data['insights'] )
        : [];
    $ctas         = is_array( $data['ctas'] ?? null )
        ? $data['ctas']
        : [];
    $pdf_base64   = sanitize_text_field( $data['pdf_base64']   ?? '' );
    $pdf_filename = sanitize_file_name(  $data['pdf_filename'] ?? 'Magellan-Readiness-Results.pdf' );
 
    if ( ! $fullname || ! is_email( $email ) || ! $company ) {
        return new WP_Error( 'missing_fields', 'Required fields are missing.', [ 'status' => 400 ] );
    }
 
    $is_cta    = ! empty( $data['is_cta'] );
    $cta_action = sanitize_text_field( $answers['cta_action'] ?? '' );

    if ( $is_cta ) {
        // CTA click: send contact-details-only email to admin with CTA-specific subject
        $admin_sent = quiz_send_cta_email( $fullname, $email, $phone, $company, $tier, $cta_action );
        $user_sent  = false;
        // No Flamingo save for CTA clicks (already saved on initial submit)
    } else {
        // Full quiz submission: send both admin full-answers email + user results email
        $admin_sent = quiz_send_admin_email( $fullname, $email, $phone, $company, $tier, $score, $answers );
        $goal_answer = quiz_q14_label( sanitize_text_field( $data['goal_answer'] ?? '' ) );
        $user_sent  = quiz_send_user_email( $fullname, $email, $tier, $tier_body, $goal_line, $goal_answer, $insights, $ctas, $pdf_base64, $pdf_filename );

        // 4. Save to Flamingo
        quiz_save_to_flamingo( $fullname, $email, $phone, $company, $tier, $tier_body, $score, $answers, $insights );
    }
 
    return rest_ensure_response( [
        'success'    => true,
        'admin_sent' => $admin_sent,
        'user_sent'  => $user_sent,
    ] );
}
 
 
// ── reCAPTCHA v3 verification ─────────────────────────────────────────────────
 
function quiz_verify_recaptcha( string $token ): array {
    if ( empty( $token ) ) return [ 'success' => false ];
 
    $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => QUIZ_RECAPTCHA_SECRET,
            'response' => $token,
        ],
    ] );
 
    if ( is_wp_error( $response ) ) return [ 'success' => false ];
 
    return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [ 'success' => false ];
}
 
 
// ── Build email headers ───────────────────────────────────────────────────────
 
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
 

// ── Admin notification email — full Outlook-safe table layout ─────────────────

function quiz_send_admin_email(
    string $fullname,
    string $email,
    string $phone,
    string $company,
    string $tier,
    int    $score,
    array  $answers
): bool {
    $to_list = quiz_split_addresses( QUIZ_ADMIN_TO );
    if ( empty( $to_list ) ) {
        $to_list = [ get_option( 'admin_email' ) ];
    }

    $reply_to = trim( QUIZ_ADMIN_REPLY_TO ) !== '' ? QUIZ_ADMIN_REPLY_TO : $email;
    $subject  = "New Outsourcing Assessment — {$fullname} ({$company})";

    $labels       = quiz_field_labels();
    $answers_rows = '';
    $answer_keys  = array_keys( $answers );
    $last_key     = end( $answer_keys );
    foreach ( $answers as $key => $value ) {
        $label   = isset( $labels[ $key ] ) ? esc_html( $labels[ $key ] ) : esc_html( $key );
        $v       = is_array( $value ) ? esc_html( implode( ', ', $value ) ) : esc_html( (string) $value );
        $is_last = ( $key === $last_key );
        $border  = $is_last ? '' : 'border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;';
        $answers_rows .= "
              <tr>
                <td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                  <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>{$label}</p>
                </td>
              </tr>
              <tr>
                <td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;{$border}'>
                  <p style='margin:0;font-size:13px;color:#d9e8f5;font-family:Arial,Helvetica,sans-serif;'>{$v}</p>
                </td>
              </tr>";
    }

    $body = "<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xmlns:v='urn:schemas-microsoft-com:vml'
      xmlns:o='urn:schemas-microsoft-com:office:office'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <meta http-equiv='X-UA-Compatible' content='IE=edge'>
  <!--[if mso]>
  <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  <![endif]-->
</head>
<body style='margin:0;padding:0;background-color:#f4f6fb;font-family:Arial,Helvetica,sans-serif;
             -webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;'>
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background-color:#f4f6fb;'>
  <tr>
    <td align='center' style='padding-top:40px;padding-bottom:40px;padding-left:16px;padding-right:16px;'>
      <!--[if mso]><table role='presentation' width='620' cellpadding='0' cellspacing='0' border='0'><tr><td><![endif]-->
      <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
             style='max-width:620px;width:100%;background-color:#0f1f3d;border-radius:16px;overflow:hidden;'>

        <!-- HEADER -->
        <tr>
          <td style='padding:0;border-bottom-width:3px;border-bottom-style:solid;
                     border-bottom-color:#54c8ef;border-radius:16px 16px 0 0;'>
            <!--[if mso]>
            <v:rect xmlns:v='urn:schemas-microsoft-com:vml' fill='true' stroke='false'
                    style='width:620px;height:110px;'>
              <v:fill type='gradient' color='#0f1f3d' color2='#1a3260' angle='135' focus='100%'/>
              <v:textbox inset='0,0,0,0'>
            <![endif]-->
            <div style='background:linear-gradient(135deg,#0f1f3d 0%,#1a3260 100%);
                        padding-top:40px;padding-bottom:30px;padding-left:40px;padding-right:40px;'>
              <h1 style='margin:0;margin-bottom:6px;font-size:22px;font-weight:800;
                         color:#ffffff;font-family:Arial,Helvetica,sans-serif;
                         letter-spacing:-0.03em;line-height:1.2;'>New Outsourcing Assessment</h1>
              <p style='margin:0;font-size:13px;color:#7aadcc;
                        font-family:Arial,Helvetica,sans-serif;
                        letter-spacing:0.05em;text-transform:uppercase;'>
                " . esc_html( $fullname ) . " &mdash; " . esc_html( $company ) . "
              </p>
            </div>
            <!--[if mso]></v:textbox></v:rect><![endif]-->
          </td>
        </tr>

        <!-- CONTACT INFORMATION -->
        <tr>
          <td style='padding-top:28px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;letter-spacing:0.12em;
                      text-transform:uppercase;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Contact Information</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Full Name</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                <p style='margin:0;font-size:14px;color:#ffffff;font-weight:700;font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $fullname ) . "</p>
              </td></tr>
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Email</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                <p style='margin:0;font-size:14px;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $email ) . "</p>
              </td></tr>
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Phone</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                <p style='margin:0;font-size:14px;color:#d9e8f5;font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $phone ) . "</p>
              </td></tr>
              <tr><td style='padding-top:12px;padding-left:18px;padding-right:18px;padding-bottom:2px;'>
                <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Company</p>
              </td></tr>
              <tr><td style='padding-top:0;padding-left:18px;padding-right:18px;padding-bottom:12px;'>
                <p style='margin:0;font-size:14px;color:#ffffff;font-weight:700;font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $company ) . "</p>
              </td></tr>
            </table>
          </td>
        </tr>

        <!-- RESULT BADGE WITH SCORE -->
        <tr>
          <td style='padding-top:20px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#132030;border-width:1px;border-style:solid;
                          border-color:#1e4060;border-radius:10px;'>
              <tr>
                <td style='padding-top:16px;padding-bottom:16px;padding-left:20px;padding-right:20px;'>
                  <p style='margin:0;margin-bottom:6px;font-size:10px;font-weight:700;letter-spacing:0.12em;
                             text-transform:uppercase;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Assessment Result</p>
                  <p style='margin:0;font-size:16px;font-weight:700;color:#ffffff;font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $tier ) . "</p>
                </td>
                <td align='right' valign='middle'
                    style='padding-top:16px;padding-bottom:16px;padding-left:20px;padding-right:20px;white-space:nowrap;'>
                  <p style='margin:0;margin-bottom:4px;font-size:10px;font-weight:700;letter-spacing:0.12em;
                             text-transform:uppercase;text-align:right;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Score</p>
                  <p style='margin:0;font-size:28px;font-weight:800;color:#ffffff;text-align:right;font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $score ) . "</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- ASSESSMENT ANSWERS -->
        <tr>
          <td style='padding-top:20px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;letter-spacing:0.12em;
                      text-transform:uppercase;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Assessment Answers</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              {$answers_rows}
            </table>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style='padding-top:28px;padding-left:40px;padding-right:40px;padding-bottom:40px;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#111d35;border-width:1px;border-style:solid;
                          border-color:#1a2e4a;border-radius:10px;'>
              <tr>
                <td style='padding-top:16px;padding-bottom:16px;padding-left:20px;padding-right:20px;'>
                  <p style='margin:0;font-size:12px;color:#5a7a99;text-align:center;line-height:1.6;
                            font-family:Arial,Helvetica,sans-serif;'>
                    Submitted via the Outsourcing Readiness Assessment &mdash;
                    Reply to respond to <strong style='color:#d9e8f5;'>" . esc_html( $fullname ) . "</strong>
                    at <strong style='color:#54c8ef;'>" . esc_html( $email ) . "</strong>.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body></html>";

    $headers = quiz_build_headers( $reply_to, QUIZ_ADMIN_CC, QUIZ_ADMIN_BCC );

    return wp_mail( $to_list, $subject, $body, $headers );
}
 
 
// ── User results email ────────────────────────────────────────────────────────
// Receives tier_body, goal_line, and insights — full branded layout matching
// the JS email-builder.js output.
 
function quiz_send_user_email(
    string $fullname,
    string $email,
    string $tier,
    string $tier_body,
    string $goal_line,
    string $goal_answer,
    array  $insights,
    array  $ctas        = [],   // CTA buttons [{label, action}] from tier
    string $pdf_base64  = '',   // Results PDF as base64 for attachment
    string $pdf_filename = 'Magellan-Readiness-Results.pdf'
): bool {
    $subject = 'Your Outsourcing Readiness Results — Magellan Solutions';
 
    $insights_html = '';
    foreach ( $insights as $msg ) {
        $insights_html .= "<li style='margin-bottom:8px;font-size:13px;"
            . "color:#d9e8f5;line-height:1.65;'>"
            . esc_html( $msg ) . "</li>";
    }
 
    $insights_section = $insights_html ? "
        <tr>
          <td style='padding-top:24px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;letter-spacing:0.12em;
                      text-transform:uppercase;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Your Key Insights</p>
            <table width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr><td style='padding-top:18px;padding-bottom:18px;padding-left:24px;padding-right:24px;'>
                <ul style='margin:0;padding-left:20px;'>{$insights_html}</ul>
              </td></tr>
            </table>
          </td>
        </tr>" : '';
 
    // goal_section: prepend "Since your primary goal is <goal>, " before goal_line
    // $goal_answer is passed as a resolved label from the handler (e.g. "Reduce Costs")
    $goal_display = $goal_answer
        ? 'Since your primary goal is <strong style="color:#54c8ef;">' . esc_html( $goal_answer ) . '</strong>, ' . esc_html( $goal_line )
        : esc_html( $goal_line );

    $goal_section = $goal_line ? "
        <tr>
          <td style='padding-top:24px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;letter-spacing:0.12em;
                      text-transform:uppercase;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Your Goal</p>
            <table width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr><td style='padding-top:16px;padding-bottom:16px;padding-left:20px;padding-right:20px;'>
                <p style='margin:0;font-size:13px;color:#d9e8f5;line-height:1.7;'>"
                    . $goal_display . "
                </p>
              </td></tr>
            </table>
          </td>
        </tr>" : '';

    // Note section — shown when tier is not "Outsourcing Ready" (decision-maker note)
    // We include it always as a soft note; matches JS popup behaviour
    $note_section = "
        <tr>
          <td style='padding-top:16px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#111d35;border-left-width:3px;border-left-style:solid;border-left-color:#54c8ef;border-radius:0 6px 6px 0;'>
              <tr><td style='padding-top:14px;padding-bottom:14px;padding-left:18px;padding-right:18px;'>
                <p style='margin:0;font-size:12px;color:#7aadcc;line-height:1.7;'>
                  <strong style='color:#54c8ef;'>Note:</strong>
                  If you are not the sole decision-maker, you may need buy-in from other
                  stakeholders before proceeding with outsourcing.
                </p>
              </td></tr>
            </table>
          </td>
        </tr>";

    // ── CTA buttons section ──────────────────────────────────────────────────
    // Build inline CTA buttons matching the popup (excludes 'download' — frontend only)
    $cta_section = '';
    if ( ! empty( $ctas ) ) {
        $btn_html = '';
        foreach ( $ctas as $cta ) {
            $cta_label_btn  = esc_html( $cta['label']  ?? '' );
            $cta_action_btn = sanitize_text_field( $cta['action'] ?? '' );
            if ( ! $cta_label_btn || ! $cta_action_btn ) continue;

            $is_primary = ( $cta_action_btn === 'schedule' );
            $btn_bg     = $is_primary ? '#54c8ef' : 'transparent';
            $btn_color  = $is_primary ? '#0f1f3d' : '#54c8ef';

            $href = $is_primary
                ? ( defined('QUIZ_CTA_SCHEDULE_URL') ? QUIZ_CTA_SCHEDULE_URL : '#' )
                : ( defined('QUIZ_CTA_CONSULT_URL')  ? QUIZ_CTA_CONSULT_URL  : '#' );

            $btn_html .= "
              <td align='center' style='padding-left:6px;padding-right:6px;padding-bottom:8px;'>
                <!--[if mso]>
                <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' href='{$href}'
                  style='height:44px;v-text-anchor:middle;width:220px;' arcsize='20%'
                  stroke='true' strokecolor='#54c8ef' fillcolor='{$btn_bg}'>
                  <w:anchorlock/>
                  <center style='color:{$btn_color};font-family:Arial,sans-serif;
                                  font-size:13px;font-weight:700;'>{$cta_label_btn}</center>
                </v:roundrect>
                <![endif]-->
                <!--[if !mso]><!-->
                <a href='{$href}'
                   style='background-color:{$btn_bg};border-width:2px;border-style:solid;
                          border-color:#54c8ef;border-radius:8px;color:{$btn_color};
                          display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:13px;
                          font-weight:700;padding-top:12px;padding-bottom:12px;
                          padding-left:24px;padding-right:24px;text-decoration:none;
                          -webkit-text-size-adjust:none;mso-hide:all;'>{$cta_label_btn}</a>
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

    $body = "<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xmlns:v='urn:schemas-microsoft-com:vml'
      xmlns:o='urn:schemas-microsoft-com:office:office'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <meta http-equiv='X-UA-Compatible' content='IE=edge'>
  <!--[if mso]>
  <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  <![endif]-->
</head>
<body style='margin:0;padding:0;background-color:#f4f6fb;font-family:Arial,Helvetica,sans-serif;
             -webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;'>
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background-color:#f4f6fb;'>
  <tr>
    <td align='center' style='padding-top:40px;padding-bottom:40px;padding-left:16px;padding-right:16px;'>
      <!--[if mso]><table role='presentation' width='620' cellpadding='0' cellspacing='0' border='0'><tr><td><![endif]-->
      <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
             style='max-width:620px;width:100%;background-color:#0f1f3d;border-radius:16px;overflow:hidden;'>

        <!-- HEADER -->
        <tr>
          <td style='padding:0;border-bottom-width:3px;border-bottom-style:solid;
                     border-bottom-color:#54c8ef;border-radius:16px 16px 0 0;'>
            <!--[if mso]>
            <v:rect xmlns:v='urn:schemas-microsoft-com:vml' fill='true' stroke='false'
                    style='width:620px;height:110px;'>
              <v:fill type='gradient' color='#0f1f3d' color2='#1a3260' angle='135' focus='100%'/>
              <v:textbox inset='0,0,0,0'>
            <![endif]-->
            <div style='background:linear-gradient(135deg,#0f1f3d 0%,#1a3260 100%);
                        padding-top:40px;padding-bottom:30px;padding-left:40px;padding-right:40px;'>
              <h1 style='margin:0;margin-bottom:6px;font-size:22px;font-weight:800;
                         color:#ffffff;font-family:Arial,Helvetica,sans-serif;
                         letter-spacing:-0.03em;line-height:1.2;'>Thank You, " . esc_html( $fullname ) . "!</h1>
              <p style='margin:0;font-size:13px;color:#7aadcc;
                        font-family:Arial,Helvetica,sans-serif;
                        letter-spacing:0.05em;text-transform:uppercase;'>Your Assessment Has Been Received</p>
            </div>
            <!--[if mso]></v:textbox></v:rect><![endif]-->
          </td>
        </tr>

        <!-- INTRO -->
        <tr>
          <td style='padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#132030;border-width:1px;border-style:solid;
                          border-color:#1e4060;border-radius:10px;'>
              <tr>
                <td style='padding-top:20px;padding-bottom:20px;padding-left:24px;padding-right:24px;'>
                  <p style='margin:0;font-size:14px;color:#d9e8f5;line-height:1.75;
                             font-family:Arial,Helvetica,sans-serif;'>
                    Thank you for completing the
                    <strong style='color:#54c8ef;'>Outsourcing Readiness Assessment</strong>.
                    Our team will review your responses and reach out shortly.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- YOUR RESULT -->
        <tr>
          <td style='padding-top:24px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <p style='margin:0;margin-bottom:12px;font-size:10px;font-weight:700;letter-spacing:0.12em;
                      text-transform:uppercase;color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Your Result</p>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr>
                <td style='padding-top:20px;padding-bottom:20px;padding-left:24px;padding-right:24px;'>
                  <p style='margin:0;margin-bottom:10px;font-size:18px;font-weight:800;color:#ffffff;
                             letter-spacing:-0.02em;font-family:Arial,Helvetica,sans-serif;'>
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

        " . $insights_section . "
        " . $goal_section . "
        " . $note_section . "
        " . $cta_section . "

        <!-- FOOTER -->
        <tr>
          <td style='padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:40px;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#111d35;border-width:1px;border-style:solid;
                          border-color:#1a2e4a;border-radius:10px;'>
              <tr>
                <td style='padding-top:16px;padding-bottom:16px;padding-left:20px;padding-right:20px;'>
                  <p style='margin:0;font-size:12px;color:#5a7a99;text-align:center;line-height:1.6;
                            font-family:Arial,Helvetica,sans-serif;'>
                    You are receiving this because you completed the Outsourcing Readiness Assessment.<br>
                    If you did not submit this form, please ignore this email.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body></html>";

    // ── Send email with PDF attachment ────────────────────────────────────────
    $admin_list = quiz_split_addresses( QUIZ_ADMIN_TO );
    $cc_list    = quiz_split_addresses( QUIZ_USER_CC );

    foreach ( $admin_list as $admin_addr ) {
        if ( strtolower( $admin_addr ) !== strtolower( $email )
             && ! in_array( strtolower( $admin_addr ), array_map( 'strtolower', $cc_list ), true ) ) {
            $cc_list[] = $admin_addr;
        }
    }

    $cc_string  = implode( ', ', $cc_list );
    $headers    = quiz_build_headers( $email, $cc_string, QUIZ_USER_BCC );

    // Attach the Results PDF if provided as base64
    $attachments = [];
    if ( ! empty( $pdf_base64 ) ) {
        $tmp_dir  = get_temp_dir();
        $tmp_file = $tmp_dir . sanitize_file_name( $pdf_filename );
        $decoded  = base64_decode( $pdf_base64, true );
        if ( $decoded !== false && file_put_contents( $tmp_file, $decoded ) !== false ) {
            $attachments[] = $tmp_file;
        }
    }

    $sent = wp_mail( $email, $subject, $body, $headers, $attachments );

    // Clean up temp file
    if ( ! empty( $attachments ) && file_exists( $attachments[0] ) ) {
        wp_delete_file( $attachments[0] );
    }

    return $sent;
}
 
 
// ── CTA contact-details email (admin only, triggered by popup button) ──────────
// Sends only the contact details (q16 group) to admin with CTA-specific subject.

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

    // CTA-specific subjects (mirrors email-builder.js buildCtaEmail subjects)
    if ( $cta_action === 'schedule' ) {
        $subject    = 'Request for a Discovery Call';
        $cta_label  = 'Request Your Strategy Call';
    } else {
        $subject    = 'Consultation for ' . $tier;
        $cta_label  = 'Book a Consultation';
    }

    $reply_to = $email; // reply goes to the lead

    $body = "<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <!--[if mso]>
  <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  <![endif]-->
</head>
<body style='margin:0;padding:0;background-color:#f4f6fb;font-family:Arial,Helvetica,sans-serif;
             -webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;'>
<table width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background-color:#f4f6fb;padding-top:40px;padding-bottom:40px;'>
  <tr>
    <td align='center' style='padding-left:16px;padding-right:16px;'>
      <!--[if mso]><table width='620' cellpadding='0' cellspacing='0' border='0'><tr><td><![endif]-->
      <table width='620' cellpadding='0' cellspacing='0' border='0'
             style='max-width:620px;width:100%;background-color:#0f1f3d;border-radius:16px;'>

        <!-- HEADER -->
        <tr>
          <td style='background-color:#1a3260;
                     padding-top:40px;padding-bottom:30px;padding-left:40px;padding-right:40px;
                     border-bottom-width:3px;border-bottom-style:solid;border-bottom-color:#54c8ef;
                     border-radius:16px 16px 0 0;'>
            <h1 style='margin:0;font-size:22px;font-weight:800;color:#ffffff;
                       font-family:Arial,Helvetica,sans-serif;letter-spacing:-0.03em;'>
              " . esc_html( $cta_label ) . "
            </h1>
            <p style='margin:8px 0 0;font-size:13px;color:#7aadcc;
                      font-family:Arial,Helvetica,sans-serif;
                      letter-spacing:0.05em;text-transform:uppercase;'>
              " . esc_html( $subject ) . "
            </p>
          </td>
        </tr>

        <!-- ASSESSMENT RESULT BADGE -->
        <tr>
          <td style='padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:0;'>
            <table width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#132030;
                          border-width:1px;border-style:solid;border-color:#1e4060;
                          border-radius:10px;'>
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
                      letter-spacing:0.12em;text-transform:uppercase;
                      color:#54c8ef;font-family:Arial,Helvetica,sans-serif;'>Contact Details</p>
            <table width='100%' cellpadding='0' cellspacing='0' border='0'
                   style='background-color:#162848;border-radius:10px;'>
              <tr>
                <td style='padding-top:14px;padding-bottom:14px;padding-left:18px;padding-right:18px;
                           border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                  <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Full Name</p>
                  <p style='margin:0;font-size:14px;color:#ffffff;font-weight:600;
                                font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $fullname ) . "</span>
                </td>
              </tr>
              <tr>
                <td style='padding-top:14px;padding-bottom:14px;padding-left:18px;padding-right:18px;
                           border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                  <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Email</p>
                  <p style='margin:0;font-size:14px;color:#54c8ef;
                                font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $email ) . "</span>
                </td>
              </tr>
              <tr>
                <td style='padding-top:14px;padding-bottom:14px;padding-left:18px;padding-right:18px;
                           border-bottom-width:1px;border-bottom-style:solid;border-bottom-color:#1e3558;'>
                  <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Phone</p>
                  <p style='margin:0;font-size:14px;color:#d9e8f5;
                                font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $phone ) . "</span>
                </td>
              </tr>
              <tr>
                <td style='padding-top:14px;padding-bottom:14px;padding-left:18px;padding-right:18px;'>
                  <p style='margin:0;font-size:11px;color:#7aadcc;font-family:Arial,Helvetica,sans-serif;'>Company</p>
                  <p style='margin:0;font-size:14px;color:#ffffff;font-weight:600;
                                font-family:Arial,Helvetica,sans-serif;'>" . esc_html( $company ) . "</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style='padding-top:30px;padding-left:40px;padding-right:40px;padding-bottom:40px;'>
            <table width='100%' cellpadding='0' cellspacing='0' border='0'>
              <tr>
                <td style='background-color:#111d35;
                           border-width:1px;border-style:solid;border-color:#1a2e4a;
                           border-radius:10px;
                           padding-top:16px;padding-bottom:16px;
                           padding-left:20px;padding-right:20px;'>
                  <p style='margin:0;font-size:12px;color:#5a7a99;
                            text-align:center;line-height:1.6;
                            font-family:Arial,Helvetica,sans-serif;'>
                    This lead clicked
                    <strong style='color:#54c8ef;'>" . esc_html( $cta_label ) . "</strong>
                    after completing the Outsourcing Readiness Assessment.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
      <!--[if mso]></td></tr></table><![endif]-->
    </td>
  </tr>
</table>
</body></html>";

    $headers = quiz_build_headers( $reply_to, QUIZ_ADMIN_CC, QUIZ_ADMIN_BCC );

    return wp_mail( $to_list, $subject, $body, $headers );
}


// ── Save to Flamingo ──────────────────────────────────────────────────────────
// Inserts directly into Flamingo's 'flamingo_inbound' custom post type.
// This works without CF7 active and without any Flamingo helper functions.
 
function quiz_save_to_flamingo(
    string $fullname,
    string $email,
    string $phone,
    string $company,
    string $tier,
    string $tier_body,
    int    $score,
    array  $answers,
    array  $insights
): void {
    $labels = quiz_field_labels();
    $skip   = [ 'fullname', 'email', 'phone', 'company', 'score', 'tier' ];
 
    // Build ordered fields with human-readable labels
    $ordered_fields = [
        'Full Name'          => $fullname,
        'Email'              => $email,
        'Phone Number'       => $phone,
        'Company Name'       => $company,
        'Result Tier'        => $tier,
        'Result Description' => $tier_body,
        'Score'              => (string) $score,
        'Key Insights'       => implode( ' | ', $insights ),
    ];
 
    foreach ( $labels as $key => $label ) {
        if ( in_array( $key, $skip, true ) ) continue;
        if ( array_key_exists( $key, $answers ) ) {
            $value                    = $answers[ $key ];
            $ordered_fields[ $label ] = is_array( $value )
                ? implode( ', ', $value )
                : (string) $value;
        }
    }
 
    if ( post_type_exists( 'flamingo_inbound' ) ) {
        $post_id = wp_insert_post( [
            'post_type'   => 'flamingo_inbound',
            'post_title'  => "New Assessment — {$fullname} ({$company})",
            'post_status' => 'publish',
            'meta_input'  => [
                '_from_name'  => $fullname,
                '_from_email' => $email,
                '_subject'    => "New Assessment — {$fullname} ({$company})",
                '_channel'    => 'Outsourcing Scorecard',
                '_fields'     => $ordered_fields,
                '_remote_ip'  => $_SERVER['REMOTE_ADDR']     ?? '',
                '_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                '_spam'       => false,
            ],
        ] );
 
        // Also store each field as individual post meta for Flamingo's detail view
        if ( $post_id && ! is_wp_error( $post_id ) ) {
            foreach ( $ordered_fields as $label => $value ) {
                add_post_meta( $post_id, sanitize_key( $label ), $value );
            }
        }
    } else {
        // Flamingo not active — fall back to a private WP post
        quiz_save_as_post( $fullname, $email, $phone, $company, $tier, $score, $ordered_fields );
    }
}
 
 
// ── Fallback: save as a private WordPress post ────────────────────────────────
 
function quiz_save_as_post(
    string $fullname,
    string $email,
    string $phone,
    string $company,
    string $tier,
    int    $score,
    array  $ordered_fields
): void {
    if ( ! post_type_exists( 'quiz_submission' ) ) {
        register_post_type( 'quiz_submission', [
            'label'    => 'Scorecard Submissions',
            'public'   => false,
            'show_ui'  => true,
            'supports' => [ 'title', 'custom-fields' ],
        ] );
    }
 
    $meta = [];
    foreach ( $ordered_fields as $label => $value ) {
        $meta[ '_quiz_' . sanitize_key( $label ) ] = $value;
    }
 
    wp_insert_post( [
        'post_type'   => 'quiz_submission',
        'post_title'  => "{$fullname} — {$company}",
        'post_status' => 'private',
        'meta_input'  => $meta,
    ] );
}
 