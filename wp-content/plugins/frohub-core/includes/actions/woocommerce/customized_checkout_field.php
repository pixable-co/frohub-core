<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomizedCheckoutField {

    public static function init() {
        $self = new self();
        add_filter('woocommerce_checkout_fields', array($self, 'customize_checkout_fields'));
    }

    public function customize_checkout_fields($fields) {
        // Make phone number required
        $fields['billing']['billing_phone']['required'] = true;
        return $fields;
    }
}
