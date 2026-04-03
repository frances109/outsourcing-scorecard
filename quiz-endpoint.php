<?php
/**
 * WordPress REST API endpoint for the Outsourcing Scorecard.
 *
 * Drop BOTH files into: /wp-content/mu-plugins/
 *   - quiz-endpoint.php      (this file — routing, validation, data handling)
 *   - quiz-email-builder.php (email HTML construction and delivery)
 *
 * Endpoint: POST /wp-json/outsourcing-scorecard/v1/submit
 * CTA URL:  GET  /wp-json/outsourcing-scorecard/v1/cta
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
// Receives: (1) full answers + score email, AND (2) the same results email
// the user sees — sent as two separate emails for reliable delivery.
// Separate multiple addresses with commas.

define('QUIZ_ADMIN_TO',       'admin@yourdomain.com, manager@yourdomain.com');
define('QUIZ_ADMIN_CC',       '');   // optional
define('QUIZ_ADMIN_BCC',      '');   // optional

// Reply-To on the admin notification.
// Leave empty — it will automatically use the quiz submitter's email.
define('QUIZ_ADMIN_REPLY_TO', '');

// ── User results email ────────────────────────────────────────────────────────
// Sent to the person who completed the quiz.
// Reply-To is always set to the submitter's own email — no config needed.

define('QUIZ_USER_CC',  '');   // optional
define('QUIZ_USER_BCC', '');   // optional

// ── CTA buttons in the results email ─────────────────────────────────────────
// Clicking a CTA button in the email calls quiz_send_cta_email() server-side
// (same as the popup buttons). No page redirect. Returns JSON {success, action}.

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


// ── Register REST routes ──────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {

    // Main form submission
    register_rest_route( 'outsourcing-scorecard/v1', '/submit', [
        'methods'             => 'POST',
        'callback'            => 'quiz_handle_submission',
        'permission_callback' => '__return_true',
    ] );

    // Email CTA button click — same action as popup CTA, no page redirect
    register_rest_route( 'outsourcing-scorecard/v1', '/cta', [
        'methods'             => 'GET',
        'callback'            => 'quiz_handle_email_cta',
        'permission_callback' => '__return_true',
    ] );

} );


// ── Email CTA handler ─────────────────────────────────────────────────────────
// Triggered when user clicks a "Next Steps" button inside the results email.
// Validates a signed HMAC token, then calls quiz_send_cta_email() — the exact
// same function triggered by popup CTA buttons. Returns JSON; no redirect.

function quiz_handle_email_cta( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $action  = sanitize_text_field( $request->get_param( 'action' )  ?? '' );
    $email   = sanitize_email(      $request->get_param( 'email' )   ?? '' );
    $name    = sanitize_text_field( $request->get_param( 'name' )    ?? '' );
    $phone   = sanitize_text_field( $request->get_param( 'phone' )   ?? '' );
    $company = sanitize_text_field( $request->get_param( 'company' ) ?? '' );
    $tier    = sanitize_text_field( $request->get_param( 'tier' )    ?? '' );
    $token   = sanitize_text_field( $request->get_param( 'token' )   ?? '' );

    $expected = quiz_cta_token( $action, $email, $tier );
    if ( ! hash_equals( $expected, $token ) ) {
        return new WP_Error( 'invalid_token', 'Invalid or expired token.', [ 'status' => 403 ] );
    }

    $sent = quiz_send_cta_email( $name, $email, $phone, $company, $tier, $action );

    return rest_ensure_response( [ 'success' => $sent, 'action' => $action ] );
}

/**
 * Generate a signed HMAC token for email CTA links.
 * Ties the token to the action, email, and tier so it cannot be reused
 * for a different action or a different user.
 */
function quiz_cta_token( string $action, string $email, string $tier ): string {
    return hash_hmac( 'sha256', "{$action}|{$email}|{$tier}", wp_salt( 'auth' ) );
}


// ── Main form submission handler ──────────────────────────────────────────────

