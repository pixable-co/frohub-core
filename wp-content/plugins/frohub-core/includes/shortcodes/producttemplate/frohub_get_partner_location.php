<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubGetPartnerLocation {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_get_partner_location', array($self, 'frohub_get_partner_location_shortcode') );
    }

    public function frohub_get_partner_location_shortcode() {
        $unique_key = 'frohub_get_partner_location' . uniqid();
        $partner_id = get_field('partner_id'); 
        if ($partner_id) {
            $partner_address = get_field('partner_address', $partner_id);
            return '<span><i class="fas fa-map-marker-alt"></i> ' . esc_html($partner_address) . '</span>';
        } else {
            return '';
        }
        // return '<div class="frohub_get_partner_location" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
