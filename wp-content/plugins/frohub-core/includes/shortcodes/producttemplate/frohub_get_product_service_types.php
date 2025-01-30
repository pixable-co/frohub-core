<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubGetProductServiceTypes {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_get_product_service_types', array($self, 'frohub_get_product_service_types_shortcode') );
    }

    public function frohub_get_product_service_types_shortcode() {
        $unique_key = 'frohub_get_product_service_types' . uniqid();
        //[wbcr_php_snippet id="709"] on woody snippets
        $checkbox_values = get_field('service_types');
        $output= '<div class="product-service-type-container">';
        if ($checkbox_values) {
            foreach ($checkbox_values as $value) {
                $output.= '<span>'.esc_html($value).'</span>';
            }
            $output.= '</div>';
            return $output;
        } else {
            return '';
        }
        // return '<div class="frohub_get_product_service_types" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
