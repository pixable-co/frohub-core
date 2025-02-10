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

            $product_id = intval($_POST['product_id']);
            $date = sanitize_text_field($_POST['date']);
            $partner_id = get_field('partner_id', $product_id);

            // Fetch availability from ACF
            $availability = get_field('availability', $product_id);
            if (!$availability) {
                wp_send_json_error(['message' => 'No availability data found.']);
            }

            // Fetch product duration from ACF and convert to minutes
            $duration_hours = get_field('duration_hours', $product_id);
            $duration_minutes = get_field('duration_minutes', $product_id);
            $product_duration_minutes = ($duration_hours * 60) + $duration_minutes;

            // Check if an addon ID is provided
            $addon_id = null;  // Replace with isset($_POST['addons_id']) ? intval($_POST['addons_id']) : null;
            $addon_duration_minutes = 0;

            $addons = get_field('add_ons', $partner_id);
            if ($addons && is_array($addons)) {
                foreach ($addons as $addon) {
                    if (intval($addon['add_on']->term_id) === $addon_id) {
                        $addon_duration_minutes = isset($addon['duration_minutes']) ? intval($addon['duration_minutes']) : 0;
                        break;
                    }
                }
            }

            // Get already booked slots from WooCommerce orders
            $orders = Helper::get_orders_by_product_id_and_date($product_id, $date);
            $booked_slots = [];

            foreach ($orders as $order) {
                if (!empty($order['selected_time'])) {
                    $booked_slots[] = $order['selected_time'];
                }
            }

            // Include Google Calendar Bookings
            $google_calendar_booked_slots = $this->get_google_calendar_bookings($partner_id, $date);
            $booked_slots = array_merge($booked_slots, $google_calendar_booked_slots);

            $final_slots = [];

            // Total duration includes product and add-on duration
            $total_duration_minutes = $product_duration_minutes + $addon_duration_minutes;

            // Loop through availability to split into time slots
            foreach ($availability as $slot) {
                $day = $slot['day'];
                $extra_charge = !empty($slot['extra_charge']) ? $slot['extra_charge'] : 0;
                $start_time = strtotime($slot['from']);
                $end_time = strtotime($slot['to']);

                // Generate time slots
                while (($start_time + ($total_duration_minutes * 60)) <= $end_time) {
                    $slot_from = date('H:i', $start_time);
                    $slot_to = date('H:i', $start_time + ($total_duration_minutes * 60));
                    $time_range = "$slot_from - $slot_to";

                    // Check if the slot is booked
                    $is_booked = in_array($time_range, $booked_slots);

                    // Skip adding booked slots to the availability
                    if ($is_booked) {
                        $start_time = strtotime($slot_to);  // Move start to the next available slot (current end time)
                        continue;
                    }

                    // Add available slot with details
                    $final_slots[] = [
                        'day'                     => $day,
                        'from'                    => $slot_from,
                        'to'                      => $slot_to,
                        'time_range'              => $time_range,
                        'product_duration_minutes'=> $product_duration_minutes,
                        'addon_duration_minutes'  => $addon_duration_minutes,
                        'total_duration_minutes'  => $total_duration_minutes,
                        'extra_charge'            => $extra_charge,
                        'is_booked'               => $is_booked
                    ];

                    // Move start time to the end of the current slot
                    $start_time = strtotime($slot_to);
                }
            }

            wp_send_json_success([
                'availability' => $final_slots,
                'booked_slots' => $booked_slots,
            ]);
        }


    private function get_google_calendar_bookings($partner_id, $date) {
        $url = "https://frohubpartners.mystagingwebsite.com/wp-json/fpserver/v1/google-calendar-events?partner_id=" . $partner_id . "&date=" . $date;
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
