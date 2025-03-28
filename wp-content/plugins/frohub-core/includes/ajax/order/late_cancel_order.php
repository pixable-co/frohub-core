<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LateCancelOrder {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_late_cancel_order', array($self, 'late_cancel_order'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_late_cancel_order', array($self, 'late_cancel_order'));
    }

    /**
     * Handles late cancellation of an order (no refund).
     */
    public function late_cancel_order() {
        check_ajax_referer('ajax_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error(['message' => 'Order ID is missing.']);
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order.']);
        }

        // Cancel order without refund
        $order->update_status('cancelled', 'Order cancelled without refund due to late cancellation policy.');

        // Update ACF field
        update_field('cancellation_status', 'Late Cancellation', $order_id);

        wp_send_json_success([
            'message' => 'Order has been cancelled. No refund issued as per the late cancellation policy.'
        ]);
    }
}
