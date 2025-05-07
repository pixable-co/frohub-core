<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EarlyCancelOrder {

    public static function init() {
        $self = new self();
        add_action('wp_ajax_early_cancel_order', array($self, 'early_cancel_order'));
        add_action('wp_ajax_nopriv_early_cancel_order', array($self, 'early_cancel_order'));
    }

    public function early_cancel_order() {
        check_ajax_referer('ajax_nonce', 'security');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error(['message' => 'Order ID is missing.']);
        }

        $cancellation_reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        $cancellation_other_text = isset($_POST['other_reason']) ? sanitize_textarea_field($_POST['other_reason']) : '';


        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order.']);
        }

        $line_items = $order->get_items();
        $refund_total = 0;
        $refund_items = [];

        foreach ($line_items as $item_id => $item) {
            $product_id = $item->get_product_id();
            $item_total = $item->get_total();

            if ($product_id != 28990) {
                $refund_items[$item_id] = [
                    'qty'          => $item->get_quantity(),
                    'refund_total' => $item_total
                ];
                $refund_total += $item_total;
            }
        }

        if ($refund_total > 0) {
            $refund = wc_create_refund([
                'amount'         => $refund_total,
                'reason'         => 'Early Cancellation Refund (excluding non-refundable item)',
                'order_id'       => $order_id,
                'line_items'     => $refund_items,
                'refund_payment' => true,
                'restock_items'  => true,
            ]);

            if (is_wp_error($refund)) {
                wp_send_json_error([
                    'message' => 'Refund failed: ' . $refund->get_error_message()
                ]);
            }
        }

        $order->update_status('cancelled', 'Order cancelled with partial refund');
        update_field('cancellation_status', 'Early Cancellation', $order_id);
        update_field('cancellation_reason', $cancellation_reason, $order_id);
        update_field('cancellation_other_reason_text', $cancellation_other_text, $order_id);


        // Data collection
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();
        $client_notes = $order->get_customer_note();
        $customer_shipping_address = $order->get_formatted_shipping_address();

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


        $webhook_customer = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.c4690a5e3f8614af33586949f0a712a6.727222d689cd2a034af750b2ac127495&isdebug=false';

        wp_remote_post($webhook_customer, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode(sendPayloadToZohoFlowPayload($order_id)),
        ]);


        $webhook_partner = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.9dc9d8e2982ee05fb07c6c2558b9811c.42d319bfe73b89e2f314888d692ea277&isdebug=false';

        wp_remote_post($webhook_partner, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode(sendPayloadToZohoFlowPayload($order_id)),
        ]);

        wp_send_json_success([
            'message' => 'Order cancelled successfully. Refund issued except for product ID 28990.'
        ]);
    }
}
