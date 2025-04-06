<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreateUpdatePayoutPost {

    public static function init() {
        $self = new self();
        add_action('woocommerce_order_status_changed', array($self, 'create_or_update_payout_post_on_order_processing'), 10, 3);
    }

    public function create_or_update_payout_post_on_order_processing($order_id, $old_status, $new_status) {
        if ($new_status !== 'processing') {
            return;
        }

        $acf_payout_status = 'payout_status';
        $acf_order_field = 'order';
        $acf_partner_field = 'partner_name';
        $acf_appointment_field = 'appointment_date_time';
        $acf_deposit_field = 'deposit';
        $acf_commission_field = 'frohub_commission';
        $acf_stripe_field = 'stripe_fee';
        $acf_payout_amount_field = 'payout_amount';
        $acf_scheduled_date_field = 'scheduled_date';

        $partner_name = get_field($acf_partner_field, $order_id);
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $deposit_total = 0;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if ($product_id != 28990) {
                $deposit_total = $item->get_total();
                $total_due_raw = $item->get_meta('Total Due on the Day', true);
                $total_due = (float) preg_replace('/[^0-9.]/', '', $total_due_raw);
                $start_date_time_raw = $item->get_meta('Start Date Time', true);
                $end_date_time_raw = $item->get_meta('End Date Time', true);
                break;
            }
        }

        $appointment_datetime = null;
        $scheduled_date = null;

        if (!empty($start_date_time_raw)) {
            $start_date_object = \DateTime::createFromFormat('H:i, d M Y', $start_date_time_raw);
            if ($start_date_object) {
                $appointment_datetime = $start_date_object->format('Y-m-d H:i:s');
            }
        }

        if (!empty($end_date_time_raw)) {
            $end_date_object = \DateTime::createFromFormat('H:i, d M Y', $end_date_time_raw);
            if ($end_date_object) {
                $end_date_object->modify('+5 days');
                $scheduled_date = $end_date_object->format('Y-m-d');
            }
        }

        $total_service_price = (float) $deposit_total + (float) $total_due;
        $commission_percentage = 0.07;
        $frohub_commission = $total_service_price * $commission_percentage;
        $stripe_fee = (float) get_post_meta($order_id, '_stripe_fee', true);
        $commission_total = (float) $frohub_commission + (float) $stripe_fee;
        $payout_amount = $deposit_total - $commission_total;

        if ($payout_amount < 0) {
            $payout_amount = 0;
        }

        $existing_payout = get_posts([
            'post_type'   => 'payout',
            'title'       => '#' . $order_id,
            'post_status' => 'any',
            'numberposts' => 1,
        ]);

        if (!empty($existing_payout)) {
            $payout_id = $existing_payout[0]->ID;
            wp_update_post([
                'ID'           => $payout_id,
                'post_content' => 'Updated payout for order #' . $order_id,
            ]);
        } else {
            $payout_id = wp_insert_post([
                'post_title'   => '#' . $order_id,
                'post_type'    => 'payout',
                'post_status'  => 'publish',
                'post_content' => 'Payout generated for order #' . $order_id,
            ]);
        }

        if ($payout_id && !is_wp_error($payout_id)) {
            update_post_meta($order_id, 'payout_post', $payout_id);
            update_field($acf_payout_status, 'Draft', $payout_id);
            update_field($acf_order_field, $order_id, $payout_id);

            if ($partner_name) {
                update_field($acf_partner_field, $partner_name, $payout_id);
            }

            if ($appointment_datetime) {
                update_field($acf_appointment_field, $appointment_datetime, $payout_id);
            }

            update_field($acf_deposit_field, $deposit_total, $payout_id);
            update_field($acf_commission_field, $frohub_commission, $payout_id);
            update_field($acf_stripe_field, $stripe_fee, $payout_id);
            update_field($acf_payout_amount_field, $payout_amount, $payout_id);

            if ($scheduled_date) {
                update_field($acf_scheduled_date_field, $scheduled_date, $payout_id);
            }
        }
    }
}
