<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StripeAccount {

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public static function register_rest_routes() {
        register_rest_route('frohub/v1', '/stripe-account/', array(
            'methods'  => 'GET',
            'callback' => array(__CLASS__, 'get_partner_stripe_account'),
            'permission_callback' => '__return_true', // Change this for security if needed
            'args'     => array(
                'partner_id' => array(
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
    }

    /**
     * Retrieves Stripe account ID from ACF for a given partner post.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_partner_stripe_account(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        // Check if the post exists and is a valid "partner" post type
        if (!get_post($partner_id) || get_post_type($partner_id) !== 'partner') {
            return new \WP_REST_Response(['error' => 'Invalid partner ID'], 400);
        }

        // Get the Stripe Account ID from ACF field
        $stripe_account_id = get_field('stripe_account_id', $partner_id);

        if (!$stripe_account_id) {
            return new \WP_REST_Response(['error' => 'Stripe account ID not found'], 404);
        }

        return new \WP_REST_Response(['stripe_account_id' => $stripe_account_id], 200);
    }
}
