<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductServiceType {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/product-service-type', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_product_service_types'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_product_service_types(\WP_REST_Request $request) {
        $product_id = $request->get_param( 'product_id' );
        $product = wc_get_product( $product_id );

        $serviceTypes = get_field('service_types', $product_id);

    
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'product-service-type API endpoint reached',
            'data' => $serviceTypes,
        ), 200);
    
    }
}