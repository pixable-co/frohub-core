<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PartnerLogoAndBio {

    public static function init() {
        $self = new self();
        add_shortcode( 'partner_logo_and_bio', array($self, 'partner_logo_and_bio_shortcode') );
    }

    public function partner_logo_and_bio_shortcode() {
        ob_start();

        $post_id = get_the_ID();
        $partner_id = get_field('partner_id', $post_id);
        $partner_post = get_post($partner_id);
        $image_url = '';
        $bio = '';

        if ($partner_post) {
            if (has_post_thumbnail($partner_id)) {
                $image = wp_get_attachment_image_src(get_post_thumbnail_id($partner_id), 'full');
                $image_url = $image[0];
            }
            $bio = $partner_post->post_content;
        }

        echo do_shortcode('[vc_row_inner columns="1-3" content_placement="top"]'
            . '[vc_column_inner width="1/4"]'
            . '[us_image image="' . esc_attr($image_url) . '" el_class="beautician_logo" link="%7B%22url%22%3A%22%22%7D" css="%7B%22default%22%3A%7B%22border-radius%22%3A%2250%25%22%7D%7D"]'
            . '[/vc_column_inner]'
            . '[vc_column_inner width="3/4"]'
            . '[us_text tag="h6" text="' . esc_html(get_the_title($partner_id)) . '" link="%7B%22url%22%3A%22%22%7D"]'
            . '[vc_column_text]' . wp_kses_post($bio) . '[/vc_column_text]'
            . '[/vc_column_inner]'
            . '[/vc_row_inner]');

        return ob_get_clean();
    }
}