function quiz_handle_submission( WP_REST_Request $request ): WP_REST_Response|WP_Error {
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

    // 2. Sanitize fields
    $fullname     = sanitize_text_field( $data['fullname']     ?? '' );
    $email        = sanitize_email( $data['email']             ?? '' );
    $phone        = sanitize_text_field( $data['phone']        ?? '' );
    $company      = sanitize_text_field( $data['company']      ?? '' );
    $tier         = sanitize_text_field( $data['tier']         ?? '' );
    $tier_body    = sanitize_textarea_field( $data['tier_body']  ?? '' );
    $goal_line    = sanitize_textarea_field( $data['goal_line']  ?? '' );
    $score        = intval( $data['score']                     ?? 0 );
    $answers      = is_array( $data['answers']  ?? null ) ? $data['answers']  : [];
    $insights     = is_array( $data['insights'] ?? null )
                        ? array_map( 'sanitize_text_field', $data['insights'] )
                        : [];
    $ctas         = is_array( $data['ctas']     ?? null ) ? $data['ctas']     : [];
    $pdf_base64   = sanitize_text_field( $data['pdf_base64']   ?? '' );
    $pdf_filename = sanitize_file_name( $data['pdf_filename']  ?? 'Magellan-Readiness-Results.pdf' );

    if ( ! $fullname || ! is_email( $email ) || ! $company ) {
        return new WP_Error( 'missing_fields', 'Required fields are missing.', [ 'status' => 400 ] );
    }

    // 3. Route: CTA popup click vs full quiz submission
    $is_cta     = ! empty( $data['is_cta'] );
    $cta_action = sanitize_text_field( $answers['cta_action'] ?? '' );

    if ( $is_cta ) {
        // Popup CTA click — send contact-details-only email to admin
        $admin_sent = quiz_send_cta_email( $fullname, $email, $phone, $company, $tier, $cta_action );
        $user_sent  = false;

    } else {
        // Full quiz submission — send admin notification + user results
        $goal_answer = quiz_q14_label( sanitize_text_field( $data['goal_answer'] ?? '' ) );

        // (a) Admin gets full-answers notification
        $admin_sent = quiz_send_admin_email( $fullname, $email, $phone, $company, $tier, $score, $answers );

        // (b) User gets results email; admin also receives a copy (see quiz_send_user_email)
        $user_sent  = quiz_send_user_email(
            $fullname, $email, $tier, $tier_body, $goal_line,
            $goal_answer, $insights, $ctas, $pdf_base64, $pdf_filename
        );

        // (c) Save to Flamingo
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
// Writes to Flamingo's flamingo_inbound CPT and flamingo_contact CPT.

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
    if ( ! post_type_exists( 'flamingo_inbound' ) ) {
        quiz_save_as_post( $fullname, $email, $phone, $company, $tier, $score, [] );
        return;
    }

    $labels = quiz_field_labels();
    $skip   = [ 'fullname', 'email', 'phone', 'company', 'score', 'tier' ];

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

    $subject      = "New Assessment — {$fullname} ({$company})";
    $channel_name = 'Outsourcing Scorecard';

    // ── 1. Insert the inbound message ─────────────────────────────────────────
    // '_from' is the combined "Name <email>" string — this is what Flamingo's
    // list-table reads for the From column.
    $post_id = wp_insert_post( [
        'post_type'   => 'flamingo_inbound',
        'post_title'  => $subject,
        'post_status' => 'publish',
        'meta_input'  => [
            '_from'       => "{$fullname} <{$email}>",
            '_from_name'  => $fullname,
            '_from_email' => $email,
            '_subject'    => $subject,
            '_fields'     => $ordered_fields,
            '_remote_ip'  => $_SERVER['REMOTE_ADDR']     ?? '',
            '_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            '_spam'       => false,
        ],
    ] );

    if ( ! $post_id || is_wp_error( $post_id ) ) {
        return;
    }

    // ── 2. Assign the channel taxonomy term ───────────────────────────────────
    // The correct taxonomy name is 'flamingo_inbound_channel'.
    $channel_taxonomy = 'flamingo_inbound_channel';
    if ( taxonomy_exists( $channel_taxonomy ) ) {
        $term = term_exists( $channel_name, $channel_taxonomy );
        if ( ! $term ) {
            $term = wp_insert_term( $channel_name, $channel_taxonomy );
        }
        if ( $term && ! is_wp_error( $term ) ) {
            $term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
            wp_set_post_terms( $post_id, [ $term_id ], $channel_taxonomy );
        }
    }

    // ── 3. Upsert the address-book contact ────────────────────────────────────
    // Flamingo stores contacts in 'flamingo_contact', keyed by '_email' meta.
    if ( post_type_exists( 'flamingo_contact' ) && is_email( $email ) ) {
        $existing = get_posts( [
            'post_type'      => 'flamingo_contact',
            'posts_per_page' => 1,
            'meta_key'       => '_email',
            'meta_value'     => $email,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ] );

        if ( empty( $existing ) ) {
            wp_insert_post( [
                'post_type'   => 'flamingo_contact',
                'post_title'  => $fullname,
                'post_status' => 'publish',
                'meta_input'  => [
                    '_name'  => $fullname,
                    '_email' => $email,
                ],
            ] );
        }
    }
}


// ── Fallback: private WP post ─────────────────────────────────────────────────

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
