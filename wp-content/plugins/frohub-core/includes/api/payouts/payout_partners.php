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
            'permission_callback' => function () {
                return is_user_logged_in(); // Requires authentication
            },  
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
		$today = date('Y-m-d');

        // Query 'payout' posts where:
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
					'value'   => $today, // formatted as Y-m-d
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

                $payout_amount = intval((float) $payout_amount * 100);
                $description = "FroHub - " . $order_title;

                $destination = null;
                $stripe_connect = false;

                if ($partner_post && is_object($partner_post)) {
                    $partner_id = $partner_post->ID;

                    $stripe_account_id = get_field('stripe_account_id', $partner_id);
                    $access_token      = get_field('access_token', $partner_id);
                    $publishable_key   = get_field('publishable_key', $partner_id);
                    $refresh_token     = get_field('refresh_token', $partner_id);

                    $destination = $stripe_account_id;

                    if ($stripe_account_id && $access_token && $publishable_key && $refresh_token) {
                        $stripe_connect = "Yes";
                    }
                    else {
                        $stripe_connect = "No";
                    }
                }

                $payout_data[] = [
                    'post_id' => $post_id,
                    'stripe_connect' => $stripe_connect,
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
            'success' => true,
            'message' => 'Authenticated request successful!',
            'payouts' => $payout_data
        ], 200);
    }
}
