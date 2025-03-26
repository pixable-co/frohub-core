<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class FrohubGetProductServiceTypes {

    public static function init() {
        $self = new self();
        add_shortcode('frohub_get_product_service_types', array($self, 'frohub_get_product_service_types_shortcode'));
    }

    public function frohub_get_product_service_types_shortcode() {
        global $product;

        if (!$product || !$product->is_type('variable')) {
            return '';
        }

        $serviceTypes = [];
        $variation_ids = $product->get_children();

        foreach ($variation_ids as $variation_id) {
            $variation_product = wc_get_product($variation_id);
            if ($variation_product->get_status() !== 'publish') {
                continue;
            }

            $service_type = $variation_product->get_attribute('pa_service-type');

            if (!empty($service_type) && !in_array($service_type, $serviceTypes)) {
                $serviceTypes[] = $service_type;
            }
        }

        if (empty($serviceTypes)) {
            return '';
        }

        // Output styled pills (match your screenshot)
        $output = '<div class="fh-product-service-type-container">';
        foreach ($serviceTypes as $type) {
            $output .= '<span class="fh-service-type-pill">' . esc_html($type) . '</span>';
        }
        $output .= '</div>';

        return $output;
    }
}
