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
        $override_availibility = get_field('override_availibility', $product_id);

        // Override Availability
        if (true === $override_availibility){
            $availability = get_field('availability', $product_id);
        }
        else {
                $availability = get_field('availability', $partner_id);
        }

        if (!$availability) {
            wp_send_json_error(['message' => 'No availability data found.']);
        }

        // Fetch product duration from ACF and convert to minutes
        $duration_hours = get_field('duration_hours', $product_id);
        $duration_minutes = get_field('duration_minutes', $product_id);
        $product_duration_minutes = ($duration_hours * 60) + $duration_minutes;

        // Handle multiple addons_id passed as array
        $addons_ids = isset($_POST['addons_id']) ? $_POST['addons_id'] : [];

        // Ensure $addons_ids is an array even if one ID is passed
        if (!is_array($addons_ids)) {
            $addons_ids = [$addons_ids];
        }

        $addon_duration_minutes = 0;  // Initialize total addon duration

        // Fetch add-ons from the partner post type and sum their durations
        $addons = get_field('add_ons', $partner_id);
        if ($addons && is_array($addons)) {
            foreach ($addons as $addon) {
                if (in_array(intval($addon['add_on']->term_id), $addons_ids)) {
                    $addon_duration_minutes += isset($addon['duration_minutes']) ? intval($addon['duration_minutes']) : 0;
                }
            }
        }

        // Total duration includes product and add-on durations
        $total_duration_minutes = $product_duration_minutes + $addon_duration_minutes;

        // Get booked slots from WooCommerce orders
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

        // Convert booked slots to timestamps for easier comparison
        $booked_slots_timestamps = $this->convert_slots_to_timestamps($booked_slots);

        $final_slots = [];
        $available_dates = [];

        // Generate all possible slots based on total duration
        foreach ($availability as $slot) {
            $day = $slot['day'];
            $extra_charge = !empty($slot['extra_charge']) ? $slot['extra_charge'] : 0;
            $start_time = strtotime($slot['from']);
            $end_time = strtotime($slot['to']);

            while (($start_time + ($total_duration_minutes * 60)) <= $end_time) {
                $slot_from = date('H:i', $start_time);
                $slot_to = date('H:i', $start_time + ($total_duration_minutes * 60));
                $time_range = "$slot_from - $slot_to";

                // Add all slots initially
                $final_slots[] = [
                    'day'                     => $day,
                    'from'                    => $slot_from,
                    'to'                      => $slot_to,
                    'time_range'              => $time_range,
                    'product_duration_minutes'=> $product_duration_minutes,
                    'addon_duration_minutes'  => $addon_duration_minutes,
                    'total_duration_minutes'  => $total_duration_minutes,
                    'extra_charge'            => $extra_charge,
                    'is_booked'               => false
                ];

                // Collect available dates
                $date_available = date('Y-m-d', strtotime("next $day"));
                if (!in_array($date_available, $available_dates)) {
                   $available_dates[] = $date_available;
                }

                $start_time += ($total_duration_minutes * 60);
            }
        }

        // Now filter out slots that overlap with booked slots
        $available_slots = array_filter($final_slots, function ($slot) use ($booked_slots_timestamps) {
            $slot_start = strtotime($slot['from']);
            $slot_end = strtotime($slot['to']);

            foreach ($booked_slots_timestamps as $booked_slot) {
                if ($slot_start < $booked_slot['end'] && $slot_end > $booked_slot['start']) {
                    return false;  // Overlap detected, remove this slot
                }
            }

            return true;  // No overlap, keep the slot
        });

        sort($available_dates);
        $next_available_date = count($available_dates) > 0 ? $available_dates[0] : null;
        $booking_notice = get_field('booking_notice', $product_id);
        $booking_notice_days = is_numeric($booking_notice) ? intval($booking_notice) : 0;

        wp_send_json_success([
            'availability' => array_values($available_slots),
            'booked_slots' => $booked_slots,
            'next_available_date' => $next_available_date,
            'service_duration' => $total_duration_minutes,
            'booking_notice'   => $booking_notice_days,
        ]);
    }

    // Convert booked slots into timestamps for easier comparison
    private function convert_slots_to_timestamps($booked_slots) {
        $booked_slots_timestamps = [];

        foreach ($booked_slots as $slot) {
            list($from, $to) = explode(' - ', $slot);
            $booked_slots_timestamps[] = [
                'start' => strtotime($from),
                'end'   => strtotime($to)
            ];
        }

        return $booked_slots_timestamps;
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
