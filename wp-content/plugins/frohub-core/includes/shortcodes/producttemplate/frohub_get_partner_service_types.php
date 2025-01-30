<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubGetPartnerServiceTypes {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_get_partner_service_types', array($self, 'frohub_get_partner_service_types_shortcode') );
    }

    public function frohub_get_partner_service_types_shortcode() {
        $unique_key = 'frohub_get_partner_service_types' . uniqid();
        //previous code [wbcr_php_snippet id="647"]
        $checkbox_values = get_field('service_types');
        if ($checkbox_values) {
            $output= '<div class="service-type-container">';
            foreach ($checkbox_values as $value) {
                $output.= '<span>'.$value.'</span>';
            }
            $output.= '</div>';
            return $output;
        } else {
            return '';
        }
        // return '<div class="frohub_get_partner_service_types" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
