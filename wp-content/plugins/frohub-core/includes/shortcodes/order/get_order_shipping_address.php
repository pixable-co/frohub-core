<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderShippingAddress {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_shipping_address', array($self, 'get_order_shipping_address_shortcode') );
    }

    public function get_order_shipping_address_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if ($order) {
            $shipping_first_name = $order->get_shipping_first_name();
            $shipping_last_name = $order->get_shipping_last_name();
            $shipping_company = $order->get_shipping_company();
            $shipping_address_1 = $order->get_shipping_address_1();
            $shipping_address_2 = $order->get_shipping_address_2();
            $shipping_city = $order->get_shipping_city();
            $shipping_state = $order->get_shipping_state();
            $shipping_postcode = $order->get_shipping_postcode();
            $shipping_country = $order->get_shipping_country();

            echo esc_html($shipping_address_1) . '<br>';

            if ($shipping_address_2) {
                echo esc_html($shipping_address_2) . '<br>';
            }

            if ($shipping_company) {
                echo esc_html($shipping_company) . '<br>';
            }

            echo esc_html($shipping_city) . '<br> ' . esc_html($shipping_state) . ' ' . esc_html($shipping_postcode) . '<br>';
            // echo esc_html($shipping_country) . '<br>';
        }

        return ob_get_clean();
    }
}
