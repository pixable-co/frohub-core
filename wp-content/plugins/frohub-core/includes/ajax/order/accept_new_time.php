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

            //wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
           // wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        $order->update_status('processing', 'Appointment time confirmed by customer.');
        $order->save();

        //**Trigger Zeptomail Email Template */
        // Extract values for webhook payload
        $frohub_booking_fee = 0;
        $deposit = 0;
        $balance = 0;
        $total_service_fee = 0;
        $service_name = 'N/A (Please contact FroHub)';
        $partner_name = 'N/A (Please contact FroHub)';
        $booking_date_time = $formatted_start_datetime;
        $addons = 'No Add-Ons';
        $service_type = 'N/A (Please contact FroHub)';
        $free_cancellation_deadline_date = (clone $start_datetime)->modify('-7 days')->format('d M Y');
        
        // Loop through order items and extract info
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $item_total = $item->get_total();
        
            if ($product_id == 28990) {
                $frohub_booking_fee = $item_total;
                continue;
            }
        
            // Deposit and calculated values
            $deposit = $item_total;
            $total_service_fee = ($deposit > 0) ? ($deposit / 0.3) : 0;
            $balance = $total_service_fee - $deposit;
        
            // Service name (cut at first dash)
            $raw_service_name = $item->get_name();
            $service_name_parts = explode(' - ', $raw_service_name);
            $service_name = trim($service_name_parts[0]);
        
            // Partner from ACF
            $partner_post = get_field('partner_name', $product_id);
            if ($partner_post && is_object($partner_post)) {
                $partner_name = get_the_title($partner_post->ID);
            }
        
            // Add-ons
            $selected_addons = wc_get_order_item_meta($item->get_id(), 'Selected Add-Ons', true);
            if (!empty($selected_addons)) {
                $addons = is_array($selected_addons) ? implode(', ', $selected_addons) : $selected_addons;
            }
        
            // Service type from variation meta
            $item_meta_data = $item->get_meta_data();
            foreach ($item_meta_data as $meta) {
                if ($meta->key === 'pa_service-type') {
                    $raw_service_type = $item->get_meta('pa_service-type', true);
                    $service_type = ucfirst(strtolower($raw_service_type));
                }
            }
        
            break; // Only process the first main item (non-booking-fee)
        }
        
        // Format currency helper
        $format_currency = function($amount) {
            return '£' . number_format($amount, 2, '.', ',');
        };
        
        // Final payload
        $payload = json_encode([
            'client_email'                    => $order->get_billing_email(),
            'client_first_name'              => $order->get_billing_first_name(),
            'service_name'                   => $service_name,
            'addons'                         => $addons,
            'booking_date_time'              => $booking_date_time,
            'free_cancellation_deadline_date'=> $free_cancellation_deadline_date,
            'service_address'                => implode(', ', array_filter([
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_city(),
                $order->get_billing_postcode()
            ])),
            'partner_name'                   => $partner_name,
            'balance'                        => $format_currency($balance),
        ]);
        
        // Send webhook
        $webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e9a2d77f2205d933fefdfa16e52cdd5f.a6502b087ab6174b0c59f7c3f1c586bd&isdebug=false';
        wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => $payload,
        ]);
        
        wp_send_json_success([
            'message'     => 'Appointment confirmed successfully! Order is now processing.',
            'start_time'  => $formatted_start_datetime,
            'end_time'    => $formatted_end_datetime
        ]);
    }
}
