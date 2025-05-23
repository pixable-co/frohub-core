<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PayoutPartners {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/payout-partners', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_payout_partners_endpoint'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles retrieving queued payouts for partners.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_payout_partners_endpoint(\WP_REST_Request $request) {

        // Get today's date in Ymd format
        $today = date('Ymd');

        // 🚀 Query 'payout' posts where:
        // - 'payout_status' is 'Queued'
        // - 'scheduled_date' is today
        $query_args = array(
            'post_type'      => 'payout',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => 'payout_status',
                    'value'   => 'Queued',
                    'compare' => '='
                ),
                array(
                    'key'     => 'scheduled_date',
                    'value'   => $today,
                    'compare' => '='
                )
            )
        );

        $payout_query = new \WP_Query($query_args);
        $payout_data = [];

        if ($payout_query->have_posts()) {
            while ($payout_query->have_posts()) {
                $payout_query->the_post();
                $post_id = get_the_ID();
                $order_title = get_the_title();
                $payout_amount = get_field('payout_amount', $post_id);
                $partner_post = get_field('partner_name', $post_id); // ACF post object field

                // Ensure payout amount is retrieved, multiplied by 100, and cast to an integer
                $payout_amount = intval((float) $payout_amount * 100);

                // Prepend "FroHub - " to order reference
                $description = "FroHub - " . $order_title;

                // Default stripe account ID to null
                $destination = null;

                // Fetch stripe_account_id from related partner post
                if ($partner_post && is_object($partner_post)) {
                    $partner_id = $partner_post->ID;
                    $destination = get_field('stripe_account_id', $partner_id);
                }

                $payout_data[] = [
                    'post_id' => $post_id,
                    'stripe_connect_payload' => [
                        'description' => $description,
                        'amount'      => $payout_amount,
                        'destination' => $destination
                    ]
                ];
            }
            wp_reset_postdata();
        }

        return new \WP_REST_Response([
            'success'   => true,
            'message'   => 'Authenticated request successful!',
            'payouts'   => $payout_data
        ], 200);
    }
}


