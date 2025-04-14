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
        global $post;
    
        if ( ! $post instanceof \WP_Post ) {
            return '';
        }
    
        // Check if this is a variation
        $product = wc_get_product( $post->ID );
    
        if ( ! $product || ! $product->is_type('variation') || $product->get_status() !== 'publish' ) {
            return '';
        }
    
        // Get the pa_service-type attribute
        $service_type = $product->get_attribute('pa_service-type');
    
        // Check if it's home-based or salon-based (case insensitive)
        if ( ! in_array( strtolower($service_type), ['home-based', 'salon-based'] ) ) {
            return '';
        }
    
        // Get partner ID from variation or parent product
        $partner_id = get_field('partner_id', $product->get_id()); // Try variation-level first
    
        if ( ! $partner_id ) {
            $parent_id = $product->get_parent_id();
            $partner_id = get_field('partner_id', $parent_id); // Fallback to parent product
        }
    
        if ( ! $partner_id ) {
            return '';
        }
    
        // Get and return postcode
        $postcode = get_field('postcode', $partner_id);
    
        if ( ! $postcode ) {
            return '';
        }
    
        return '<span class="frohub-partner-location"><i class="fas fa-map-marker-alt"></i> ' . esc_html($postcode) . '</span>';
    }
    
}
