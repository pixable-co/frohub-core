<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FhServicePrice {

    public static function init() {
        $self = new self();
        add_shortcode( 'fh_service_price', array($self, 'fh_service_price_shortcode') );
    }

    public function fh_service_price_shortcode() {
        // Get global post to access product ID
        global $post;

        if ( ! $post || $post->post_type !== 'product' ) {
            return '<span class="fh_service_price">Price not available</span>';
        }

        $product_id = $post->ID;
        $price = get_field('service_price', $product_id);

        if ( ! $price ) {
            return '<span class="fh_service_price">£0.00</span>';
        }

        // Ensure it's a float with 2 decimal places
        $formatted_price = number_format((float) $price, 2);

        return '<span class="fh_service_price">£' . esc_html($formatted_price) . '</span>';
    }
}
