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

            if (!empty($customer_note)) {
                echo '<div class="customer_note">';
                echo esc_html($customer_note);
                echo '</div>';
            } else {
                echo '<div class="no_customer_note">';
                echo esc_html("You didn't add any notes for the stylist. If you need to contact them, you can use the messaging feature.");
                echo '</div>';
            }
        }

        return ob_get_clean();
    }
}
