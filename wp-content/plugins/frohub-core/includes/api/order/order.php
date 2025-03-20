<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Order {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/order/(?P<order_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_full_order_data'),
            'permission_callback' => '__return_true', // ⚠️ Be careful, this makes it public!
        ));
    }

    /**
     * Retrieves full order details including payouts and items.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_full_order_data(\WP_REST_Request $request) {
        $order_id = intval($request['order_id']);

        if (!$order_id) {
            return new \WP_Error('no_order_id', 'No order ID provided', ['status' => 400]);
        }


        wc_delete_shop_order_transients($order_id); // Clears cached order data
        wp_cache_delete($order_id, 'orders');

        $order = wc_get_order($order_id);

        if (!$order || !is_a($order, 'WC_Order')) {
            return new \WP_Error('invalid_order', 'Invalid order ID', ['status' => 404]);
        }

        // Get order data
        $order_data = $order->get_data();
        $order_meta = get_post_meta($order_id);
        $customer_note = $order->get_customer_note();

        // Fetch customer's phone number
        $customer_phone = $order_data['billing']['phone'] ?? '';

        // Fetch ACF fields: "conversation" and "partner_name"
        $conversation_id = get_field('conversation', $order_id) ?? 'N/A';
        $partner_conversation_id = get_field('partner_client_post_id',$conversation_id);

        $partner_id = get_field('partner_name', $order_id);
        $town  = get_field('town',$partner_id);    
        $streetAddress  = get_field('street_address',$partner_id);    
        $countyDistrict = get_field('county_district',$partner_id);    
        $postcode = get_field('postcode',$partner_id);        

        $review = get_field('review', $order_id) ?? 'No review available';

        // Get items and their meta
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $item_meta = wc_get_order_item_meta($item_id, '', true);
            $service_fee = get_field('service_price', $item->get_product_id());

            $items[] = [
                'item_id'     => $item_id,
                'product_id'  => $item->get_product_id(),
                'name'        => $item->get_name(),
                'service_fee' => $service_fee,
                'quantity'    => $item->get_quantity(),
                'subtotal'    => $item->get_subtotal(),
                'total'       => $item->get_total(),
                'meta'        => $item_meta
            ];
        }

        // Get payout data
        $payouts = $this->get_payout_data($order_id);

        // Get the customer ID from the order
        $customer_id = $order_data['customer_id'];

        $customer_first_name = '';
        $customer_last_name = '';

        if ($customer_id) {
        $user_info = get_userdata($customer_id);
        if ($user_info) {
            $customer_first_name = $user_info->first_name;
            $customer_last_name = $user_info->last_name;
        }
        }

        return new \WP_REST_Response([
        'order_id'      => $order_id,
        'status'     => $order->get_status(),
        'created'       => $order_data['date_created']->date('Y-m-d H:i:s'),
        'total'         => $order_data['total'],
        'currency'      => $order_data['currency'],
        'customer_id'   => $customer_id,
        'customer_first_name' => $customer_first_name, // ✅ From WP user profile
        'customer_last_name'  => $customer_last_name,  // ✅ From WP user profile
        'customer_note' => $customer_note,
        'customer_phone'=> $customer_phone,
        'conversation_id'=> $partner_conversation_id,
        'partner_street_address' => $streetAddress,
        'partner_town' => $town,
        'partner_countydistrict' => $countyDistrict,
        'partner_postcode' => $postcode,
        'billing'       => $order_data['billing'],
        'shipping'      => $order_data['shipping'],
        'items'         => $items,
        'meta'          => $order_meta,
        'payout'        => $payouts,
        'review'        => $review
        ], 200);

    }

    /**
     * Retrieves payout data associated with an order.
     *
     * @param int $order_id
     * @return array
     */
    private function get_payout_data($order_id) {
        $args = [
            'post_type'  => 'payout',
            'meta_query' => [
                [
                    'key'     => 'order',
                    'value'   => $order_id,
                    'compare' => '='
                ]
            ]
        ];

        $payout_query = new \WP_Query($args);
        $payouts = [];

        if ($payout_query->have_posts()) {
            while ($payout_query->have_posts()) {
                $payout_query->the_post();
                $payouts[] = [
                    'appointment_date_time' => get_field('appointment_date_time'),
                    'deposit'               => get_field('deposit'),
                    'commission'            => get_field('frohub_commission'),
                    'payout_amount'         => get_field('payout_amount'),
                    'scheduled_date'        => get_field('scheduled_date'),
                    'payout_date'           => get_field('payout_date'),
                    'payout_status'         => get_field('payout_status'),
                    'stripe_payment_id'     => get_field('stripe_payment_id'),
                ];
            }
            wp_reset_postdata();
        }

        return $payouts;
    }
}

