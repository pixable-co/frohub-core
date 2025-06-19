<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerMessageMobile {

    public static function init() {
        $self = new self();
        add_shortcode( 'customer_message_mobile', array($self, 'customer_message_mobile_shortcode') );
    }

    public function customer_message_mobile_shortcode() {
        $unique_key = 'customer_message_mobile' . uniqid();
        return '<div class="customer_message_mobile" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
