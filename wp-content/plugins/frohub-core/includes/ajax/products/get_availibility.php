<?php

namespace FECore;

use FECore\Helper;

if (!defined('ABSPATH')) {
    exit;
}

class GetAvailibility
{

    public static function init()
    {
        $self = new self();
        add_action('wp_ajax_frohub/get_availibility', [$self, 'get_availibility']);
        add_action('wp_ajax_nopriv_frohub/get_availibility', [$self, 'get_availibility']);
    }

    public function get_availibility()
    {
        check_ajax_referer('frohub_nonce');

        if (empty($_POST['product_id'])) {
            wp_send_json_error(['message' => 'Product ID is required.']);
        }

        if (empty($_POST['date'])) {
            wp_send_json_error(['message' => 'Date is required.']);
        }

        $product_id = intval($_POST['product_id']);
        $date = sanitize_text_field($_POST['date']);
        $partner_id = get_field('partner_id', $product_id);
        $override_availability = get_field('override_availability', $product_id);

        // -------------------------
        // Availability source
        // -------------------------
        if ($override_availability) {
            $availability = get_field('availability', $product_id);
            $booking_notice = get_field('booking_notice', $product_id);
            $booking_scope = get_field('booking_scope', $product_id);
        } else {
            $availability = get_field('availability', $partner_id);
            $booking_notice = get_field('booking_notice', $partner_id);
            $booking_scope = get_field('booking_scope', $partner_id);
        }

        $booking_scope = is_numeric($booking_scope) ? intval($booking_scope) : 30;

        if (!$availability) {
            wp_send_json_error(['message' => 'No availability data found.']);
        }

        // -------------------------
        // Config
        // -------------------------
        $buffer_hours = get_field('buffer_period_hours', $partner_id);
        $buffer_minutes = get_field('buffer_period_minutes', $partner_id);
        $buffer_total_minutes = (intval($buffer_hours) * 60) + intval($buffer_minutes);

        $duration_hours = get_field('duration_hours', $product_id);
        $duration_minutes = get_field('duration_minutes', $product_id);
        $product_duration_minutes = ($duration_hours * 60) + $duration_minutes;

        // -------------------------
        // Add-ons duration
        // -------------------------
        $addons_ids = isset($_POST['addons_id']) ? (array) $_POST['addons_id'] : [];
        $addon_duration_minutes = 0;
        $addons = get_field('add_ons', $partner_id);

        if ($addons && is_array($addons)) {
            foreach ($addons as $addon) {
                if (in_array(intval($addon['add_on']->term_id), $addons_ids)) {
                    $addon_duration_minutes += intval($addon['duration_minutes'] ?? 0);
                }
            }
        }

        $total_duration_minutes = $product_duration_minutes + $addon_duration_minutes;

        // -------------------------
        // Local bookings (Woo Orders)
        // -------------------------
        $orders = Helper::get_orders_by_product_id_and_date_range($product_id, $date, $date);
        $booked_slots = [];
        $booked_days = [];

        foreach ($orders as $order) {
            if (!empty($order['selected_time'])) {
                $booked_slots[] = $order['selected_time'];
            }

            if (!empty($order['start_date_time'])) {
                $booked_days[] = date('Y-m-d', strtotime($order['start_date_time']));
            }
        }

        $booked_days = array_unique($booked_days);
        sort($booked_days);

        // -------------------------
        // Google Calendar bookings
        // -------------------------
        $google_calendar_booked_slots = [];
        $booked_slots = array_merge($booked_slots, $google_calendar_booked_slots);

        // ✅ Stop if all-day event detected
        if (in_array('ALL_DAY', $booked_slots, true)) {
            wp_send_json_success([
                'availability' => [],
                'booked_slots' => ['ALL_DAY'],
                'booked_days' => $booked_days,
                'next_available_date' => null,
                'service_duration' => $total_duration_minutes,
                'message' => '🛑 All-day Google Calendar event detected — no availability for this date.'
            ]);
        }

        $booked_slots_timestamps = $this->convert_slots_to_timestamps($booked_slots, $date);

        // -------------------------
        // Date scope setup
        // -------------------------
        $current_date = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime($current_date . ' + ' . $booking_scope . ' days'));

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

        // -------------------------
        // Generate all dates in scope
        // -------------------------
        $all_dates_in_scope = [];
        $current_date_obj = new \DateTime($current_date);
        $end_date_obj = new \DateTime($max_date);
        $interval = new \DateInterval('P1D');
        $date_period = new \DatePeriod($current_date_obj, $interval, $end_date_obj->modify('+1 day'));

        foreach ($date_period as $date_obj) {
            $weekday = $date_obj->format('l');
            $date_str = $date_obj->format('Y-m-d');
            $all_dates_in_scope[$weekday][] = $date_str;
        }

        // -------------------------
        // Generate slot list
        // -------------------------
        $final_slots = [];

        foreach ($availability as $slot) {
            $day = $slot['day'];
            $extra_charge = $slot['extra_charge'] ?? 0;

            if (empty($all_dates_in_scope[$day])) continue;

            foreach ($all_dates_in_scope[$day] as $date_str) {
                $start_time = strtotime($slot['from']);
                $end_time   = strtotime($slot['to']);

                while (($start_time + ($total_duration_minutes * 60) + ($buffer_total_minutes * 60)) <= $end_time) {
                    $slot_from = date('H:i', $start_time);
                    $slot_to   = date('H:i', $start_time + ($total_duration_minutes * 60));

                    $final_slots[] = [
                        'date' => $date_str,
                        'day'  => $day,
                        'from' => $slot_from,
                        'to'   => $slot_to,
                        'time_range' => "$slot_from - $slot_to",
                        'product_duration_minutes' => $product_duration_minutes,
                        'addon_duration_minutes'   => $addon_duration_minutes,
                        'total_duration_minutes'   => $total_duration_minutes,
                        'extra_charge' => $extra_charge,
                        'is_booked' => false
                    ];

                    $start_time += ($total_duration_minutes * 60) + ($buffer_total_minutes * 60);
                }
            }
        }

