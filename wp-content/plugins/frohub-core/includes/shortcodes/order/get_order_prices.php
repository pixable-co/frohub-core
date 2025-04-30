<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderPrices {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_prices', array($self, 'get_order_prices_shortcode') );
    }

    public function get_order_prices_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            $base_service_price = 0.0;
            $addons_total = 0.0;
            $extra_charge = 0.0;
            $mobile_fee = 0.0;
            $deposit_paid = 0.0;
            $due_on_the_day = 0.0;
            $booking_fee = 0.0;
            $addon_items = [];

            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();

                if ($product_id != 28990) {
                    // Get base price from ACF
                    $base_service_price = (float) get_field('service_price', $product_id);

                    // Add-ons parsing
                    $add_ons = $item->get_meta('Selected Add Ons', true);
                    if (!empty($add_ons)) {
                        preg_match_all('/<strong>(.*?)<\/strong> \(£([\d\.]+)\)/', $add_ons, $matches, PREG_SET_ORDER);
                        foreach ($matches as $match) {
                            $label = $match[1];
                            $price = floatval($match[2]);
                            $addons_total += $price;
                            $addon_items[] = array('label' => $label, 'price' => $price);
                        }
                    }

                    // Extra charge
                    $extra = $item->get_meta('Extra Charge', true);
                    if (!empty($extra)) {
                        $extra_charge += (float) str_replace(['£', ','], '', $extra);
                    }

                    // Mobile travel fee
                    $mobile = $item->get_meta('Mobile Travel Fee', true);
                    if (!empty($mobile)) {
                        $mobile_fee += (float) str_replace(['£', ','], '', $mobile);
                    }

                    // ✅ Correctly retrieve Total due on day
                    $due_meta = $item->get_meta('Total due on day', true);
                    if (!empty($due_meta)) {
                        $due_on_the_day += (float) str_replace(['£', ','], '', $due_meta);
                    }

                    // Deposit paid (non-booking fee product)
                    $deposit_paid += $item->get_total();
                }

                // Booking fee product
                if ($product_id == 28990) {
                    $booking_fee = (float) $item->get_total();
                }
            }

            $total_service_fee = $base_service_price + $addons_total + $extra_charge + $mobile_fee;

            echo '<table border="0" cellpadding="5">';
            echo '<tr><td><strong>Base Service Price</strong></td><td>£' . number_format($base_service_price, 2) . '</td></tr>';

            if (!empty($addon_items)) {
                echo '<tr><td colspan="2"><strong>Selected Add-Ons</strong></td></tr>';
                foreach ($addon_items as $addon) {
                    echo '<tr><td>' . esc_html($addon['label']) . '</td><td>£' . number_format($addon['price'], 2) . '</td></tr>';
                }
                echo '<tr><td><strong>Add-Ons Total</strong></td><td>£' . number_format($addons_total, 2) . '</td></tr>';
            }

            if ($extra_charge > 0) {
                echo '<tr><td>Extra Charges</td><td>£' . number_format($extra_charge, 2) . '</td></tr>';
            }

            if ($mobile_fee > 0) {
                echo '<tr><td>Mobile Travel Fee</td><td>£' . number_format($mobile_fee, 2) . '</td></tr>';
            }

            echo '<tr style="font-weight:bold;"><td>Total Service Fee</td><td>£' . number_format($total_service_fee, 2) . '</td></tr>';
            echo '<tr><td>Deposit Paid *</td><td>£' . number_format($deposit_paid, 2) . '</td></tr>';
            echo '<tr><td>Due on the Day</td><td>£' . number_format($due_on_the_day, 2) . '</td></tr>';
            echo '</table>';

            echo '<hr><br>';
            echo '* Exclusive of £' . number_format($booking_fee, 2) . ' Booking fee. Total paid on FroHub: £' . number_format($order->get_total(), 2) . '<br><br>';

            $order_date = $order->get_date_created();
            if ($order_date) {
                echo '<p class="order_date">Order date: ' . esc_html($order_date->date('d M Y')) . '</p>';
            }
        }

        return ob_get_clean();
    }
}
