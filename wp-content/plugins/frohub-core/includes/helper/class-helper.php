<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper {

    public static function get_orders_by_product_id_and_date($product_id, $date) {
        global $wpdb;

        if (!$product_id || !$date) {
            return [];
        }

        $formatted_date = date('Y-m-d', strtotime($date));

        $query = $wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} AS p
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
            AND DATE(p.post_date) = %s
        ", $formatted_date);

        $order_ids = $wpdb->get_col($query);

        if (empty($order_ids)) {
            return [];
        }

        $orders = [];

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);

            if ($order) {
                foreach ($order->get_items() as $item_id => $item) {
                    if ($item->get_product_id() == $product_id) {
                        $selected_date = wc_get_order_item_meta($item_id, 'Selected Date', true);
                        $selected_time = wc_get_order_item_meta($item_id, 'Selected Time', true);

                        $orders[] = [
                            'order_id'       => $order_id,
                            'order_status'   => $order->get_status(),
                            'order_total'    => $order->get_total(),
                            'order_date'     => $order->get_date_created()->format('Y-m-d H:i:s'),
                            'customer_email' => $order->get_billing_email(),
                            'selected_date'  => $selected_date ?: null,
                            'selected_time'  => $selected_time ?: null
                        ];
                        break;
                    }
                }
            }
        }

        return $orders;
    }



}
