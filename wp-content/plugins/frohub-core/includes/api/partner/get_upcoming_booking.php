<?php
namespace FECore;

use FECore\Helper;

if (!defined('ABSPATH')) {
    exit;
}

class GetUpcomingBooking {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-upcoming-booking', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'partner_id' => array(
                    'required'          => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
//                 'date' => array(
//                     'required'          => false,
//                     'validate_callback' => function ($param, $request, $key) {
//                         return strtotime($param) !== false;
//                     }
//                 )
            )
        ));
    }

    /**
     * Handles the API request to get upcoming bookings by partner ID.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $partner_id = $request->get_param('partner_id');
//         $date = $request->get_param('date') ? $request->get_param('date') : date('Y-m-d');

        if (!$partner_id) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing partner_id parameter'
            ), 400);
        }

        // Fetch upcoming orders
        $orders = Helper::get_next_upcoming_order_by_partner($partner_id);

        return new \WP_REST_Response(array(
            'success' => true,
            'data'    => $orders
        ), 200);
    }
}
