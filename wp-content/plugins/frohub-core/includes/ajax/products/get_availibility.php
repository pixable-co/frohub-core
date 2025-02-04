<?php
namespace FECore;

use FECore\Helper;

if (!defined('ABSPATH')) {
    exit;
}

class GetAvailibility {

    public static function init() {
        $self = new self();
        add_action('wp_ajax_frohub/get_availibility', array($self, 'get_availibility'));
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

//         if (!isset($_POST['partner_id']) || empty($_POST['partner_id'])) {
//             wp_send_json_error(['message' => 'Partner ID is required.']);
//         }

        $product_id = intval($_POST['product_id']);
        $date = sanitize_text_field($_POST['date']);
//         $partner_id = intval($_POST['partner_id']);
        $partner_id = 1149;

        // ✅ Step 1: Get ACF Availability Data
        $availability = get_field('availability', $product_id);
        if (!$availability) {
            wp_send_json_error(['message' => 'No availability data found.']);
        }

        // ✅ Step 2: Get Booked Slots from WooCommerce Orders
        $orders = Helper::get_orders_by_product_id_and_date($product_id, $date);
        $booked_slots = [];

        foreach ($orders as $order) {
            if (!empty($order['selected_time'])) {
                $booked_slots[] = $order['selected_time'];
            }
        }

        // ✅ Step 3: Call Google Calendar API for Additional Booked Slots
        $google_calendar_booked_slots = $this->get_google_calendar_bookings($partner_id, $date);
        $booked_slots = array_merge($booked_slots, $google_calendar_booked_slots);

        // ✅ Step 4: Filter Available Slots
        $available_slots = array_filter($availability, function ($entry) use ($booked_slots) {
            return !in_array($entry['from'] . ' - ' . $entry['to'], $booked_slots);
        });

        // ✅ Step 5: Return the Response
        wp_send_json_success([
            'availability'   => array_values($available_slots),
            'booked_slots'   => $booked_slots,
        ]);
    }

    /**
     * Fetch Google Calendar booked slots for a specific partner and date
     */
    private function get_google_calendar_bookings($partner_id, $date) {
        $url = "http://localhost:10028/wp-json/fpserver/v1/google-calendar-events?partner_id=" . $partner_id . "&date=" . $date;
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['success']) || !$data['success']) {
            return [];
        }

        $booked_slots = [];

        foreach ($data['events'] as $event) {
            if (!empty($event['start']) && !empty($event['end'])) {
                // Convert Google Calendar timestamps to match your slot format (e.g., "10:00 AM - 11:00 AM")
                $start_time = date('H:i', strtotime($event['start']));
                $end_time = date('H:i', strtotime($event['end']));
                $booked_slots[] = "$start_time - $end_time";
            }
        }

        return $booked_slots;
    }
}
