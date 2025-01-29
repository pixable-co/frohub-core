<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetAvailibility {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/get_availibility', array($self, 'get_availibility'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_frohub/get_availibility', array($self, 'get_availibility'));
    }

    public function get_availibility() {
        check_ajax_referer('frohub_nonce');

        // Check if product_id is provided
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error(array('message' => 'Product ID is required.'));
        }

        $product_id = intval($_POST['product_id']);

        // Get ACF Repeater Field (availability)
        $availability = get_field('availability', $product_id);

        if (!$availability) {
            wp_send_json_error(array('message' => 'No availability data found.'));
        }

        // Format the repeater field data
        $availability_data = [];

        foreach ($availability as $entry) {
                $availability_data[] = [
                    'day'          => $entry['day'] ?? '',  // Retrieve 'day'
                    'from'         => $entry['from'] ?? '', // Retrieve 'from'
                    'to'           => $entry['to'] ?? '',   // Retrieve 'to'
                    'extra_charge' => $entry['extra_charge'] ?? 0, // Retrieve 'extra_charge'
                ];
        }

        wp_send_json_success(array(
            'availability' => $availability_data,
        ));
    }
}