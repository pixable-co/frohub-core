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

        // Pull client data
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();

        foreach ($order->get_items() as $item) {

            $product_id = $item->get_product_id();
            $item_total = $item->get_total();

            if ($product_id == 28990) {

            } else {

                // Get the service name and strip after ' - '
                $raw_service_name = $item->get_name();
                $service_name_parts = explode(' - ', $raw_service_name);
                $service_name = trim($service_name_parts[0]);

                $partner_post = get_field('partner_name', $product_id);
                if ($partner_post && is_object($partner_post)) {
                    $partner_name = get_the_title($partner_post->ID);
                }
                $selected_date_time = wc_get_order_item_meta($item->get_id(), 'Start Date Time', true);
            }
        }

         // Build payload
        $payload = json_encode([
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'booking_date_time' => $selected_date_time,
        ]);

        // Webhook URL
        $webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.c4690a5e3f8614af33586949f0a712a6.727222d689cd2a034af750b2ac127495&isdebug=false';

        // Send it
        wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
            'body'      => $payload,
        ]);

        wp_send_json_success([
            'message' => 'Order cancelled successfully. Refund issued except for product ID 28990.'
        ]);
    }
}
