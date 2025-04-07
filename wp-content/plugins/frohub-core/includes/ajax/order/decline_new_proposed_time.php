<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class DeclineNewProposedTime {

    public static function init() {
        $self = new self();
        add_action('wp_ajax_decline_new_proposed_time', array($self, 'decline_new_proposed_time'));
        add_action('wp_ajax_nopriv_decline_new_proposed_time', array($self, 'decline_new_proposed_time'));
    }

    public function decline_new_proposed_time() {
        check_ajax_referer('ajax_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(['message' => 'Error: Missing order ID!']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Error: Order not found!']);
        }

        // Remove proposed time meta
        foreach ($order->get_items() as $item_id => $item) {
            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        // Cancel order
        $order->update_status('cancelled', 'Order has been cancelled. Proposed Time declined by customer.');
        $order->save();

        // Update ACF
        update_field('cancellation_status', 'Declined by Client', $order_id);

        // Extract client and booking details
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();
        $partner_name = '';
        $partner_email = '';
        $service_name = '';
        $booking_date_time = '';

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if ($product_id == 28990) continue;

            $raw_service_name = $item->get_name();
            $service_name_parts = explode(' - ', $raw_service_name);
            $service_name = trim($service_name_parts[0]);

            $partner_post = get_field('partner_name', $product_id);
            if ($partner_post && is_object($partner_post)) {
                $partner_name = get_the_title($partner_post->ID);
                $partner_email = get_field('partner_email', $partner_post->ID);
            }

            $booking_date_time = wc_get_order_item_meta($item->get_id(), 'Start Date Time', true);
            break;
        }

        // 🔹 Payload 1: emailSentToCustomer
        $payload_customer = json_encode([
            'order_id' => $order_id,
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
        ]);

        $webhook_customer = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e14c3511f867358f97a4ffc2340ef099.302bd10e4c7fa5fe9841309126bcb1dc&isdebug=false';

        wp_remote_post($webhook_customer, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $payload_customer,
        ]);

        // 🔹 Payload 2: emailSentToPartner
        $payload_partner = json_encode([
            'order_id' => '#' . $order_id,
            'partner_email' => $partner_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'booking_date_time' => $booking_date_time,
        ]);

        $webhook_partner = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e834dd56f30691cb23c33225e8711d1e.93f350c42e6622f53ee3a390064d6ca1&isdebug=false';

        wp_remote_post($webhook_partner, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $payload_partner,
        ]);

        wp_send_json_success(['message' => 'Order has been cancelled.']);
    }
}
