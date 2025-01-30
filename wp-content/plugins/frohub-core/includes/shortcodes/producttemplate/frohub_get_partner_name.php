<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubGetPartnerName {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_get_partner_name', array($self, 'frohub_get_partner_name_shortcode') );
    }

    public function frohub_get_partner_name_shortcode() {
        $unique_key = 'frohub_get_partner_name' . uniqid();
        //[wbcr_php_snippet id="773"] on woody snippets
        // Get the Partner ID from the Product post type's ACF field
        $partner_id = get_field('partner_id'); 

        if ($partner_id) {
            // Get the title of the Partner post
            $partner_title = get_the_title($partner_id);

            // Display the Partner title
            return '<span>' . esc_html($partner_title) . ' <i class="fas fa-shield-alt"></i></span>';
        } else {
            return '';
        }
        // return '<div class="frohub_get_partner_name" data-key="' . esc_attr($unique_key) . '"></div>';
    }
}
