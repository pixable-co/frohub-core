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

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID.']);
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(['message' => 'Product is not a variable product.']);
        }

        $serviceTypes = [];
        $variation_ids = $product->get_children();

        foreach ($variation_ids as $variation_id) {
            $variation_product = wc_get_product($variation_id);
            if ($variation_product->get_status() !== 'publish') {
                continue;
            }

            
            $service_type = $variation_product->get_attribute('pa_service-type');

            if (!empty($service_type) && !in_array($service_type, $serviceTypes)) {
                $serviceTypes[] = $service_type;
            }
        }

        // Return response
        wp_send_json_success([
            'message' => 'get_service_type AJAX handler executed successfully.',
            'data' => $serviceTypes,
        ]);
    }

}
