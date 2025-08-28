<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CheckActiveStatus {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/check_active_status', array($self, 'check_active_status'));
        // AJAX for non-logged-in users (if you want to allow checking without login)
        add_action('wp_ajax_nopriv_frohub/check_active_status', array($self, 'check_active_status'));
    }

    public function check_active_status() {
        // Verify nonce for security
        check_ajax_referer('frohub_nonce');

        // Get email from POST data
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email)) {
            wp_send_json_error(array(
                'message' => 'Email is required'
            ));
            return;
        }

        // Validate email format
        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => 'Invalid email format'
            ));
            return;
        }

        // Check if user exists by email
        $user = get_user_by('email', $email);

        if (!$user) {
            wp_send_json_error(array(
                'message' => 'User not found'
            ));
            return;
        }

        // Check if user is active using ACF field
        $is_active = get_field('is_active', 'user_' . $user->ID);

        // Handle case where ACF field might not exist or be null/false
        $is_active = !empty($is_active);

        // Return the status
        wp_send_json_success(array(
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'is_active' => $is_active,
            'status' => $is_active ? 'active' : 'inactive',
            'message' => 'User status checked successfully'
        ));
    }
}