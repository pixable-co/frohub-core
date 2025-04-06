<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EarlyCancelOrder {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_early_cancel_order', array($self, 'early_cancel_order'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_early_cancel_order', array($self, 'early_cancel_order'));
    }

    /**
     * Handles early cancellation with partial refund.
     */
    public function early_cancel_order() {
        check_ajax_referer('ajax_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error(['message' => 'Order ID is missing.']);
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order.']);
        }

        $line_items = $order->get_items();
        $refund_total = 0;
        $refund_items = [];

        foreach ($line_items as $item_id => $item) {
            $product_id = $item->get_product_id();
            $item_total = $item->get_total();

            // Exclude product ID 28990 from the refund
            if ($product_id != 28990) {
                $refund_items[$item_id] = [
                    'qty'          => $item->get_quantity(),
                    'refund_total' => $item_total
                ];
                $refund_total += $item_total;
            }
        }

        if ($refund_total > 0) {
            $refund = wc_create_refund([
                'amount'         => $refund_total,
                'reason'         => 'Early Cancellation Refund (excluding non-refundable item)',
                'order_id'       => $order_id,
                'line_items'     => $refund_items,
                'refund_payment' => true,
                'restock_items'  => true,
            ]);

            if (is_wp_error($refund)) {
                wp_send_json_error([
                    'message' => 'Refund failed: ' . $refund->get_error_message()
                ]);
            }
        }

        $order->update_status('cancelled', 'Order cancelled with partial refund');
        update_field('cancellation_status', 'Early Cancellation', $order_id);

        wp_send_json_success([
            'message' => 'Order cancelled successfully. Refund issued except for product ID 28990.'
        ]);
    }
}
