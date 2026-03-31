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

// ── CTA buttons in the user results email ────────────────────────────────────
// The "Next Steps" buttons in the user results email call quiz_send_cta_email()
// via the /wp-json/scorecard/v1/cta REST endpoint — identical behaviour to the
// popup CTA buttons. No page redirect. Returns JSON {success, action}.

// =============================================================================
// END OF CONFIGURATION — do not edit below this line
// =============================================================================

// ── Email builder ───────────────────────────────────────────────────────────
require_once __DIR__ . '/quiz-email-builder.php';

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
    // Main submission endpoint (POST)
    register_rest_route( 'scorecard/v1', '/submit', [
        'methods'             => 'POST',
        'callback'            => 'quiz_handle_submission',
        'permission_callback' => '__return_true',
    ] );

    // Email CTA click endpoint (GET) — triggered when user clicks a button in their results email.
    // URL format: /wp-json/scorecard/v1/cta?action=schedule&email=...&name=...&company=...&tier=...&token=...
    register_rest_route( 'scorecard/v1', '/cta', [
        'methods'             => 'GET',
        'callback'            => 'quiz_handle_email_cta',
        'permission_callback' => '__return_true',
    ] );
} );


// ── Email CTA click handler ───────────────────────────────────────────────────
// Called when the user clicks a CTA button inside their results email.
// Validates a signed token and calls quiz_send_cta_email() — the exact same
// function the popup CTA buttons use. Returns JSON only; no page redirect.

function quiz_handle_email_cta( WP_REST_Request $request ) {
    $action  = sanitize_text_field( $request->get_param( 'action' )  ?? '' );
    $email   = sanitize_email(      $request->get_param( 'email' )   ?? '' );
    $name    = sanitize_text_field( $request->get_param( 'name' )    ?? '' );
    $phone   = sanitize_text_field( $request->get_param( 'phone' )   ?? '' );
    $company = sanitize_text_field( $request->get_param( 'company' ) ?? '' );
    $tier    = sanitize_text_field( $request->get_param( 'tier' )    ?? '' );
    $token   = sanitize_text_field( $request->get_param( 'token' )   ?? '' );

    // Validate the signed token to prevent abuse
    $expected = quiz_cta_token( $action, $email, $tier );
    if ( ! hash_equals( $expected, $token ) ) {
        return new WP_Error( 'invalid_token', 'Invalid or expired token.', [ 'status' => 403 ] );
    }

    // Send the CTA email to admin — exactly the same as clicking popup CTA buttons.
    // No page redirect — buttons in email only trigger the email, same as the popup.
    $sent = quiz_send_cta_email( $name, $email, $phone, $company, $tier, $action );

    return rest_ensure_response( [
        'success' => $sent,
        'action'  => $action,
    ] );
}

/**
 * Generate a signed token for an email CTA link.
 * Uses WordPress auth keys so tokens are site-specific.
 */
function quiz_cta_token( string $action, string $email, string $tier ): string {
    return hash_hmac( 'sha256', "{$action}|{$email}|{$tier}", wp_salt('auth') );
}


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
    $answers   = is_array( $data['answers']  ?? null ) ? $data['answers']  : [];
    $insights  = is_array( $data['insights'] ?? null )
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
 