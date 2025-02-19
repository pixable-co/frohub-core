<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetServiceType {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/get_service_type', array($self, 'get_service_type'));
        add_action('wp_ajax_nopriv_frohub/get_service_type', array($self, 'get_service_type'));
    }

    public function get_service_type() {
        // Verify nonce
        check_ajax_referer('frohub_nonce');

        // Get product ID from request
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$product_id) {
            wp_send_json_error(array(
                'message' => 'Invalid product ID.',
            ));
        }

        // Get product and service types
        $product = wc_get_product($product_id);
        $serviceTypes = get_field('service_types', $product_id);

        // Return response
        wp_send_json_success(array(
            'message' => 'get_service_type AJAX handler executed successfully.',
            'data' => $serviceTypes,
        ));
    }
}
