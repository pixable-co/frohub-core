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
                $webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.701f1885176381453a6604fbea45ecbf.4f5f6b8dbcf659a0ed51e76e6ab66598&isdebug=false';
        
                // Send it
                wp_remote_post($webhook_url, [
                    'method'    => 'POST',
                    'headers'   => [
                        'Content-Type' => 'application/json',
                    ],
                    'body'      => $payload,
                ]);

                

        wp_send_json_success([
            'message' => 'Order has been cancelled. No refund issued as per the late cancellation policy.'
        ]);
    }
}
