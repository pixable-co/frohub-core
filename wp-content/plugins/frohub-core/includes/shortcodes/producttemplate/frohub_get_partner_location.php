<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class FrohubGetPartnerLocation {

    public static function init() {
        $self = new self();
        add_shortcode('frohub_get_partner_location', array($self, 'frohub_get_partner_location_shortcode'));
    }

    public function frohub_get_partner_location_shortcode() {
        global $product;

        if (!$product || !$product->is_type('variable')) {
            return '';
        }

        $variation_ids = $product->get_children();
        $matched = false;

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);

            if (!$variation || $variation->get_status() !== 'publish') {
                continue;
            }

            $service_type = strtolower($variation->get_attribute('pa_service-type'));

            if (in_array($service_type, ['home-based', 'salon-based'])) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            return '';
        }

        $partner_id = get_field('partner_id', $product->get_id());

        if (!$partner_id) {
            return '';
        }

        $postcode = get_field('postcode', $partner_id);

        if (!$postcode) {
            return '';
        }

        return '<span class="frohub-partner-location"><i class="fas fa-map-marker-alt"></i> ' . esc_html($postcode) . '</span>';
    }
}
