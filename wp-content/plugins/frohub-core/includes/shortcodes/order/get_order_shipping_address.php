<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class GetOrderShippingAddress
{

    public static function init()
    {
        $self = new self();
        add_shortcode('get_order_shipping_address', array($self, 'get_order_shipping_address_shortcode'));
    }

    public function get_order_shipping_address_shortcode()
    {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if ($order && in_array($order->get_status(), ['processing', 'completed', 'on-hold', 'rescheduling', 'cancelled'])) {
            foreach ($order->get_items() as $item) {
                $service_type = $item->get_meta('pa_service-type'); // Fetch service-type attribute

                if (!empty($service_type)) {
                    $service_type = strtolower($service_type);

                    $product_id = $item->get_product_id();
                    $partner_id = get_field('partner_id', $product_id);

                    if ($service_type === 'mobile') {
                        echo $this->render_shipping_address($order);
                    } elseif (in_array($service_type, ['salon-based', 'home-based'])) {
                        if ($partner_id) {
                            echo $this->render_partner_address($partner_id, $order->get_status());
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

    private function render_shipping_address($order)
    {
        $output = '';

        $shipping_address_1 = $order->get_shipping_address_1();
        $shipping_address_2 = $order->get_shipping_address_2();
        $shipping_city = $order->get_shipping_city();
        $shipping_state = $order->get_shipping_state();
        $shipping_postcode = $order->get_shipping_postcode();

        $output .= esc_html($shipping_address_1) . '<br>';

        if ($shipping_address_2) {
            $output .= esc_html($shipping_address_2) . '<br>';
        }

        $output .= esc_html($shipping_city) . '<br>' . esc_html($shipping_state) . ' ' . esc_html($shipping_postcode) . '<br>';

        return $output;
    }

    private function render_partner_address($partner_id, $order_status)
    {
        $output = '';

        $street_address = get_field('street_address', $partner_id);
        $city = get_field('city', $partner_id);
        $county_district = get_field('county_district', $partner_id);
        $postcode = get_field('postcode', $partner_id);

        // Statuses that should only show city + postcode
        $partial_display_statuses = ['on-hold', 'cancelled', 'rescheduling'];

        if (in_array($order_status, $partial_display_statuses)) {
            // Show only city and postcode
            $parts = array();

            if (!empty($city)) {
                $parts[] = esc_html($city);
            }

            if (!empty($postcode)) {
                $parts[] = esc_html($postcode);
            }

            if (!empty($parts)) {
                $output .= implode(', ', $parts) . '<br>';
            } else {
                $output .= '<p>Partner address information unavailable.</p>';
            }

        } elseif (in_array($order_status, ['processing', 'completed'])) {
            // Show full address with Google Maps link
            $parts = array();

            if (!empty($street_address)) {
                $parts[] = esc_html($street_address);
            }

            if (!empty($city)) {
                $parts[] = esc_html($city);
            }

            if (!empty($county_district)) {
                $parts[] = esc_html($county_district);
            }

            if (!empty($postcode)) {
                $parts[] = esc_html($postcode);
            }

            if (!empty($parts)) {
                $full_address = implode(', ', $parts);
                $output .= $full_address . '<br>';

                // Prepare a clean URL for Google Maps
                $maps_query = urlencode($full_address);
                $google_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . $maps_query;

                // Append "Open in Google Maps" link
                $output .= '<a href="' . esc_url($google_maps_url) . '" target="_blank" rel="noopener noreferrer" style="margin-top: 5px; display: inline-block;">Open in Google Maps <i class="far fa-external-link-alt"></i></a>';
            } else {
                $output .= '<p>Full partner address not available.</p>';
            }
        }

        return $output;
    }

}
