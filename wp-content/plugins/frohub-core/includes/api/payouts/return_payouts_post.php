<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReturnPayoutsPost {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/payouts', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_payouts_with_acf'),
            'permission_callback' => '__return_true',
        ));
    }
    /**
     * Fetches payouts with ACF fields.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_payouts_with_acf(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');

        if (!$partner_id) {
            return new \WP_Error('missing_param', 'partner_id is required', ['status' => 400]);
        }

        $args = [
            'post_type'      => 'payout',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'payout_status',
                    'value'   => ['Queued', 'Paid', 'Disputed'],
                    'compare' => 'IN'
                ]
            ]
        ];

        $payouts = get_posts($args);
        $response = [];

        foreach ($payouts as $payout) {
            $partner = get_field('partner_name', $payout->ID);
            $partner_post_id = $partner ? $partner->ID : null;

            if ($partner_post_id != $partner_id) {
                continue;
            }

            $order = get_field('order', $payout->ID);
            $order_id = $order->ID ?? $payout->post_title; // Use post title if order ID is not set

            $deposit_total = 0;
            $total_due = 0;
            $total_service_price = (float) $deposit_total + (float) $total_due;

            $response[] = [
                'payout_id'            => $payout->ID,
                'partner_id'           => $partner_post_id,
                'partner_name'         => get_the_title($partner_post_id),
                'order_id'             => $order_id,
                'appointment_date_time'=> get_field('appointment_date_time', $payout->ID),
                'deposit'              => get_field('deposit', $payout->ID),
                'frohub_commission'    => get_field('frohub_commission', $payout->ID),
                'stripe_fee'           => get_field('stripe_fee', $payout->ID),
                'payout_amount'        => get_field('payout_amount', $payout->ID),
                'scheduled_date'       => get_field('scheduled_date', $payout->ID),
                'payout_date'          => get_field('payout_date', $payout->ID),
                'payout_status'        => get_field('payout_status', $payout->ID),
                'stripe_payment_id'    => get_field('stripe_payment_id', $payout->ID),
            ];
        }

        return rest_ensure_response($response);
    }
}

