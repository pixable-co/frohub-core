<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerMessage {

    public static function init() {
        $self = new self();
        add_shortcode( 'customer_message', array($self, 'customer_message_shortcode') );
    }

    public function customer_message_shortcode() {
        $unique_key = 'customer_message' . uniqid();
        return '<div class="customer_message" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
