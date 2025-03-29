<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PrintOrderId {

    public static function init() {
        $self = new self();
        add_shortcode( 'print_order_id', array($self, 'print_order_id_shortcode') );
    }

    public function print_order_id_shortcode() {
        ob_start();

        if ( isset($GLOBALS['single_order_id']) && $GLOBALS['single_order_id'] ) {
            echo '<p class="order_id">#' . esc_html($GLOBALS['single_order_id']) . '</p>';
        }

        return ob_get_clean();
    }
}
