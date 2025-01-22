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

    public function render_product_add_ons_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => 0,
        ), $atts, 'render_product_add_ons');

        $unique_key = 'render_product_add_ons' . uniqid();
        return '<div class="render_product_add_ons" data-key="' . esc_attr($unique_key) . '" data-product-id="' . esc_attr($atts['product_id']) . '"></div>';
    }
}