        // -------------------------
        // Google Calendar filters (range-based)
        // -------------------------
        $current_date = date('Y-m-d');
        $max_date = date('Y-m-d', strtotime($current_date . ' + ' . $booking_scope . ' days'));
        $gcal_events = $this->get_google_calendar_bookings($partner_id, $current_date, $max_date);

        $all_day_blocked = $gcal_events['all_day'] ?? [];
        $timed_bookings = $gcal_events['timed'] ?? [];

        // Remove all-day blocked dates
        $final_slots = array_filter($final_slots, function ($slot) use ($all_day_blocked) {
            return !in_array($slot['date'], $all_day_blocked, true);
        });

        // Remove overlapping timed events
        $final_slots = array_filter($final_slots, function ($slot) use ($timed_bookings) {
            $wp_tz = wp_timezone();
            $date = $slot['date'];
            if (empty($timed_bookings[$date])) return true;

            $slot_start = (new \DateTime("{$date} {$slot['from']}", $wp_tz))->getTimestamp();
            $slot_end   = (new \DateTime("{$date} {$slot['to']}", $wp_tz))->getTimestamp();

            foreach ($timed_bookings[$date] as $event) {
                if ($slot_start < $event['end'] && $slot_end > $event['start']) {
                    return false;
                }
            }
            return true;
        });

        // Remove slots overlapping with local bookings
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

        // -------------------------
        // Booking notice logic
        // -------------------------
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

        // -------------------------
        // Gather all booked days in scope
        // -------------------------
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

    // -------------------------
    // Helpers
    // -------------------------
    private function convert_slots_to_timestamps($booked_slots, $date)
    {
        $timestamps = [];
        foreach ($booked_slots as $slot) {
            $parts = explode(' - ', $slot);
            if (count($parts) === 2) {
                $timestamps[] = [
                    'start' => strtotime($date . ' ' . trim($parts[0])),
                    'end'   => strtotime($date . ' ' . trim($parts[1]))
                ];
            }
        }
        return $timestamps;
    }

    private function get_google_calendar_bookings($partner_id, $start_date, $end_date)
    {
        $wp_timezone = wp_timezone();
        $results = [
            'all_day' => [],
            'timed'   => []
        ];

        // ✅ Call your range endpoint once (not daily)
        $url = FHCORE_PARTNER_BASE_API_URL . "/wp-json/fpserver/v1/google-calendar-events?partner_id={$partner_id}&start={$start_date}&end={$end_date}";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log('❌ Google API error: ' . $response->get_error_message());
            return $results;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['events'])) {
            return $results;
        }

        foreach ($data['events'] as $event) {
            $start_ev = $event['start'] ?? '';
            $end_ev   = $event['end'] ?? '';
            $summary  = $event['title'] ?? '';

            if (empty($start_ev) || empty($end_ev)) {
                continue;
            }

            // 🟢 1. Handle all-day or date-only events (no 'T')
            if (!str_contains($start_ev, 'T') && !str_contains($end_ev, 'T')) {
                try {
                    $s = new \DateTimeImmutable($start_ev);
                    $e = new \DateTimeImmutable($end_ev);

                    // Google all-day events are exclusive on end date, so subtract one day
                    $e = $e->modify('-1 day');

                    $period = new \DatePeriod($s, new \DateInterval('P1D'), $e->modify('+1 day'));
                    foreach ($period as $d) {
                        $results['all_day'][] = $d->format('Y-m-d');
                    }
                } catch (\Exception $ex) {
                    error_log('⛔ Error parsing all-day: ' . $ex->getMessage());
                }
                continue;
            }

            // 🟢 2. Handle timed events (with 'T')
            try {
                $start_dt = new \DateTime($start_ev);
                $end_dt   = new \DateTime($end_ev);

                // Adjust to WP timezone for overlap checks
                $start_dt->setTimezone($wp_timezone);
                $end_dt->setTimezone($wp_timezone);

                // If spans multiple days, split across each date
                $current = clone $start_dt;
                while ($current->format('Y-m-d') <= $end_dt->format('Y-m-d')) {
                    $date_key = $current->format('Y-m-d');

                    $day_start = (new \DateTime($date_key . ' 00:00:00', $wp_timezone))->getTimestamp();
                    $day_end   = (new \DateTime($date_key . ' 23:59:59', $wp_timezone))->getTimestamp();

                    // Clip to current day boundaries
                    $event_start = max($day_start, $start_dt->getTimestamp());
                    $event_end   = min($day_end, $end_dt->getTimestamp());

                    $results['timed'][$date_key][] = [
                        'start' => $event_start,
                        'end'   => $event_end,
                        'summary' => $summary
                    ];

                    $current->modify('+1 day');
                }
            } catch (\Exception $ex) {
                error_log('⛔ Error parsing timed: ' . $ex->getMessage());
            }
        }

        $results['all_day'] = array_unique($results['all_day']);
        return $results;
    }
}
