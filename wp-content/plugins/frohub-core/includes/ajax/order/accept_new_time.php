<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AcceptNewTime {

    public static function init() {
        $self = new self();
        add_action('wp_ajax_accept_new_time', array($self, 'accept_new_time'));
        add_action('wp_ajax_nopriv_accept_new_time', array($self, 'accept_new_time'));
    }

    public function accept_new_time() {
        check_ajax_referer('ajax_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(['message' => 'Error: Missing order ID!']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Error: Order not found!']);
        }

        $customer_shipping_address = $order->get_formatted_shipping_address();

        $proposed_start_time = null;
        $duration_text = null;

        // Get proposed time and duration
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_product_id() == 28990) continue;

            if (!$proposed_start_time) {
                $proposed_start_time = wc_get_order_item_meta($item_id, 'Proposed Start Date Time', true);
            }
            if (!$duration_text) {
                $duration_text = wc_get_order_item_meta($item_id, 'Duration', true);
            }

            if ($proposed_start_time && $duration_text) break;
        }

        if (!$proposed_start_time) {
            wp_send_json_error(['message' => 'Error: Proposed start time not found!']);
        }

        if (!$duration_text) {
            wp_send_json_error(['message' => 'Error: Duration not found!']);
        }

        preg_match('/(\d+)\s*hrs?/i', $duration_text, $hours_match);
        preg_match('/(\d+)\s*mins?/i', $minutes_match = '', $minutes_match);
        $hours = isset($hours_match[1]) ? intval($hours_match[1]) : 0;
        $minutes = isset($minutes_match[1]) ? intval($minutes_match[1]) : 0;
        $total_minutes = ($hours * 60) + $minutes;

        $start_datetime = \DateTime::createFromFormat('H:i, d M Y', $proposed_start_time);
        if (!$start_datetime) {
            wp_send_json_error(['message' => 'Error: Invalid proposed start date format!']);
        }

        $end_datetime = clone $start_datetime;
        $end_datetime->modify("+{$total_minutes} minutes");

        $formatted_start_datetime = $start_datetime->format('H:i, d M Y');
        $formatted_end_datetime = $end_datetime->format('H:i, d M Y');

        // Update order items
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_product_id() == 28990) continue;

            wc_update_order_item_meta($item_id, 'Start Date Time', $formatted_start_datetime);
            wc_update_order_item_meta($item_id, 'End Date Time', $formatted_end_datetime);

            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }
        
        $webhook_customer = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e9a2d77f2205d933fefdfa16e52cdd5f.a6502b087ab6174b0c59f7c3f1c586bd&isdebug=false';
        //$webhook_customer = 'https://webhook.site/46259b7b-17ea-4186-a9a8-1c976d72379c';
        wp_remote_post($webhook_customer, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode(sendPayloadToZohoFlowPayload($order_id)),
        ]);

        $webhook_partner = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.65e0e4740df7fca9b365732f476c2f0e.5dfae26de7e36dc015ff1c4819f8188e&isdebug=false';

        wp_remote_post($webhook_partner, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode(sendPayloadToZohoFlowPayload($order_id)),
        ]);

        wp_send_json_success([
            'message'     => 'Appointment confirmed successfully! Order is now processing.',
            'start_time'  => $formatted_start_datetime,
            'end_time'    => $formatted_end_datetime
        ]);

        $order->update_status('processing', 'Appointment time confirmed by customer.');
        $order->save();
    }
}
