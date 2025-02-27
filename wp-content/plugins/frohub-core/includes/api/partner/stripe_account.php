<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class StripeAccount {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        // POST: Handle Stripe account updates or creation
        register_rest_route('frohub/v1', '/stripe-account', [
            'methods'  => 'POST',
            'callback' => [$this, 'update_stripe_account'],
            'permission_callback' => function () {
                return current_user_can('edit_posts'); // Restrict access to users with edit permissions
            },
            'args'     => [
                'partner_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
                'stripe_account_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
            ],
        ]);

        // GET: Retrieve Stripe account details
        register_rest_route('frohub/v1', '/stripe-account', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_partner_stripe_account'],
            'permission_callback' => function () {
                return current_user_can('edit_posts'); // Restrict access to authenticated users
            },
            'args'     => [
                'partner_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
            ],
        ]);
    }

    /**
     * Handles updating Stripe account details.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_stripe_account(\WP_REST_Request $request) {
        $partner_id = intval($request->get_param('partner_id'));
        $stripe_account_id = sanitize_text_field($request->get_param('stripe_account_id'));

        // Check if the partner post exists
        if (!get_post($partner_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Partner post not found.',
            ], 404);
        }

        // Update the Stripe Account ID
        update_post_meta($partner_id, 'stripe_account_id', $stripe_account_id);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Stripe account ID updated successfully.',
            'partner_id' => $partner_id,
            'stripe_account_id' => $stripe_account_id
        ], 200);
    }

    /**
     * Retrieves Stripe account ID for a given partner post.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_partner_stripe_account(\WP_REST_Request $request) {
        $partner_id = intval($request->get_param('partner_id'));

        // Check if the post exists and is a valid "partner" post type
        if (!get_post($partner_id) || get_post_type($partner_id) !== 'partner') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid partner ID.',
            ], 400);
        }

        // Get the Stripe Account ID
        $stripe_account_id = get_post_meta($partner_id, 'stripe_account_id', true);

        if (!$stripe_account_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Stripe account ID not found.',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'stripe_account_id' => $stripe_account_id
        ], 200);
    }
}
