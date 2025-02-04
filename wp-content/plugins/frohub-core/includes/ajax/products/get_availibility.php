<?php
namespace FECore;
use FECore\Helper;

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

        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error(['message' => 'Product ID is required.']);
        }

        if (!isset($_POST['date']) || empty($_POST['date'])) {
            wp_send_json_error(['message' => 'Date is required.']);
        }

        $product_id = intval($_POST['product_id']);
        $date = sanitize_text_field($_POST['date']);

        // Get ACF Repeater Field (availability)
        $availability = get_field('availability', $product_id);
        if (!$availability) {
            wp_send_json_error(['message' => 'No availability data found.']);
        }

        $orders = Helper::get_orders_by_product_id_and_date($product_id, $date);
        $booked_slots = [];


        foreach ($orders as $order) {
            if (!empty($order['selected_time'])) {
                $booked_slots[] = $order['selected_time'];
            }
        }


        $available_slots = array_filter($availability, function ($entry) use ($booked_slots) {
            return !in_array($entry['from'] . ' - ' . $entry['to'], $booked_slots);
        });

        wp_send_json_success([
            'availability' => array_values($available_slots),
            'booked_slots'    => $booked_slots,
        ]);
    }
}