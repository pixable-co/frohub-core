<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DisplayPartnerName {

    public static function init() {
        $self = new self();
        add_shortcode( 'display_partner_name', array($self, 'display_partner_name_shortcode') );
    }

    public function display_partner_name_shortcode() {
        ob_start();

        $post_id = get_the_ID();
        $partner_id = get_field('partner_id', $post_id);

        if ($partner_id) {
              echo '<span><a href="' . esc_url(get_permalink($partner_id)) . '">' . esc_html(get_the_title($partner_id)) . '</a></span>';
        }

        return ob_get_clean();
    }
}
