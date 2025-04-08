<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetPartnerName {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-partner-name', array(
            'methods'             => 'GET',
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
        $partner_id = $request->get_param('partner_id');
    
        if (empty($partner_id) || !is_numeric($partner_id)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing or invalid partner_id parameter',
            ), 400);
        }
    
        $partner_title = get_the_title($partner_id);
    
        if (empty($partner_title)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'No partner found for this ID',
            ), 404);
        }
    
        return new \WP_REST_Response(array(
            'success' => true,
            'partner_id' => $partner_id,
            'partner_name' => $partner_title,
        ), 200);
    }
    
}
