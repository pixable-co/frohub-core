<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DeclineNewProposedTime {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_decline_new_proposed_time', array($self, 'decline_new_proposed_time'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_decline_new_proposed_time', array($self, 'decline_new_proposed_time'));
    }

    /**
     * Handles the decline new proposed time AJAX request.
     */
    public function decline_new_proposed_time() {
        check_ajax_referer('ajax_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Error: Missing order ID!']);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Error: Order not found!']);
        }

        // Remove proposed time meta from each item
        foreach ($order->get_items() as $item_id => $item) {
            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        // Cancel the order
        $order->update_status('cancelled', 'Order has been cancelled. Proposed Time declined by customer.');
        $order->save();

        // Update ACF cancellation status
        update_field('cancellation_status', 'Declined by Client', $order_id);

        wp_send_json_success(['message' => 'Order has been cancelled.']);
    }
}
