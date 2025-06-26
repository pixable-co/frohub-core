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

        $availability = get_field('availability', $product_id);
        if (empty($availability)) {
            $availability = get_field('availability', $partner_id);
        }

        if (!$availability) {
            wp_send_json_error(['message' => 'No availability data found.']);
        }

        $buffer_hours = get_field('buffer_period_hours', $partner_id);
        $buffer_minutes = get_field('buffer_period_minutes', $partner_id);
        $buffer_total_minutes = (intval($buffer_hours) * 60) + intval($buffer_minutes);

        $duration_hours = get_field('duration_hours', $product_id);
        $duration_minutes = get_field('duration_minutes', $product_id);
        $product_duration_minutes = ($duration_hours * 60) + $duration_minutes;

        $addons_ids = isset($_POST['addons_id']) ? $_POST['addons_id'] : [];
        if (!is_array($addons_ids)) {
            $addons_ids = [$addons_ids];
        }

        $addon_duration_minutes = 0;
        $addons = get_field('add_ons', $partner_id);
        if ($addons && is_array($addons)) {
            foreach ($addons as $addon) {
                if (in_array(intval($addon['add_on']->term_id), $addons_ids)) {
                    $addon_duration_minutes += isset($addon['duration_minutes']) ? intval($addon['duration_minutes']) : 0;
                }
            }
        }

        $total_duration_minutes = $product_duration_minutes + $addon_duration_minutes;

        // ✅ Fix: use date range for single date retrieval
        $orders = Helper::get_orders_by_product_id_and_date_range($product_id, $date, $date);
        $booked_slots = [];
        $booked_days = [];

        foreach ($orders as $order) {
            if (!empty($order['selected_time'])) {
                $booked_slots[] = $order['selected_time'];
            }

            if (!empty($order['start_date_time'])) {
                $order_date = date('Y-m-d', strtotime($order['start_date_time']));
                $booked_days[] = $order_date;
            }
        }

        $booked_days = array_unique($booked_days);
        sort($booked_days);

        $google_calendar_booked_slots = $this->get_google_calendar_bookings($partner_id, $date);
        $booked_slots = array_merge($booked_slots, $google_calendar_booked_slots);

        $booked_slots_timestamps = $this->convert_slots_to_timestamps($booked_slots);

        $booking_scope = get_field('booking_scope', $partner_id);
        $booking_scope = is_numeric($booking_scope) ? intval($booking_scope) : 30;

        $current_date = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime($current_date . ' + ' . $booking_scope . ' days'));

        // ✅ Fix: unavailable_dates fetched before use
        $unavailable_dates = get_field('unavailable_dates', $partner_id);
        $formatted_unavailable_dates = [];

        if (!empty($unavailable_dates) && is_array($unavailable_dates)) {
            foreach ($unavailable_dates as $date_entry) {
                if (!empty($date_entry['start_date']) && !empty($date_entry['end_date'])) {
                    $formatted_unavailable_dates[] = [
                        'start_date' => $date_entry['start_date'],
                        'end_date' => $date_entry['end_date']
                    ];
                }
            }
        }

        // ✅ Generate all dates in booking scope
        $all_dates_in_scope = [];
        $current_date_obj = new \DateTime($current_date);
        $end_date_obj = new \DateTime($max_date);

        $interval = new \DateInterval('P1D');
        $date_period = new \DatePeriod($current_date_obj, $interval, $end_date_obj->modify('+1 day'));

        foreach ($date_period as $date_obj) {
            $weekday = $date_obj->format('l'); // "Monday"
            $date_str = $date_obj->format('Y-m-d');
            $all_dates_in_scope[$weekday][] = $date_str;
        }

        $final_slots = [];

        foreach ($availability as $slot) {
            $day = $slot['day'];
            $extra_charge = !empty($slot['extra_charge']) ? $slot['extra_charge'] : 0;

            if (empty($all_dates_in_scope[$day])) continue;

            foreach ($all_dates_in_scope[$day] as $date_str) {
                $start_time = strtotime($slot['from']);
                $end_time = strtotime($slot['to']);

                while (($start_time + ($total_duration_minutes * 60) + ($buffer_total_minutes * 60)) <= $end_time) {
                    $slot_from = date('H:i', $start_time);
                    $slot_to = date('H:i', $start_time + ($total_duration_minutes * 60));
                    $time_range = "$slot_from - $slot_to";

                    $final_slots[] = [
                        'date' => $date_str,
                        'day'  => $day,
                        'from' => $slot_from,
                        'to'   => $slot_to,
                        'time_range' => $time_range,
                        'product_duration_minutes' => $product_duration_minutes,
                        'addon_duration_minutes'   => $addon_duration_minutes,
                        'total_duration_minutes'   => $total_duration_minutes,
                        'extra_charge' => $extra_charge,
                        'is_booked'    => false
                    ];

                    $start_time += ($total_duration_minutes * 60) + ($buffer_total_minutes * 60);
                }
            }
        }

        // ✅ Fix: slot time matching with slot[date]
        $available_slots = array_filter($final_slots, function ($slot) use ($booked_slots_timestamps) {
            $slot_start = strtotime($slot['date'] . ' ' . $slot['from']);
            $slot_end = strtotime($slot['date'] . ' ' . $slot['to']);

            foreach ($booked_slots_timestamps as $booked_slot) {
                if ($slot_start < $booked_slot['end'] && $slot_end > $booked_slot['start']) {
                    return false;
                }
            }

            return true;
        });

        $booking_notice = get_field('booking_notice', $product_id);
        if (empty($booking_notice)) {
              $booking_notice = get_field('booking_notice', $partner_id);
        }
        $booking_notice_days = is_numeric($booking_notice) ? intval($booking_notice) : 0;

        $today = new \DateTime();
        $notice_cutoff_date = $today->modify("+{$booking_notice_days} days")->format('Y-m-d');

        $next_available_date = null;
        foreach ($available_slots as $slot) {
            if ($slot['date'] > $notice_cutoff_date) {
                $next_available_date = $slot['date'];
                break;
            }
        }

        // ✅ All bookings in scope
        $all_orders = Helper::get_orders_by_product_id_and_date_range($product_id, $current_date, $max_date);

        $all_bookings = [];
        $all_booked_days = [];

        foreach ($all_orders as $order) {
            if (!empty($order['start_date_time'])) {
                $order_date = date('Y-m-d', strtotime($order['start_date_time']));
                $all_bookings[$order_date][] = $order['selected_time'];
                $all_booked_days[] = $order_date;
            }
        }

        $all_booked_days = array_unique($all_booked_days);
        sort($all_booked_days);

        wp_send_json_success([
            'availability' => array_values($available_slots),
            'booked_slots' => $booked_slots,
            'booked_days' => $booked_days,
            'next_available_date' => $next_available_date,
            'service_duration' => $total_duration_minutes,
            'booking_notice' => $booking_notice_days,
            'google_calendar_booked_slots' => $google_calendar_booked_slots,
            'buffer_period_minutes' => $buffer_total_minutes,
            'booking_scope' => $booking_scope,
            'max_date' => $max_date,
            'unavailable_dates' => $formatted_unavailable_dates,
            'all_booked_days' => $all_booked_days,
            'all_bookings' => $all_bookings
        ]);
    }

    private function get_dates_for_day_within_scope($day, $startDate, $endDate) {
        $dates = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);

        while ($current <= $end) {
            if (date('l', $current) === $day) {
                $dates[] = date('Y-m-d', $current);
            }
            $current = strtotime('+1 day', $current);
        }

        return $dates;
    }

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
        $url = FHCORE_PARTNER_BASE_API_URL . "/wp-json/fpserver/v1/google-calendar-events?partner_id=" . $partner_id . "&date=" . $date;
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
                $start_time = date('H:i', strtotime($event['start']));
                $end_time = date('H:i', strtotime($event['end']));
                $booked_slots[] = "$start_time - $end_time";
            }
        }

        return $booked_slots;
    }
}
