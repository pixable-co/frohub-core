<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LateCancelOrder {

    public static function init() {
        $self = new self();

        add_action('wp_ajax_late_cancel_order', array($self, 'late_cancel_order'));
        add_action('wp_ajax_nopriv_late_cancel_order', array($self, 'late_cancel_order'));
    }

    public function late_cancel_order() {
        check_ajax_referer('ajax_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error(['message' => 'Order ID is missing.']);
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order.']);
        }

        // Cancel order without refund
        $order->update_status('cancelled', 'Order cancelled without refund due to late cancellation policy.');

        // Update ACF field
        update_field('cancellation_status', 'Late Cancellation', $order_id);

        // Pull client data
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();
        $client_notes = $order->get_customer_note();
        $customer_shipping_address = $order->get_formatted_shipping_address();

        // Init values
        $partner_name = '';
        $partner_email = '';
        $partner_address = '';
        $service_name = '';
        $selected_date_time = '';
        $formatted_date_time = '';
        $addons = [];
        $service_type = '';
        $total_service_fee = 0;
        $deposit = 0;
        $frohub_booking_fee = 0;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            if ($product_id == 28990) {
                $frohub_booking_fee += $item->get_total();
                continue;
            }

            $line_total = $item->get_total();
            $deposit += $line_total;

            $total_due_raw_string = $item->get_meta('Total Due on the Day', true);
            $total_due_raw = floatval(preg_replace('/[^\d.]/', '', $total_due_raw_string));
            $total_service_fee += $line_total + $total_due_raw;

            $raw_service_name = $item->get_name();
            $service_name_parts = explode(' - ', $raw_service_name);
            $service_name = trim($service_name_parts[0]);

            $partner_post = get_field('partner_name', $product_id);
            if ($partner_post && is_object($partner_post)) {
                $partner_name = get_the_title($partner_post->ID);
                $partner_email = get_field('partner_email', $partner_post->ID);

                $street = get_field('street_address', $partner_post->ID);
                $city = get_field('city', $partner_post->ID);
                $county = get_field('county_district', $partner_post->ID);
                $postcode = get_field('postcode', $partner_post->ID);

                $address_parts = array_filter([$street, $city, $county, $postcode]);
                $partner_address = implode(', ', $address_parts);
            }

            $selected_date_time = wc_get_order_item_meta($item->get_id(), 'Start Date Time', true);
            $formatted_date_time = !empty($selected_date_time)
                ? date('H:i, d M Y', strtotime($selected_date_time))
                : '';

            $selected_addons = wc_get_order_item_meta($item->get_id(), 'Selected Add-Ons', true);
            if (!empty($selected_addons)) {
                if (is_array($selected_addons)) {
                    $addons = array_merge($addons, $selected_addons);
                } else {
                    $addons = array_merge($addons, explode(', ', $selected_addons));
                }
            }

            $product = $item->get_product();
            if ($product && $product->is_type('variation')) {
                $variation_attributes = $product->get_attributes();
                if (isset($variation_attributes['pa_service-type'])) {
                    $service_type = ucfirst($variation_attributes['pa_service-type']);
                }
            }
        }

        $final_service_address = strtolower($service_type) === 'mobile' || empty($service_type)
            ? $customer_shipping_address
            : $partner_address;

        // 🔹 Payload 1: Email to customer
        $payload_customer = json_encode([
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'booking_date_time' => $selected_date_time,
        ]);

        $webhook_customer = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.701f1885176381453a6604fbea45ecbf.4f5f6b8dbcf659a0ed51e76e6ab66598&isdebug=false';

        wp_remote_post($webhook_customer, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $payload_customer,
        ]);

        // 🔹 Payload 2: Email to partner
        $payload_partner = json_encode([
            'order_id' => '#' . $order_id,
            'partner_email' => $partner_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'addons' => implode(', ', $addons),
            'service_type' => $service_type ?: 'Mobile',
            'booking_date_time' => $formatted_date_time,
            'total_service_fee' => '£' . number_format($total_service_fee, 2),
            'deposit' => '£' . number_format($deposit, 2),
            'balance' => '£' . number_format($total_service_fee - $deposit, 2),
            'frohub_booking_fee' => '£' . number_format($frohub_booking_fee, 2),
            'service_address' => $final_service_address,
            'client_notes' => $client_notes,
        ]);

        $webhook_partner = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.6dc353d22eac8800be330a092c5863f7.bfb2c94f7e462e288f2b18f6b164f200&isdebug=false';

        wp_remote_post($webhook_partner, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $payload_partner,
        ]);

        wp_send_json_success([
            'message' => 'Order has been cancelled. No refund issued as per the late cancellation policy.'
        ]);
    }
}
