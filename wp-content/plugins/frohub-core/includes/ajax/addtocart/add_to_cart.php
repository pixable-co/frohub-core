<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AddToCart {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub_add_to_cart', array($self, 'add_to_cart'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_frohub_add_to_cart', array($self, 'add_to_cart'));

        // Apply custom price when adding to cart
        add_filter('woocommerce_add_cart_item', array($self, 'apply_custom_price'), 10, 1);
        // Restore custom meta from session
        add_filter('woocommerce_get_cart_item_from_session', array($self, 'get_cart_item_from_session'), 10, 2);
        // Display custom meta in cart and checkout
        add_filter('woocommerce_get_item_data', array($self, 'display_selected_add_ons'), 10, 2);
        // Save custom meta to order
        add_action('woocommerce_add_order_item_meta', array($self, 'add_order_item_meta'), 10, 2);
    }

    public function add_to_cart() {
        check_ajax_referer( 'frohub_nonce' );

        // Clear the cart before adding a new product
        WC()->cart->empty_cart();
        // Get data from the request
        $product_id = isset($_POST['productId']) ? sanitize_text_field($_POST['productId']) : '';
        $selected_add_ons = isset($_POST['selectedAddOns']) ? array_map(function($add_on) {
            return array(
                'id' => sanitize_text_field($add_on['id']),
                'name' => sanitize_text_field($add_on['name']),
                'price' => sanitize_text_field($add_on['price']),
                'duration_minutes' => sanitize_text_field($add_on['duration_minutes']),
            );
        }, $_POST['selectedAddOns']) : array();
        $product_price = isset($_POST['productPrice']) ? sanitize_text_field($_POST['productPrice']) : 0;
        $total_price = isset($_POST['totalPrice']) ? sanitize_text_field($_POST['totalPrice']) : 0;
        $deposit_due = isset($_POST['depositDue']) ? sanitize_text_field($_POST['depositDue']) : 0;
        $deposit_due_today = isset($_POST['depositDueToday']) ? sanitize_text_field($_POST['depositDueToday']) : 0;
        $service_fee = isset($_POST['serviceFee']) ? sanitize_text_field($_POST['serviceFee']) : 0;
        $selected_service_type = isset($_POST['selectedServiceType']) ? sanitize_text_field($_POST['selectedServiceType']) : '';
        $selected_date = isset($_POST['selectedDate']) ? sanitize_text_field($_POST['selectedDate']) : '';
        $selected_time = isset($_POST['selectedTime']) ? sanitize_text_field($_POST['selectedTime']) : '';

        // Add product to cart with custom meta
        $cart_item_data = array(
            'selected_add_ons' => $selected_add_ons,
            'custom_price' => $product_price,
            'deposit_due' => $deposit_due,
            'service_fee' => $service_fee,
            'selected_service_type' => $selected_service_type, // Add service type to cart item data
            'booking_date' => $selected_date,
            'booking_time' => $selected_time,
        );


       $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

        // Frohub Service Fee
       $additional_product_id = 2600;
       $base_price = $this->get_product_price($additional_product_id);
       $percentage = $base_price / 100;
       $secondary_product_price = $total_price * $percentage;

       $secondary_cart_item_data = array(
               'custom_price' => $secondary_product_price
       );
        $secondary_cart_item_key = WC()->cart->add_to_cart($additional_product_id, 1, 0, array(), $secondary_cart_item_data);


        if ($cart_item_key && $secondary_cart_item_key) {
            // Prepare response data
            $response = array(
                'message' => 'Product added to cart successfully.',
                'product_id' => $product_id,
                'selected_add_ons' => $selected_add_ons,
                'product_price' => $product_price,
                'deposit_due' => $deposit_due,
                'service_fee' => $service_fee,
                'selected_service_type' => $selected_service_type,
                'booking_date' => $selected_date,
                'booking_time' => $selected_time,
                'secondary_product_price' => $secondary_product_price,
            );

            // Send success response
            wp_send_json_success($response);
        } else {
            // Send error response
            wp_send_json_error(array('message' => 'Failed to add product to cart.'));
        }
    }

    public function format_date($date) {
        if (!$date) return ''; // Return empty string if no date

        $date_obj = date_create_from_format('Y-m-d', $date);
        return $date_obj ? date_format($date_obj, 'jS F Y') : $date; // Convert to readable format for frontend
    }

    private function get_product_price($product_id) {
        $product = wc_get_product($product_id);
        return $product ? floatval($product->get_price()) : 0;
    }

    public function apply_custom_price($cart_item) {
        // Apply custom price to cart item
        if (isset($cart_item['custom_price'])) {
            $cart_item['data']->set_price($cart_item['custom_price']);
            $cart_item['data']->set_regular_price($cart_item['custom_price']);
            $cart_item['data']->set_sale_price('');
        }
        return $cart_item;
    }

    public function get_cart_item_from_session($cart_item, $values) {
        // Restore custom price from session
        if (isset($values['custom_price'])) {
            $cart_item['custom_price'] = $values['custom_price'];
            $cart_item['data']->set_price($values['custom_price']);
            $cart_item['data']->set_regular_price($values['custom_price']);
            $cart_item['data']->set_sale_price('');
        }
        // Restore selected add-ons from session
        if (isset($values['selected_add_ons'])) {
            $cart_item['selected_add_ons'] = $values['selected_add_ons'];
        }
        // Restore selected service type from session
        if (isset($values['selected_service_type'])) {
            $cart_item['selected_service_type'] = $values['selected_service_type'];
        }
        if (isset($values['booking_date'])) { // Restore date
                $cart_item['booking_date'] = $values['booking_date'];
        }
        if (isset($values['booking_time'])) { // Restore time
            $cart_item['booking_time'] = $values['booking_time'];
        }
        return $cart_item;
    }

    public function display_selected_add_ons($item_data, $cart_item) {
        // Display selected add-ons in cart and checkout
        if (isset($cart_item['selected_add_ons']) && ! empty($cart_item['selected_add_ons'])) {
            $add_ons = array_map(function($add_on) {
                return $add_on['name'];
            }, $cart_item['selected_add_ons']);
            
            // Convert array to comma-separated string
            $add_ons_string = implode(', ', $add_ons);
            $item_data[] = array(
                'name' => __('Selected Add-Ons', 'frohub'),
                'value' => $add_ons_string,
            );
        }

        if (isset($cart_item['deposit_due']) && !empty($cart_item['deposit_due'])) {
                $item_data[] = array(
                    'name' => __('Total due on day', 'frohub'),
                    'value' => '£' . number_format($cart_item['deposit_due'], 2),
                );
        }

        // Display selected service type in cart and checkout
        if (isset($cart_item['selected_service_type']) && ! empty($cart_item['selected_service_type'])) {
            $item_data[] = array(
                'name' => __('Service Type', 'frohub'),
                'value' => ucfirst($cart_item['selected_service_type']),
            );
        }

        $formatted_date = $this->format_date($cart_item['booking_date']);
        if (isset($cart_item['booking_date']) && !empty($cart_item['booking_date'])) {
                $item_data[] = array(
                    'name' => __('Selected Date', 'frohub'),
                    'value' => $formatted_date,
                );
        }
        if (isset($cart_item['booking_time']) && !empty($cart_item['booking_time'])) { // Display time
                $item_data[] = array(
                    'name' => __('Selected Time', 'frohub'),
                    'value' => $cart_item['booking_time'],
                );
        }
        return $item_data;
    }

    public function add_order_item_meta($item_id, $values) {
        $order_id = wc_get_order_id_by_order_item_id($item_id); // Get the Order ID from item ID

        // Save selected add-ons to order meta
        if (isset($values['selected_add_ons'])) {
            $add_ons = array_map(function($add_on) {
                return $add_on['name'];
            }, $values['selected_add_ons']);
            wc_add_order_item_meta($item_id, 'Selected Add-Ons', implode(', ', $add_ons));
        }

        if (isset($values['deposit_due'])) {
            wc_add_order_item_meta($item_id, 'Total due on day', $values['deposit_due']);
        }

        if (isset($values['service_fee'])) {
            wc_add_order_item_meta($order_id, '_frohub_service_fee', number_format($values['service_fee'], 2)); // ✅ Save service fee as hidden order meta
        }

        // Save selected service type to order meta
        if (isset($values['selected_service_type'])) {
            wc_add_order_item_meta($item_id, 'Service Type', $values['selected_service_type']);
        }
        if (isset($values['booking_date'])) { // Save date
                wc_add_order_item_meta($item_id, 'Selected Date', $values['booking_date']);
        }
        if (isset($values['booking_time'])) { // Save time
               wc_add_order_item_meta($item_id, 'Selected Time', $values['booking_time']);
        }
    }
}