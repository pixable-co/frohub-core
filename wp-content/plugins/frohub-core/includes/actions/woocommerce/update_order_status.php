<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdatePayoutStatus {

    public static function init() {
        $self = new self();
        add_action('woocommerce_order_status_changed', array($self, 'update_payout_status_on_order_complete'), 10, 3);
    }

    public function update_payout_status_on_order_complete($order_id, $old_status, $new_status) {
        // Ensure this runs only when an order transitions to "completed"
        if ($new_status !== 'completed') {
            return;
        }

        // Define the ACF field name
        $acf_payout_status = 'payout_status'; // ACF select field

        // Retrieve the payout post ID linked to this order
        $payout_id = get_post_meta($order_id, 'payout_post', true);

        // Ensure the payout post exists
        if ($payout_id && !is_wp_error($payout_id)) {
            // Update the 'payout_status' field to "Queued"
            update_field($acf_payout_status, 'Queued', $payout_id);
        }
    }
}
