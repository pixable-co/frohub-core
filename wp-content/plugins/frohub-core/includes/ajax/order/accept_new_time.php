<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AcceptNewTime {

    public static function init() {
        $self = new self();
        add_action('wp_ajax_accept_new_time', array($self, 'accept_new_time'));
        add_action('wp_ajax_nopriv_accept_new_time', array($self, 'accept_new_time'));
    }

    public function accept_new_time() {
        check_ajax_referer('ajax_nonce', 'security');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(['message' => 'Error: Missing order ID!']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Error: Order not found!']);
        }

        $webhook_customer = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e9a2d77f2205d933fefdfa16e52cdd5f.a6502b087ab6174b0c59f7c3f1c586bd&isdebug=false';

        wp_remote_post($webhook_customer, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => sendPayloadToZohoFlowPayload($order_id),
        ]);

        $webhook_partner = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.65e0e4740df7fca9b365732f476c2f0e.5dfae26de7e36dc015ff1c4819f8188e&isdebug=false';

        wp_remote_post($webhook_partner, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => sendPayloadToZohoFlowPayload($order_id),
        ]);

        wp_send_json_success([
            'message'     => 'Appointment confirmed successfully! Order is now processing.',
            'start_time'  => $formatted_start_datetime,
            'end_time'    => $formatted_end_datetime
        ]);
    }
}
