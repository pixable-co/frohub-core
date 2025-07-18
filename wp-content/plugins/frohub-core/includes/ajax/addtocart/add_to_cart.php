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

        add_filter('woocommerce_checkout_fields', array($self, 'force_checkout_postcode'));
    }

public function add_to_cart() {
    check_ajax_referer('frohub_nonce');

    // Clear the cart before adding a new product
    WC()->cart->empty_cart();
    // Get data from the request
    $product_id = isset($_POST['productId']) ? sanitize_text_field($_POST['productId']) : '';

    // Fetch Partner ID from ACF field
    $partner_id = get_field('partner_id', $product_id);

    // Get Partner Name
    $partner_name = $partner_id ? get_the_title($partner_id) : 'Error: Partner not found';

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

    $extra_charge_raw = isset($_POST['extraCharge']) ? $_POST['extraCharge'] : '';
    $extra_charge_data = json_decode(stripslashes($extra_charge_raw), true);

    $extra_charge = isset($extra_charge_data['bookingExtra']) ? sanitize_text_field($extra_charge_data['bookingExtra']) : '';
    $mobile_travel_fee = isset($extra_charge_data['mobileFee']) ? sanitize_text_field($extra_charge_data['mobileFee']) : '';
    $post_code = isset($extra_charge_data['postCode']) ? sanitize_text_field($extra_charge_data['postCode']) : '';

    if (!empty($selected_service_type)) {
        WC()->session->set('frohub_selected_service_type', strtolower($selected_service_type));
    }

    if (!empty($post_code)) {
        WC()->session->set('frohub_forced_postcode', $post_code);
    }

    // Fetch the product and find the correct variation
    $product = wc_get_product($product_id);
    $variation_id = 0;
    $pa_size = '';
    $pa_length = '';

    if ($product && $product->is_type('variable')) {
        // ✅ Fetch `pa_size` and `pa_length` from the **parent product** (not variation)
        $pa_size = $product->get_attribute('pa_size');
        $pa_length = $product->get_attribute('pa_length');

        $variations = $product->get_available_variations();

        foreach ($variations as $variation) {
            $variation_attributes = $variation['attributes'];

            if (isset($variation_attributes['attribute_pa_service-type']) && $variation_attributes['attribute_pa_service-type'] === $selected_service_type) {
                $variation_id = $variation['variation_id'];
                break;
            }
        }
    }

    if ($variation_id) {
        // Add product to cart with custom meta
        $cart_item_data = array(
            'selected_add_ons' => $selected_add_ons,
            'custom_price' => $product_price,
            'deposit_due' => $deposit_due,
            'service_fee' => $service_fee,
            'selected_service_type' => $selected_service_type, // Add service type to cart item data
            'booking_date' => $selected_date,
            'booking_time' => $selected_time,
            'size' => $pa_size,
            'length' => $pa_length,
            'stylist_name' => $partner_name,
            'extra_charge' => $extra_charge,
            'mobile_travel_fee' => $mobile_travel_fee,
        );

        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id, array(), $cart_item_data);

        // Frohub Service Fee
        $additional_product_id = 28990;
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
                'size' => $pa_size,
                'length' => $pa_length,
            );

            // Send success response
            wp_send_json_success($response);
        } else {
            $errors = [];
        
            if (!$cart_item_key) {
                $errors[] = 'Primary product (ID: ' . $product_id . ', Variation ID: ' . $variation_id . ') failed to add to cart.';
            }
        
            if (!$secondary_cart_item_key) {
                $errors[] = 'Secondary service fee product (ID: ' . $additional_product_id . ') failed to add to cart.';
            }
        
            // Optional: include debug context for frontend developers
            $errors[] = 'Selected service type: ' . $selected_service_type;
            $errors[] = 'Selected date: ' . $selected_date;
            $errors[] = 'Selected time: ' . $selected_time;
            $errors[] = 'Size: ' . $pa_size;
            $errors[] = 'Length: ' . $pa_length;
        
            wp_send_json_error(array(
                'message' => 'Failed to add product to cart.',
                'details' => $errors
            ));
        }
    } else {
        // Send error response if no matching variation is found
        wp_send_json_error(array('message' => 'No matching variation found for the selected service type.'));
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
        if (isset($values['size'])) {
            $cart_item['size'] = $values['size'];
        }
        if (isset($values['length'])) {
            $cart_item['length'] = $values['length'];
        }
        if (isset($values['stylist_name'])) {
            $cart_item['stylist_name'] = $values['stylist_name'];
        }

        if (isset($values['extra_charge'])) {
            $cart_item['extra_charge'] = $values['extra_charge'];
        }

        if (isset($values['mobile_travel_fee'])) {
            $cart_item['mobile_travel_fee'] = $values['mobile_travel_fee'];
        }
    
        
        return $cart_item;
    }

    public function display_selected_add_ons($item_data, $cart_item) {
        // Display selected add-ons
        if (isset($cart_item['selected_add_ons']) && is_array($cart_item['selected_add_ons'])) {
            $formatted_add_ons = array_map(function($add_on) {
                $name = isset($add_on['name']) ? $add_on['name'] : '';
                $price = isset($add_on['price']) ? floatval($add_on['price']) : 0;
                return "<strong>{$name}</strong> (£" . number_format($price, 2) . ")";
            }, $cart_item['selected_add_ons']);
    
            $item_data[] = array(
                'name'    => __('Add Ons', 'frohub'),
                'value'   => implode(', ', $formatted_add_ons),
                'display' => implode(', ', $formatted_add_ons),
            );
        }
    
        // Service Type
        if (isset($cart_item['selected_service_type']) && !empty($cart_item['selected_service_type'])) {
            $service_type = ucwords(str_replace('-', ' ', $cart_item['selected_service_type']));
            $service_label = $service_type;
    
            // Append mobile fee if service is Mobile
            if (strtolower($service_type) === 'mobile' && !empty($cart_item['mobile_travel_fee'])) {
                $mobile_fee = floatval($cart_item['mobile_travel_fee']);
                $service_label .= ' (£' . number_format($mobile_fee, 2) . ')';
            }
    
            $item_data[] = array(
                'name' => __('Service Type', 'frohub'),
                'value' => $service_label,
            );
        }
    
        // Requested Date
        $formatted_date = $this->format_date($cart_item['booking_date']);
        if (!empty($cart_item['booking_date'])) {
            $item_data[] = array(
                'name' => __('Requested Date', 'frohub'),
                'value' => $formatted_date,
            );
        }
    
        // Requested Time + Extra Charge
        if (!empty($cart_item['booking_time'])) {
            $time_value = $cart_item['booking_time'];
            if (!empty($cart_item['extra_charge'])) {
                $extra = floatval($cart_item['extra_charge']);
                $time_value .= ' (£' . number_format($extra, 2) . ')';
            }
    
            $item_data[] = array(
                'name' => __('Requested Time', 'frohub'),
                'value' => $time_value,
            );
        }
    
        // Stylist
        if (!empty($cart_item['stylist_name'])) {
            $item_data[] = array(
                'name' => __('Stylist', 'frohub'),
                'value' => $cart_item['stylist_name'],
            );
        }
    
              // Total due on the day
              if (isset($cart_item['deposit_due']) && !empty($cart_item['deposit_due'])) {
                $item_data[] = array(
                    'name' => __('Total due on day', 'frohub'),
                    'value' => '£' . number_format($cart_item['deposit_due'], 2),
                );
            }
        return $item_data;
    }
    

    public function add_order_item_meta($item_id, $values) {
        $order_id = wc_get_order_id_by_order_item_id($item_id);
    
        // Save "Total Due on the Day" with 2 decimal places
        if (isset($values['deposit_due'])) {
            wc_add_order_item_meta($item_id, 'Total Due on the Day', '£' . number_format((float)$values['deposit_due'], 2));
        }
    
        // Ensure "Selected Date" and "Selected Time" exist
        if (!empty($values['booking_date']) && !empty($values['booking_time'])) {
            $selected_date = $values['booking_date'];
            $selected_time = $values['booking_time'];
    
            // Validate and split time
            if (strpos($selected_time, ' - ') !== false) {
                list($start_time, $end_time) = explode(' - ', $selected_time);
                $start_time = trim($start_time);
                $end_time = trim($end_time);
            } else {
                return; // Invalid time format, don't save
            }
    
            // Convert Start and End time to DateTime
            $start_datetime = \DateTime::createFromFormat('H:i Y-m-d', $start_time . ' ' . $selected_date);
            $end_datetime = \DateTime::createFromFormat('H:i Y-m-d', $end_time . ' ' . $selected_date);
    
            if ($start_datetime && $end_datetime) {
                // Calculate Duration
                $duration_minutes = ($end_datetime->getTimestamp() - $start_datetime->getTimestamp()) / 60;
    
                // Format output
                $start_formatted = $start_datetime->format('H:i, d M Y');
                $end_formatted = $end_datetime->format('H:i, d M Y');
    
                // Save "Start Date Time" and "End Date Time"
                wc_add_order_item_meta($item_id, 'Start Date Time', $start_formatted);
                wc_add_order_item_meta($item_id, 'End Date Time', $end_formatted);
    
                // Format "Duration"
                $hours = floor($duration_minutes / 60);
                $minutes = $duration_minutes % 60;
                $duration_string = ($hours > 0 ? "{$hours} hrs " : '') . ($minutes > 0 ? "{$minutes} mins" : '');
                wc_add_order_item_meta($item_id, 'Duration', trim($duration_string));
            }
        }
    
        // Save "Size" & "Length" if available
        if (!empty($values['size'])) {
            wc_add_order_item_meta($item_id, 'Size', ucfirst($values['size']));
        }
        if (!empty($values['length'])) {
            wc_add_order_item_meta($item_id, 'Length', ucfirst($values['length']));
        }
        if (isset($values['stylist_name']) && !empty($values['stylist_name'])) {
            wc_add_order_item_meta($item_id, 'Stylist', $values['stylist_name']);
        }

        if (isset($values['selected_add_ons']) && is_array($values['selected_add_ons'])) {
            $formatted_add_ons = array_map(function($add_on) {
                $name = isset($add_on['name']) ? $add_on['name'] : '';
                $price = isset($add_on['price']) ? floatval($add_on['price']) : 0;
                return "<strong>{$name}</strong> (£" . number_format($price, 2) . ")";
            }, $values['selected_add_ons']);

            $formatted_string = implode(', ', $formatted_add_ons);

            wc_add_order_item_meta($item_id, 'Selected Add Ons', $formatted_string);
        }

        if (!empty($values['extra_charge'])) {
            wc_add_order_item_meta($item_id, 'Extra Charge', '£' . number_format((float)$values['extra_charge'], 2));
        }

        if (!empty($values['mobile_travel_fee'])) {
            wc_add_order_item_meta($item_id, 'Mobile Travel Fee', '£' . number_format((float)$values['mobile_travel_fee'], 2));
        }
    }


    public function force_checkout_postcode($fields) {
        $raw_value = WC()->session->get('frohub_forced_postcode');
        $service_type = WC()->session->get('frohub_selected_service_type');

        // Only proceed if service type is mobile
        if (strtolower($service_type) !== 'mobile') {
            return $fields;
        }

        if (!empty($raw_value)) {
            if (preg_match('/\b([A-Z]{1,2}\d{1,2}[A-Z]?)\s?(\d[A-Z]{2})\b/i', $raw_value, $matches)) {
                $postcode = strtoupper(trim($matches[0]));

                $fields['billing']['billing_postcode']['default'] = $postcode;

                add_action('wp_footer', function () use ($postcode) {
                    if (is_checkout()) {
                        ?>
                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const input = document.querySelector('#billing_postcode');
                            if (input) {
                                input.value = '<?php echo esc_js($postcode); ?>';
                                input.setAttribute('readonly', 'readonly');
                                input.style.backgroundColor = '#f9f9f9';
                                input.style.cursor = 'not-allowed';
                            }
                        });
                        </script>
                        <?php
                    }
                });
            }
        }

        return $fields;
    }
    
}