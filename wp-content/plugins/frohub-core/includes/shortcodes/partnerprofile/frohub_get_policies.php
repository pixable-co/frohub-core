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
        // 1) Build an array of sections
        $sections = [];

        // always include the hard‑coded Refund Policy
        $deposit_refund = get_field( 'deposit_refund_policy', 'option' );
        if ( $deposit_refund ) {
            $sections[] = [
                'title'   => 'Deposit Refund Policy',
                'content' => apply_filters( 'the_content', $deposit_refund )
            ];
        }


        // then pull in each ACF field if it exists
        $fields = [
            'terms_and_conditions' => 'Terms and Conditions',
            'late_fees'            => 'Late Fees',
            'payments'             => 'Payments',
        ];

        foreach ( $fields as $field_name => $heading ) {
            $html = get_field( $field_name );
            if ( $html ) {
                $sections[] = [
                    'title'   => $heading,
                    'content' => apply_filters( 'the_content', $html )
                ];
            }
        }

        // if somehow we have nothing, bail
        if ( empty( $sections ) ) {
            return '';
        }

        // 2) Build the VC accordion shortcode
        $accordion = '[vc_tta_accordion c_icon="plus"]';
        foreach ( $sections as $sec ) {
            $accordion .= sprintf(
                '[vc_tta_section title="%s"]',
                esc_attr( $sec['title'] )
            );
            $accordion .= '[vc_column_text]' . $sec['content'] . '[/vc_column_text]';
            $accordion .= '[/vc_tta_section]';
        }
        $accordion .= '[/vc_tta_accordion]';

        // 3) Wrap it in an <h3> and return
        return '<h3>Policies</h3>' . do_shortcode( $accordion );
    }
}