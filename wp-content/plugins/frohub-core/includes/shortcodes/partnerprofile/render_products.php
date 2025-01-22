<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RenderProducts {

    public static function init() {
        $self = new self();
        add_shortcode( 'render_products', array($self, 'render_products_shortcode') );
    }

    public function render_products_shortcode() {
        $unique_key = 'render_products' . uniqid();
        return '<div class="render_products" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
