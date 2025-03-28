<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CancelOrder {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_cancel_order', array($self, 'cancel_order'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_cancel_order', array($self, 'cancel_order'));
    }

    /**
     * Handles AJAX cancellation of an order (simple cancel with meta cleanup).
     */
    public function cancel_order() {
        check_ajax_referer('ajax_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Error: Missing order ID!']);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Error: Order not found!']);
        }

        // Remove the proposed time meta fields
        foreach ($order->get_items() as $item_id => $item) {
            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        // Cancel the order
        $order->update_status('cancelled', 'Order has been cancelled.');
        $order->save();

        wp_send_json_success(['message' => 'Order has been cancelled.']);
    }
}
