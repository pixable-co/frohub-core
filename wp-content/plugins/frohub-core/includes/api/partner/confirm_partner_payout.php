<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class ConfirmPartnerPayout {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/confirm-partner-payout', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_confirm_partner_payout'],
            'permission_callback' => function () {
                return is_user_logged_in(); // Requires authentication
            },
            'args'     => [
                'payout_post_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
                'stripe_payment_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
            ],
        ]);
    }

    /**
     * Handles payout confirmation and updates the payout status.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_confirm_partner_payout(\WP_REST_Request $request) {
        $user_id = get_current_user_id(); // Get authenticated user ID

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Authentication failed.'
            ], 401);
        }

        // Retrieve parameters from request
        $payout_post_id = intval($request->get_param('payout_post_id'));
        $stripe_payment_id = sanitize_text_field($request->get_param('stripe_payment_id'));

        // Verify that the post exists and is of type "payout"
        $payout_post = get_post($payout_post_id);
        if (!$payout_post || get_post_type($payout_post_id) !== 'payout') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid payout_post_id or post type is not "payout".'
            ], 400);
        }

        // 🚀 Update payout status to "Paid"
        update_post_meta($payout_post_id, 'payout_status', 'Paid');

        // 🚀 Save today's date in 'payout_date' (formatted as Y-m-d)
        $today = date('Y-m-d');
        update_post_meta($payout_post_id, 'payout_date', $today);

        // 🚀 Save 'stripe_payment_id' to the post meta
        update_post_meta($payout_post_id, 'stripe_payment_id', $stripe_payment_id);

        return new \WP_REST_Response([
            'success'           => true,
            'message'           => 'Payout successfully marked as Paid!',
            'payout_post_id'    => $payout_post_id,
            'payout_status'     => 'Paid',
            'payout_date'       => $today,
            'stripe_payment_id' => $stripe_payment_id
        ], 200);
    }

}