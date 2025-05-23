<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SetStripeFailedPayout {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/set-stripe-failed-payout', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $payout_post_id = $request->get_param('payout_post_id');

        if (!$payout_post_id || !get_post($payout_post_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid payout_post_id provided.'
            ], 400);
        }

        // Get the related partner post from ACF field 'partner_name'
        $partner_post = get_field('partner_name', $payout_post_id);

        if (!$partner_post || !is_object($partner_post)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No valid partner found for this payout post.'
            ], 404);
        }

        // Update the partner's payout_status ACF field
        update_field('payout_status', 'Stripe Not Connected', $partner_post->ID);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Partner payout_status updated to "Stripe Not Connected".',
            'partner_post_id' => $partner_post->ID
        ], 200);
    }
}
