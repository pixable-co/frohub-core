<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderNotes {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_notes', array($self, 'get_order_notes_shortcode') );
    }

    public function get_order_notes_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if ($order) {
            $customer_note = $order->get_customer_note();
            if ($customer_note) {
                echo esc_html($customer_note);
            }
        }

        return ob_get_clean();
    }
}
