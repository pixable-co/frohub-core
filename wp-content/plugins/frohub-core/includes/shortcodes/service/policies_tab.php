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

echo '<h3>Deposit Refund Policy</h3>';
echo '<p>At FroHub, we have a deposit refund policy in place to protect both our stylists and clients, ensuring a fair experience for everyone.</p>';

echo '<p><strong>Cancellations up to 7 Days Before Appointment:</strong> If a client cancels their booking at least 7 days before the scheduled appointment, they will receive a full refund of their deposit. However, the booking fee is non-refundable.</p>';

echo '<p><strong>Cancellations Within 7 Days:</strong> If a client cancels their booking within 7 days of the scheduled appointment, or if the booking was made less than 7 days in advance, the client is not eligible for a refund of either the deposit or the booking fee.</p>';

echo '<p><strong>If the Stylist Cancels:</strong> If the stylist cancels the appointment for any reason, the client will be refunded all payments made, including both the deposit and the booking fee.</p>';

echo '<p><strong>Why the 7-Day Notice?</strong><br />
The 7-day cancellation policy allows our stylists to fill their time slot with another client, reducing the financial impact of last-minute cancellations. We ask that clients keep this in mind when making bookings, as it helps stylists manage their schedules and maintain availability for all clients.</p>';

        if ($terms_conditions || $late_fees || $payments) {
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
        } else {
            echo '<i><strong>No other policies listed. We recommend messaging the stylist if you need more information before booking.</strong></i>';
        }

        
        echo '</div>';

        return ob_get_clean();
    }
}
