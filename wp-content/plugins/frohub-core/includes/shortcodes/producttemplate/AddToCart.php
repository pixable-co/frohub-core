<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RenderAddToCart {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_add_to_cart', array($self, 'frohub_add_to_cart_shortcode') );
    }

    public function frohub_add_to_cart_shortcode() {
        global $post;
        $product_id = $post->ID;
        $partner_id = get_field('partner_id', $product_id);

        $unique_key = 'frohub_add_to_cart' . uniqid();
        return '<div class="frohub_add_to_cart"
                        data-key="' . esc_attr($unique_key) . '"
                        data-product-id="' . esc_attr($product_id) . '"
                        data-partner-id="' . esc_attr($partner_id) . '">
                    </div>';
    }
}


// Initialize the shortcode
RenderAddToCart::init();
