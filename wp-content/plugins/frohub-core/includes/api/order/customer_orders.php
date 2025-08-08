<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerOrders {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/customer-orders', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request to retrieve customer orders filtered by partner.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $customer_id = $request->get_param('customer_id');
        $partner_id  = $request->get_param('partner_id');

        if ( ! $customer_id || ! get_userdata($customer_id) ) {
            return new \WP_REST_Response(['error' => 'Invalid customer ID'], 400);
        }
        if ( ! $partner_id ) {
            return new \WP_REST_Response(['error' => 'Missing partner_id'], 400);
        }

        // Fetch all orders for this customer; we'll filter at item level.
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'limit'       => -1,
        ]);

        if (empty($orders)) {
            return new \WP_REST_Response(['message' => 'No orders found'], 200);
        }

        $order_data = [];

        foreach ($orders as $order) {
            // Get only items that belong to this partner
            $partner_items = $this->get_order_items_for_partner($order, $partner_id);

            // If the order has no items for this partner, skip it entirely
            if (empty($partner_items)) {
                continue;
            }

            // Compute a per-partner total for the order (sum of item subtotals + "Total Due on the Day")
            $partner_total = $this->calculate_partner_total($partner_items);

            $order_id = $order->get_id();

            // Existing review lookup on the order
            $review     = get_field('review', $order_id);
            $review_id  = is_object($review) ? $review->ID : null;
            $overall_rating = $review_id ? get_field('overall_rating', $review_id) : null;

            $order_data[] = [
                'order_id'        => $order_id,
                'status'          => $order->get_status(),
                // important: total shown is for this partner only
                'partner_total'   => wc_price($partner_total),
                'order_date'      => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                'items'           => $partner_items, // only items for this partner
                'overall_rating'  => $overall_rating ?: null,
            ];
        }

        if (empty($order_data)) {
            // Customer has orders, but none with this partner
            return new \WP_REST_Response(['message' => 'No orders found for this partner'], 200);
        }

        return new \WP_REST_Response($order_data, 200);
    }

    /**
     * Return items from an order that belong to the given partner, excluding product 28990.
     *
     * @param \WC_Order $order
     * @param string|int $partner_id
     * @return array
     */
    private function get_order_items_for_partner($order, $partner_id) {
        $items = [];
        $partner_id = (string) $partner_id;

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_id = $product ? $product->get_id() : null;

            // Skip Frohub Booking Fee
            if ((int) $product_id === 28990) {
                continue;
            }

            // Detect partner on the line item; prefer 'partner_id', fallback to 'partner_name'
            $item_partner = $item->get_meta('partner_id', true);
            if ($item_partner === '' || $item_partner === null) {
                $item_partner = $item->get_meta('partner_name', true);
            }

            if ((string) $item_partner !== $partner_id) {
                continue;
            }

            // Collect item meta as key => value
            $meta_data = [];
            foreach ($item->get_meta_data() as $meta) {
                $meta_data[$meta->key] = $meta->value;
            }

            $items[] = [
                'product_id'   => $product_id,
                'product_name' => $item->get_name(),
                'quantity'     => (int) $item->get_quantity(),
                // per-line subtotal (ex tax, before coupons)
                'total_paid'   => wc_price((float) $item->get_subtotal()),
                'meta_data'    => $meta_data,
            ];
        }

        return $items;
    }

    /**
     * Sum partner-specific totals from a list of items: subtotal + "Total Due on the Day" if present.
     *
     * @param array $items Array from get_order_items_for_partner()
     * @return float
     */
    private function calculate_partner_total(array $items) {
        $sum = 0.0;

        foreach ($items as $item) {
            // 'total_paid' here is formatted via wc_price; we should use raw values.
            // So instead of trusting 'total_paid', recompute from meta_data safely:
            $line_subtotal = 0.0;
            if (isset($item['meta_data']['_line_subtotal'])) {
                // sometimes Woo stores these hidden props — but not always exposed
                $line_subtotal = (float) $item['meta_data']['_line_subtotal'];
            } else {
                // Fallback: parse from formatted 'total_paid'
                $line_subtotal = (float) preg_replace('/[^\d.]/', '', (string) $item['total_paid']);
            }
            $sum += $line_subtotal;

            // Add "Total Due on the Day" if present
            if (isset($item['meta_data']['Total Due on the Day'])) {
                $clean = preg_replace('/[^\d.]/', '', (string) $item['meta_data']['Total Due on the Day']);
                if ($clean !== '' && is_numeric($clean)) {
                    $sum += (float) $clean;
                }
            }
        }

        return $sum;
    }
}
