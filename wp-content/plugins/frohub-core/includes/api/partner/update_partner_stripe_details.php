<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class UpdatePartnerStripeDetails {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API route.
     */
    public function register_rest_routes() { 
        register_rest_route('frohub/v1', '/update-partner-stripe-details', [
            'methods'  => 'POST',
            'callback' => [$this, 'update_partner_stripe_details'],
            'permission_callback' => '__return_true',
            'args'     => [
                'partner_id' => [
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    }
                ],
                'stripe_account_id' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'access_token' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'refresh_token' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
                'publishable_key' => [
                    'required' => false,
                    'validate_callback' => function ($param) {
                        return is_string($param) && !empty($param);
                    }
                ],
            ],
        ]);
    }

    /**
     * Handles updating partner's Stripe details.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_partner_stripe_details(\WP_REST_Request $request) {
        $partner_id = intval($request->get_param('partner_id'));
        $stripe_account_id = sanitize_text_field($request->get_param('stripe_account_id'));
        $access_token = sanitize_text_field($request->get_param('access_token'));
        $refresh_token = sanitize_text_field($request->get_param('refresh_token'));
        $publishable_key = sanitize_text_field($request->get_param('publishable_key'));

        // Check if the partner post exists
        if (!get_post($partner_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Partner post not found.',
            ], 404);
        }

        // Prepare data for updating custom fields
        $meta_fields = [
            'stripe_account_id'   => $stripe_account_id,
            'refresh_token'       => $refresh_token,
            'access_token'        => $access_token,
            'publishable_key'     => $publishable_key
        ];

        // Track updated fields
        $updated_fields = [];

        foreach ($meta_fields as $key => $value) {
            if (!empty($value)) {
                update_post_meta($partner_id, $key, $value);
                $updated_fields[$key] = $value;
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Partner Stripe details updated successfully.',
            'post_id' => $partner_id,
            'updated_fields' => $updated_fields
        ], 200);
    }
}

