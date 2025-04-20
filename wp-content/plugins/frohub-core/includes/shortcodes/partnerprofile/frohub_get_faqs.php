<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubGetFaqs {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_get_faqs', [ $self, 'frohub_get_faqs_shortcode' ] );
    }

    public function frohub_get_faqs_shortcode() {
        // grab the repeater
        $faqs = get_field( 'faqs' );
        if ( empty( $faqs ) || ! is_array( $faqs ) ) {
            // no FAQs → render nothing
            return '';
        }

        // build accordion shortcode
        $accordion = '[vc_tta_accordion c_icon="plus"]';
        foreach ( $faqs as $row ) {
            $faq_id   = intval( $row['faq_post_id'] );
            $title    = get_the_title( $faq_id );
            $content  = apply_filters( 'the_content', get_post_field( 'post_content', $faq_id ) );

            $accordion .= sprintf(
                '[vc_tta_section title="%s" tab_link="%%7B%%22url%%22%%3A%%22#%s%%22%%7D"]',
                esc_attr( $title ),
                esc_attr( sanitize_title( $title ) )
            );
            

            $accordion .= '[vc_column_text]' . $content . '[/vc_column_text]';
            $accordion .= '[/vc_tta_section]';
        }
        $accordion .= '[/vc_tta_accordion]';

        // wrap with an H3 heading
        $output  = '<h3>FAQs</h3>';
        $output .= do_shortcode( $accordion );

        return $output;
    }
}
