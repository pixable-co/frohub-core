<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PoliciesTab {

    public static function init() {
        $self = new self();
        add_shortcode( 'policies_tab', array($self, 'policies_tab_shortcode') );
    }

    public function policies_tab_shortcode() {
        ob_start();

        $product_id = get_the_ID();
        $partner_id = get_field('partner_id', $product_id);

        // Get policies field
        $terms_conditions = get_field('terms_and_conditions', $partner_id);
        $late_fees = get_field('late_fees', $partner_id);
        $payments = get_field('payments', $partner_id);

        echo '<div class="partner-policies">';

        if ($terms_conditions) {
            echo '<h3>Terms & Conditions</h3>';
            echo '<p>' . nl2br(esc_html($terms_conditions)) . '</p>';
        }

        if ($late_fees) {
            echo '<h3>Late Fees</h3>';
            echo '<p>' . nl2br(esc_html($late_fees)) . '</p>';
        }

        if ($payments) {
            echo '<h3>Payments</h3>';
            echo '<p>' . nl2br(esc_html($payments)) . '</p>';
        }

        echo '</div>';

        return ob_get_clean();
    }
}
