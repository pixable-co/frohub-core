<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubGetPolicies {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_get_policies', [ $self, 'frohub_get_policies_shortcode' ] );
    }

    public function frohub_get_policies_shortcode() {
        // We'll always have the hard‑coded Refund Policy
        $sections = [];

        // 1) Refund Policy (hard‑coded)
        $sections[] = [
            'title'   => 'Refund Policy',
            'content' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed non risus. Suspendisse lectus tortor, dignissim sit amet, adipiscing nec, ultricies sed, dolor.</p>'
        ];

        // 2) Terms and Conditions (ACF field)
        $terms = get_field( 'terms_and_conditions' );
        if ( $terms ) {
            $sections[] = [
                'title'   => 'Terms and Conditions',
                'content' => apply_filters( 'the_content', $terms )
            ];
        }

        // 3) Late Fees (ACF field)
        $late = get_field( 'late_fees' );
        if ( $late ) {
            $sections[] = [
                'title'   => 'Late Fees',
                'content' => apply_filters( 'the_content', $late )
            ];
        }

        // 4) Payments (ACF field)
        $payments = get_field( 'payments' );
        if ( $payments ) {
            $sections[] = [
                'title'   => 'Payments',
                'content' => apply_filters( 'the_content', $payments )
            ];
        }

        // If for some reason we have no sections (shouldn't happen), bail.
        if ( empty( $sections ) ) {
            return '';
        }

        // Build the accordion shortcode
        $accordion = '[vc_tta_accordion c_icon="plus"]';
        foreach ( $sections as $sec ) {
            $slug = sanitize_title( $sec['title'] );
            $accordion .= sprintf(
                '[vc_tta_section title="%s" tab_link="%%%7B%%22url%%22%%3A%%22#%s%%22%%7D"]',
                esc_attr( $sec['title'] ),
                esc_attr( $slug )
            );
            $accordion .= '[vc_column_text]' . $sec['content'] . '[/vc_column_text]';
            $accordion .= '[/vc_tta_section]';
        }
        $accordion .= '[/vc_tta_accordion]';

        // Optionally wrap in a heading
        $output  = '<h3>Policies</h3>';
        $output .= do_shortcode( $accordion );

        return $output;
    }
}


