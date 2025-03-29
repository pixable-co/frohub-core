<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnersMyPendingOrdersCount {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/partners-my-pending-orders-count', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args'                => array(
                'partner_id' => array(
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
    }

    /**
     * Handles the API request to count "on-hold" orders for a partner.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Authentication failed.'
            ], 401);
        }

        $partner_id = intval($request->get_param('partner_id'));

        if (!$partner_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid partner ID.'
            ], 400);
        }

        $query = new \WP_Query(array(
            'post_type'      => 'shop_order',
            'post_status'    => 'wc-on-hold',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'partner_name', // ACF field
                    'value'   => $partner_id,
                    'compare' => '='
                ),
            ),
        ));

        $order_count = $query->found_posts;

        return new \WP_REST_Response([
            'success'     => true,
            'message'     => "Found $order_count 'On Hold' orders for Partner ID $partner_id",
            'order_count' => $order_count
        ], 200);
    }
}
