<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetPartnerName {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_partner_name', array($self, 'get_partner_name_shortcode') );
    }

    public function get_partner_name_shortcode() {
        ob_start();

        $post_id = get_the_ID();
        $partner_id = get_field('partner_id', $post_id);

        if ($partner_id) {
            echo '<span><a href="' . esc_url(get_permalink($partner_id)) . '">' . esc_html(get_the_title($partner_id)) . '</a> <i class="fas fa-shield-alt"></i></span>';
        }

        return ob_get_clean();
    }
}
