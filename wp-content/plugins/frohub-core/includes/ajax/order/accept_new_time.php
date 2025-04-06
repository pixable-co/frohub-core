<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AcceptNewTime {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_accept_new_time', array($self, 'accept_new_time'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_accept_new_time', array($self, 'accept_new_time'));
    }

    /**
     * Handles accepting proposed new time and updates start/end time accordingly.
     */
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

        $proposed_start_time = null;
        $duration_text = null;

        // Find proposed start time
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_product_id() == 28990) continue;

            $proposed_start_time = wc_get_order_item_meta($item_id, 'Proposed Start Date Time', true);
            if ($proposed_start_time) break;
        }

        if (!$proposed_start_time) {
            wp_send_json_error(['message' => 'Error: Proposed start time not found!']);
        }

        // Find duration
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_product_id() == 28990) continue;

            $duration_text = wc_get_order_item_meta($item_id, 'Duration', true);
            if ($duration_text) break;
        }

        if (!$duration_text) {
            wp_send_json_error(['message' => 'Error: Duration not found!']);
        }

        // Extract hours and minutes
        preg_match('/(\d+)\s*hrs?/i', $duration_text, $hours_match);
        preg_match('/(\d+)\s*mins?/i', $duration_text, $minutes_match);

        $hours = isset($hours_match[1]) ? intval($hours_match[1]) : 0;
        $minutes = isset($minutes_match[1]) ? intval($minutes_match[1]) : 0;
        $total_minutes = ($hours * 60) + $minutes;

        // Convert proposed time to DateTime
        $start_datetime = \DateTime::createFromFormat('H:i, d M Y', $proposed_start_time);

        if (!$start_datetime) {
            wp_send_json_error(['message' => 'Error: Invalid proposed start date format!']);
        }

        $end_datetime = clone $start_datetime;
        $end_datetime->modify("+{$total_minutes} minutes");

        $formatted_start_datetime = $start_datetime->format('H:i, d M Y');
        $formatted_end_datetime = $end_datetime->format('H:i, d M Y');

        // Update items and remove proposed times
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_product_id() == 28990) continue;

            wc_update_order_item_meta($item_id, 'Start Date Time', $formatted_start_datetime);
            wc_update_order_item_meta($item_id, 'End Date Time', $formatted_end_datetime);

            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        $order->update_status('processing', 'Appointment time confirmed by customer.');
        $order->save();

        wp_send_json_success([
            'message'     => 'Appointment confirmed successfully! Order is now processing.',
            'start_time'  => $formatted_start_datetime,
            'end_time'    => $formatted_end_datetime
        ]);
    }
}
