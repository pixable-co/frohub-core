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
        $partner_id = get_field('partner_id');

        if (!$partner_id) {
            return '';
        }

        // Get individual address fields
        $street     = get_field('street_address', $partner_id);
        $town       = get_field('town', $partner_id);
        $county     = get_field('county_district', $partner_id);

        // Build address string (skip empty parts gracefully)
        $address_parts = array_filter([$street, $town, $county]);
        $full_address = implode(', ', $address_parts);

        if (!$full_address) {
            return '';
        }

        return '<span class="frohub-partner-location"><i class="fas fa-map-marker-alt"></i> ' . esc_html($full_address) . '</span>';
    }
}
