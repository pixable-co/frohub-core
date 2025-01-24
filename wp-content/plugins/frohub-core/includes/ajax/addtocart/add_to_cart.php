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
        // Verify nonce
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'frohub_nonce') ) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Get data from the request
        $product_id = isset($_POST['productId']) ? sanitize_text_field($_POST['productId']) : '';
        $selected_add_ons = isset($_POST['selectedAddOns']) ? $_POST['selectedAddOns'] : array();
        $product_price = isset($_POST['productPrice']) ? sanitize_text_field($_POST['productPrice']) : 0;

        // Add product to cart with custom meta
        $cart_item_data = array(
            'selected_add_ons' => $selected_add_ons,
            'custom_price' => $product_price,
        );

        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

        if ($cart_item_key) {
            $response = array(
                'message' => 'Product added to cart successfully.',
                'product_id' => $product_id,
                'selected_add_ons' => $selected_add_ons,
                'product_price' => $product_price,
            );

            wp_send_json_success($response);
        } else {
            wp_send_json_error(array('message' => 'Failed to add product to cart.'));
        }
    }

    public function apply_custom_price($cart_item) {
        if (isset($cart_item['custom_price'])) {
            $cart_item['data']->set_price($cart_item['custom_price']);
        }
        return $cart_item;
    }

    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['custom_price'])) {
            $cart_item['custom_price'] = $values['custom_price'];
            $cart_item['data']->set_price($values['custom_price']);
        }
        if (isset($values['selected_add_ons'])) {
            $cart_item['selected_add_ons'] = $values['selected_add_ons'];
        }
        return $cart_item;
    }

    public function display_selected_add_ons($item_data, $cart_item) {
        if (isset($cart_item['selected_add_ons']) && ! empty($cart_item['selected_add_ons'])) {
            $item_data[] = array(
                'name' => __('Selected Add-Ons', 'frohub'),
                'value' => implode(', ', $cart_item['selected_add_ons']),
            );
        }
        return $item_data;
    }

    public function add_order_item_meta($item_id, $values) {
        if (isset($values['selected_add_ons'])) {
            wc_add_order_item_meta($item_id, 'Selected Add-Ons', implode(', ', $values['selected_add_ons']));
        }
    }
}

// Initialize the AddToCart
AddToCart::init();
