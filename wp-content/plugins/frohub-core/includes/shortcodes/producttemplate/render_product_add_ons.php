<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RenderProductAddOns {

    public static function init() {
        $self = new self();
        add_shortcode( 'render_product_add_ons', array($self, 'render_product_add_ons_shortcode') );
    }

    public function render_product_add_ons_shortcode() {
        $unique_key = 'render_product_add_ons' . uniqid();
        return '<div class="render_product_add_ons" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
