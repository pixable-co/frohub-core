<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StylistCancelOrder {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/stylist-cancel-order', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'order_id' => array(
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && intval($param) > 0;
                    },
                ),
            ),
        ));
    }

    /**
     * Handles the API request to cancel the order.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $order_id = intval($request->get_param('order_id'));

        $order = wc_get_order($order_id);
        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid order ID. Order not found.',
            ], 404);
        }
    
        $current_status = $order->get_status();
        if (in_array($current_status, ['cancelled', 'completed'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Order #$order_id cannot be cancelled because it is already '$current_status'.",
            ], 400);
        }
    
        // Pull client data
        $client_email = $order->get_billing_email();
        $client_first_name = $order->get_billing_first_name();
        $client_notes = $order->get_customer_note();
        $customer_shipping_address = $order->get_formatted_shipping_address();
    
        // Cancel order and update ACF field
        $order->update_status('cancelled', 'Order declined via API by Stylist.');
        update_field('cancellation_status', 'Declined by Stylist', $order_id);
        $order->save();
    
        // Initialize variables
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
    
            // Deposit and total
            $line_total = $item->get_total();
            $deposit += $line_total;
    
            $total_due_raw_string = $item->get_meta('Total Due on the Day', true);
            $total_due_raw = floatval(preg_replace('/[^\d.]/', '', $total_due_raw_string));
            $total_service_fee += $line_total + $total_due_raw;
    
            // Service name
            $raw_service_name = $item->get_name();
            $service_name_parts = explode(' - ', $raw_service_name);
            $service_name = trim($service_name_parts[0]);
    
            // Partner info
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
    
            // Booking date/time
            $selected_date_time = wc_get_order_item_meta($item->get_id(), 'Start Date Time', true);
            $formatted_date_time = !empty($selected_date_time)
                ? date('H:i, d M Y', strtotime($selected_date_time))
                : '';
    
            // Addons
            $selected_addons = wc_get_order_item_meta($item->get_id(), 'Selected Add-Ons', true);
            if (!empty($selected_addons)) {
                if (is_array($selected_addons)) {
                    $addons = array_merge($addons, $selected_addons);
                } else {
                    $addons = array_merge($addons, explode(', ', $selected_addons));
                }
            }
    
            // Service type
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
    
        // 🔹 Payload 1: Email sent to customer
        $payload_customer = json_encode([
            'client_email' => $client_email,
            'client_first_name' => $client_first_name,
            'partner_name' => $partner_name,
            'service_name' => $service_name,
            'booking_date_time' => $selected_date_time,
        ]);
    
        $webhook_customer = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.7f07c99121431dc8e17958ee0dc60a2b.9bdaa8eccc2446b091e2a4eb82f79ee5&isdebug=false';
    
        wp_remote_post($webhook_customer, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $payload_customer,
        ]);
    
        // 🔹 Payload 2: Email sent to partner
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
    
        $webhook_partner = 'https://flow.zoho.eu/20103370577/flow/webhook/incoming?zapikey=1001.e85edd3739cd913ffca51143ac9aa5a1.538447985a4881ac6b52f90d84529583&isdebug=false';
    
        wp_remote_post($webhook_partner, [
            'method'  => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $payload_partner,
        ]);
    
        return new \WP_REST_Response([
            'success'             => true,
            'message'             => "Order #$order_id has been successfully declined and cancelled.",
            'order_id'            => $order_id,
            'cancellation_status' => 'Declined by Stylist',
        ], 200);
    }
}
