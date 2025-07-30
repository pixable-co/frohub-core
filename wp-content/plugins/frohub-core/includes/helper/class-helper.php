<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class Helper
{

    public static function get_orders_by_product_id_and_date_range($product_id, $start_date, $end_date)
    {
        global $wpdb;

        if (!$product_id || !$start_date || !$end_date) {
            return [];
        }

        $query = $wpdb->prepare("
            SELECT
                p.ID as order_id,
                p.post_status,
                p.post_date,
                im1.meta_value as start_date_time,
                im2.meta_value as end_date_time
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im1 ON oi.order_item_id = im1.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im2 ON oi.order_item_id = im2.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_meta ON oi.order_item_id = product_meta.order_item_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-rescheduling', 'wc-processing', 'wc-on-hold')
            AND im1.meta_key = 'Start Date Time'
            AND im2.meta_key = 'End Date Time'
            AND product_meta.meta_key = '_product_id'
            AND product_meta.meta_value = %d
            AND STR_TO_DATE(im1.meta_value, '%%H:%%i, %%d %%b %%Y') BETWEEN %s AND %s
        ", $product_id, $start_date, $end_date);

        $results = $wpdb->get_results($query);

        if (empty($results)) {
            return [];
        }

        $orders = [];

        foreach ($results as $result) {
            $selected_time = self::convert_to_selected_time($result->start_date_time, $result->end_date_time);

            $orders[] = [
                'order_id'        => $result->order_id,
                'selected_time'   => $selected_time,
                'start_date_time' => $result->start_date_time
            ];
        }

        return $orders;
    }

    public static function get_orders_by_product_id_and_date($product_id, $date)
    {
        global $wpdb;

        if (!$product_id || !$date) {
            return [];
        }

        $formatted_date = date('d M Y', strtotime($date));

        $query = $wpdb->prepare("
                SELECT
                    p.ID as order_id,
                    p.post_status,
                    p.post_date,
                    im1.meta_value as start_date_time,
                    im2.meta_value as end_date_time
                FROM {$wpdb->posts} AS p
                INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON p.ID = oi.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im1 ON oi.order_item_id = im1.order_item_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im2 ON oi.order_item_id = im2.order_item_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_meta ON oi.order_item_id = product_meta.order_item_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-rescheduling', 'wc-processing', 'wc-on-hold')
                AND im1.meta_key = 'Start Date Time'
                AND im1.meta_value LIKE %s  -- ✅ Match only the date part
                AND im2.meta_key = 'End Date Time'
                AND product_meta.meta_key = '_product_id'
                AND product_meta.meta_value = %d
            ", "%$formatted_date%", $product_id); // ✅ This now correctly matches the stored date

        $results = $wpdb->get_results($query);

        if (empty($results)) {
            return [];
        }

        $orders = [];

        foreach ($results as $result) {
            $order = wc_get_order($result->order_id);

            if ($order) {
                $selected_time = self::convert_to_selected_time($result->start_date_time, $result->end_date_time);
                $orders[] = [
                    'order_id'        => $result->order_id,
                    'order_status'    => $order->get_status(),
                    'order_total'     => $order->get_total(),
                    'order_date'      => $order->get_date_created()->format('Y-m-d H:i:s'),
                    'customer_email'  => $order->get_billing_email(),
                    'selected_time'   => $selected_time,
                    'start_date_time' => $result->start_date_time
                ];
            }
        }

        return $orders;
    }

    public static function get_next_upcoming_order_by_partner($partner_id, $statuses = ['wc-rescheduling', 'wc-pending', 'wc-on-hold'])
    {
        global $wpdb;

        if (!$partner_id) {
            $partner_id = get_field('partner_id');
            if (!$partner_id) {
                return null;
            }
        }

        // Sanitize statuses
        $statuses = array_map('sanitize_key', (array) $statuses);
        $status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        $current_datetime = current_time('mysql');

        $sql = "
            SELECT
                p.ID AS order_id,
                p.post_status,
                p.post_date,
                im1.meta_value AS start_date_time,
                im2.meta_value AS end_date_time,
                oi.order_item_name AS service_name
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items AS oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im1 ON oi.order_item_id = im1.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im2 ON oi.order_item_id = im2.order_item_id
            INNER JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
              AND p.post_status IN ($status_placeholders)
              AND im1.meta_key = 'Start Date Time'
              AND im2.meta_key = 'End Date Time'
              AND pm.meta_key = 'partner_id'
              AND pm.meta_value = %d
              AND STR_TO_DATE(im1.meta_value, '%%H:%%i, %%d %%b %%Y') >= %s
            ORDER BY STR_TO_DATE(im1.meta_value, '%%H:%%i, %%d %%b %%Y') ASC
            LIMIT 1
        ";

        $params = array_merge($statuses, [$partner_id, $current_datetime]);
        $query  = $wpdb->prepare($sql, $params);

        $result = $wpdb->get_row($query);
        if (!$result) {
            return null;
        }

        $order = wc_get_order($result->order_id);
        if (!$order) {
            return null;
        }

        $conversationId = get_field('conversation', $result->order_id);
        $partnerPlatformClientId = get_field('partner_client_post_id', $conversationId);

        // Convert status codes to human-friendly labels
        $status_label_map = [
            'wc-rescheduling' => 'Rescheduled',
            'wc-pending'      => 'Pending',
            'wc-on-hold'      => 'On hold',
            'wc-processing'   => 'Confirmed',
        ];
        $status_label = $status_label_map[$result->post_status] ?? ucfirst(str_replace('wc-', '', $result->post_status));

        // Parse start date/time
        $start_datetime_parts = explode(', ', $result->start_date_time);
        $start_time = $start_datetime_parts[0] ?? '';
        $start_date = $start_datetime_parts[1] ?? '';

        return [
            'order_id'        => $result->order_id,
            'status'          => $status_label, // Human-readable status
            'start_date'      => $start_date,
            'start_time'      => $start_time,
            'service_name'    => $result->service_name,
            'client_name'     => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'client_phone'    => $order->get_billing_phone(),
            'client_email'    => $order->get_billing_email(),
            'customer_id'     => $order->get_customer_id(),
            'partner_platform_client_id' => $partnerPlatformClientId,
        ];
    }

    private static function convert_to_selected_time($start_date_time, $end_date_time)
    {
        if (!$start_date_time || !$end_date_time) {
            return null;
        }

        // Convert "12:00, 07 Mar 2025" → "12:00"
        $start_time = date('H:i', strtotime($start_date_time));
        $end_time = date('H:i', strtotime($end_date_time));

        return "$start_time - $end_time";
    }


}
