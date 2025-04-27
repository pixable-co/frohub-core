<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderShippingAddress {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_shipping_address', array($self, 'get_order_shipping_address_shortcode') );
    }

    public function get_order_shipping_address_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if ($order && in_array($order->get_status(), ['processing', 'completed', 'on-hold'])) {
            foreach ($order->get_items() as $item) {
                $service_type = $item->get_meta('pa_service-type'); // Fetching service-type attribute
                
                if (!empty($service_type)) {
                    $service_type = strtolower($service_type); // just in case (uniform)
                    
                    $product_id = $item->get_product_id();
                    $partner_id = get_field('partner_id', $product_id); // ACF field on product

                    if ($service_type === 'mobile') {
                        echo $this->render_shipping_address($order);
                    } elseif (in_array($service_type, ['salon-based', 'home-based'])) {
                        if ($partner_id) {
                            echo $this->render_partner_address($partner_id);
                        } else {
                            echo '<p>No partner assigned.</p>';
                        }
                    } else {
                        echo '<p>Unknown service type selected.</p>';
                    }
                } else {
                    echo '<p>Service type not available for this item.</p>';
                }

                break; // Only one item handled
            }
        } else {
            echo '<p>Order not found or not in a displayable status.</p>';
        }

        return ob_get_clean();
    }

    private function render_shipping_address($order) {
        $output = '';

        $shipping_address_1 = $order->get_shipping_address_1();
        $shipping_address_2 = $order->get_shipping_address_2();
        $shipping_city = $order->get_shipping_city();
        $shipping_state = $order->get_shipping_state();
        $shipping_postcode = $order->get_shipping_postcode();
        $shipping_country = $order->get_shipping_country();

        $output .= esc_html($shipping_address_1) . '<br>';

        if ($shipping_address_2) {
            $output .= esc_html($shipping_address_2) . '<br>';
        }

        $output .= esc_html($shipping_city) . '<br>' . esc_html($shipping_state) . ' ' . esc_html($shipping_postcode) . '<br>';

        return $output;
    }

    private function render_partner_address($partner_id) {
        $output = '';

        $partner_postcode = get_field('postcode', $partner_id); // Assuming Partner has 'postcode' field

        if ($partner_postcode) {
            $output .= 'Postcode: ' . esc_html($partner_postcode) . '<br>';
        } else {
            $output .= '<p>Partner postcode not available.</p>';
        }

        return $output;
    }
}
