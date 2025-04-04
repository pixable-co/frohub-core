<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SendOrderToEndpoint {

    public static function init() {
        $self = new self();

        add_action('woocommerce_order_status_on-hold', array($self, 'send_order_to_endpoint'));
        add_action('woocommerce_order_status_processing', array($self, 'send_order_to_endpoint'));
    }

    public function send_order_to_endpoint($order_id) {
        // Define endpoints based on order status
        $endpoints = [
            'on-hold'    => 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.a2b4f63dfb58631d29cd422e757e1643.ab4522f28de91914f023873159058bfb&isdebug=false',
            'processing' => 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.c24fdf1c034a419e885cbc1353668061.25d7d31440d630f3414b8de7488df2d3&isdebug=false',
        ];

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('Error: Order not found.');
            return;
        }

        $order_status = $order->get_status();

        if (!isset($endpoints[$order_status])) {
            error_log("No endpoint defined for status: $order_status");
            return;
        }

        $endpoint = $endpoints[$order_status];

        $billing_address = implode(', ', array_filter([
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode()
        ]));

        $frohub_booking_fee = 0;
        $deposit = 0;
        $balance = 0;
        $total_service_fee = 0;
        $service_name = 'Unknown';
        $partner_name = 'Unknown';
        $booking_date_time = 'Not Set';
        $addons = 'None';
        $service_type = 'Unknown';

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $item_total = $item->get_total();

            if ($product_id == 2600) {
                $frohub_booking_fee = $item_total;
            } else {
                $deposit = $item_total;
                $service_name = $item->get_name();

                $partner_post = get_field('partner_name', $product_id);
                if ($partner_post && is_object($partner_post)) {
                    $partner_name = get_the_title($partner_post->ID);
                }

                $total_service_fee = ($deposit > 0) ? ($deposit / 0.3) : 0;
                $balance = $total_service_fee - $deposit;

                $selected_date = wc_get_order_item_meta($item->get_id(), 'Selected Date', true);
                $selected_time = wc_get_order_item_meta($item->get_id(), 'Selected Time', true);

                if (!empty($selected_date) && !empty($selected_time)) {
                    $formatted_date = date('d M Y', strtotime($selected_date));
                    $start_time = explode(' - ', $selected_time)[0];
                    $booking_date_time = "$start_time, $formatted_date";
                }

                $selected_addons = wc_get_order_item_meta($item->get_id(), 'Selected Add-Ons', true);
                if (!empty($selected_addons)) {
                    $addons = is_array($selected_addons) ? implode(', ', $selected_addons) : $selected_addons;
                } else {
                    $addons = 'None';
                }

                $service_type_meta = wc_get_order_item_meta($item->get_id(), 'Service Type', true);
                if (!empty($service_type_meta)) {
                    $service_type = $service_type_meta;
                }
            }
        }

        $format_currency = function($amount) {
            return '£' . number_format($amount, 2, '.', ',');
        };

        $order_data = [
            'order_id'           => '#' . strval($order_id),
            'status'             => $order_status,
            'client_email'       => $order->get_billing_email(),
            'client_first_name'  => $order->get_billing_first_name(),
            'service_address'    => $billing_address,
            'partner_name'       => $partner_name,
            'frohub_booking_fee' => $format_currency($frohub_booking_fee),
            'deposit'            => $format_currency($deposit),
            'balance'            => $format_currency($balance),
            'total_service_fee'  => $format_currency($total_service_fee),
            'service_name'       => $service_name,
            'booking_date_time'  => $booking_date_time,
            'addons'             => $addons,
            'service_type'       => $service_type,
        ];

        $response = wp_remote_post($endpoint, [
            'method'    => 'POST',
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => json_encode($order_data),
            'timeout'   => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('Error sending order data to ' . $endpoint . ': ' . $response->get_error_message());
        } else {
            error_log("Order #$order_id successfully sent to $endpoint.");
        }
    }
}
