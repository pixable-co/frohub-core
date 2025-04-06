<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DeclineNewProposedTime {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_decline_new_proposed_time', array($self, 'decline_new_proposed_time'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_decline_new_proposed_time', array($self, 'decline_new_proposed_time'));
    }

    /**
     * Handles the decline new proposed time AJAX request.
     */
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

        // Remove proposed time meta from each item
        foreach ($order->get_items() as $item_id => $item) {
            wc_delete_order_item_meta($item_id, 'Proposed Start Date Time');
            wc_delete_order_item_meta($item_id, 'Proposed End Date Time');
        }

        // Cancel the order
        $order->update_status('cancelled', 'Order has been cancelled. Proposed Time declined by customer.');
        $order->save();

        // Update ACF cancellation status
        update_field('cancellation_status', 'Declined by Client', $order_id);

        // Pull client data
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();

        foreach ($order->get_items() as $item) {

            $product_id = $item->get_product_id();
            $item_total = $item->get_total();

            if ($product_id == 28990) {

            } else {

                // Get the service name and strip after ' - '
                $raw_service_name = $item->get_name();
                $service_name_parts = explode(' - ', $raw_service_name);
                $service_name = trim($service_name_parts[0]);

                $partner_post = get_field('partner_name', $product_id);
                if ($partner_post && is_object($partner_post)) {
                    $partner_name = get_the_title($partner_post->ID);
                }
                $selected_date_time = wc_get_order_item_meta($item->get_id(), 'Start Date Time', true);
            }
        }

            // Build payload
        $payload = json_encode([
            'order_id' => $order_id,
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,

        ]);

        // Webhook URL
        $webhook_url = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e14c3511f867358f97a4ffc2340ef099.302bd10e4c7fa5fe9841309126bcb1dc&isdebug=false';

        // Send it
        wp_remote_post($webhook_url, [
            'method'    => 'POST',
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
            'body'      => $payload,
        ]);
        

        wp_send_json_success(['message' => 'Order has been cancelled.']);
    }
}
