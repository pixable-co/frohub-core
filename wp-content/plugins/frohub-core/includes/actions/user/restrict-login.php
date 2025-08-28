<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestrictLogin {

    public static function init() {
        $self = new self();
        add_action( 'user_register', array( $self, 'set_user_inactive' ) );
        add_action( 'init', array( $self, 'handle_verification' ) );
    }

    /**
     * Mark new users as inactive on registration and send verification email.
     */
    public function set_user_inactive( $user_id ) {
        // Set user as inactive
        update_field( 'is_active', 0, 'user_' . $user_id );

        // Generate verification token
        $verification_token = wp_generate_password( 32, false );
        update_user_meta( $user_id, 'verification_token', $verification_token );

        // Send verification webhook
        $this->send_verification_webhook( $user_id, $verification_token );
    }

    /**
     * Send verification webhook to webhook.site
     */
    private function send_verification_webhook( $user_id, $verification_token ) {
        $user = get_user_by( 'ID', $user_id );

        if ( ! $user ) {
            return;
        }

        // Get user's first name (from user meta or display name)
        $first_name = get_user_meta( $user_id, 'first_name', true );
        if ( empty( $first_name ) ) {
            $first_name = $user->display_name;
        }

        // Create verification link
        $verification_link = add_query_arg( array(
            'action' => 'verify_account',
            'token' => $verification_token,
            'user_id' => $user_id
        ), home_url() );

        // Prepare payload
        $payload = array(
            'email' => $user->user_email,
            'first_name' => $first_name,
            'verificationLink' => $verification_link
        );

        // Send to webhook.site
        $webhook_url = 'https://webhook.site/16b98ca7-61d5-47b1-82cf-8d2cace8aa60';

        $response = wp_remote_post( $webhook_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( $payload ),
            'timeout' => 30,
        ) );

        // Log any errors (optional)
        if ( is_wp_error( $response ) ) {
            error_log( 'Verification webhook failed: ' . $response->get_error_message() );
        }
    }

    /**
     * Handle account verification when user clicks the link
     */
    public function handle_verification() {
        // Check if this is a verification request
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'verify_account' ) {
            return;
        }

        // Get parameters
        $token = sanitize_text_field( $_GET['token'] ?? '' );
        $user_id = intval( $_GET['user_id'] ?? 0 );

        // Validate parameters
        if ( empty( $token ) || empty( $user_id ) ) {
            wp_die( 'Invalid verification link.', 'Verification Error', array( 'response' => 400 ) );
        }

        // Get stored token
        $stored_token = get_user_meta( $user_id, 'verification_token', true );

        // Verify token matches
        if ( empty( $stored_token ) || ! hash_equals( $stored_token, $token ) ) {
            wp_die( 'Invalid or expired verification token.', 'Verification Error', array( 'response' => 400 ) );
        }

        // Check if user exists
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            wp_die( 'User not found.', 'Verification Error', array( 'response' => 404 ) );
        }

        // Check if already active
        $is_active = get_field( 'is_active', 'user_' . $user_id );
        if ( $is_active ) {
            wp_die( 'Account is already verified.', 'Already Verified', array( 'response' => 200 ) );
        }

        // Activate the account
        update_field( 'is_active', 1, 'user_' . $user_id );

        // Clean up verification token
        delete_user_meta( $user_id, 'verification_token' );

        // Redirect or show success message
        $this->show_verification_success( $user );
    }

    /**
     * Show verification success page
     */
    private function show_verification_success( $user ) {
        // You can customize this success page as needed
        wp_die(
            sprintf(
                '<h1>Account Verified!</h1><p>Welcome %s! Your account has been successfully verified and activated.</p><p><a href="%s">Continue to login</a></p>',
                esc_html( $user->display_name ),
                wp_login_url()
            ),
            'Account Verified',
            array( 'response' => 200 )
        );
    }

    /**
     * Optional: Add method to check if user is active (for use in login restrictions)
     */
    public static function is_user_active( $user_id ) {
        return (bool) get_field( 'is_active', 'user_' . $user_id );
    }

    /**
     * Optional: Resend verification email
     */
    public function resend_verification( $user_id ) {
        $user = get_user_by( 'ID', $user_id );

        if ( ! $user || $this->is_user_active( $user_id ) ) {
            return false;
        }

        // Generate new token
        $verification_token = wp_generate_password( 32, false );
        update_user_meta( $user_id, 'verification_token', $verification_token );

        // Send webhook again
        $this->send_verification_webhook( $user_id, $verification_token );

        return true;
    }
}