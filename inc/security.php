<?php
/**
 * Sikkerhetsfunksjoner for Nattevakten
 * Brukes til å validere brukere, input og AJAX- eller REST-kall
 */

// Sjekk om brukeren har administrator-rettigheter
function nattevakten_check_permissions() {
    return current_user_can('manage_options');
}

// Valider og rens input fra POST/GET
function nattevakten_sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_text_field', $input);
    }
    return sanitize_text_field($input);
}

// Sikkerhetsjekk for AJAX (bruk i hver handler)
function nattevakten_verify_ajax_request($nonce_action = 'nattevakt_nonce', $nonce_name = 'nonce') {
    if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
        wp_send_json_error(['message' => 'Ugyldig sikkerhetsnøkkel (nonce)']);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Ingen tilgang']);
    }
}

// Beskytt REST-endepunkt – bruk som permission_callback
function nattevakten_rest_permission_check() {
    return current_user_can('read'); // Kan endres til 'manage_options' for admin-only
}

/**
 * Capability double-check system
 */
function nattevakten_verify_admin_capability($required_capability = 'manage_options') {
    if (!current_user_can($required_capability)) {
        wp_die(
            sprintf(
                __('Du har ikke nødvendige rettigheter (%s) for å utføre denne handlingen.', 'nattevakten'),
                $required_capability
            ),
            __('Ingen tilgang', 'nattevakten'),
            ['response' => 403]
        );
    }
    
    // Additional check for network sites
    if (is_multisite() && !is_user_member_of_blog()) {
        wp_die(
            __('Du er ikke medlem av denne siden.', 'nattevakten'),
            __('Ingen tilgang', 'nattevakten'),
            ['response' => 403]
        );
    }
    
    return true;
}

// Wrap all admin functions with capability verification
add_action('admin_init', function() {
    if (isset($_GET['page']) && strpos($_GET['page'], 'nattevakten') === 0) {
        nattevakten_verify_admin_capability();
    }
});

/**
 * Security headers and hardening
 */
add_action('send_headers', 'nattevakten_security_headers');
function nattevakten_security_headers() {
    // Only add headers on our admin pages
    if (!is_admin() || !isset($_GET['page']) || strpos($_GET['page'], 'nattevakten') !== 0) {
        return;
    }
    
    // Content Security Policy for admin pages
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://unpkg.com; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; connect-src \'self\' https://api.openai.com;');
    
    // Additional security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
?>