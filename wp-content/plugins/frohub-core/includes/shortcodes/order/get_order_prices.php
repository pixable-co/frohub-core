<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class GetOrderPrices
{
    public static function init()
    {
        $self = new self();
        add_shortcode('get_order_prices', array($self, 'get_order_prices_shortcode'));
    }

    public function get_order_prices_shortcode()
    {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            $total_base_service_price = 0.0;
            $total_addons = 0.0;
            $total_extra = 0.0;
            $total_mobile = 0.0;
            $total_deposit = 0.0;
            $total_due = 0.0;
            $booking_fee = 0.0;

            echo '<div class="order-items-breakdown">';

            foreach ($order->get_items() as $index => $item) {
                $product_id = $item->get_product_id();

                // Skip booking fee product
                if ($product_id == 28990) {
                    $booking_fee += (float) $item->get_total();
                    continue;
                }

                $base_service_price = (float) get_field('service_price', $product_id);
                $addons_total = 0.0;
                $extra_charge = 0.0;
                $mobile_fee = 0.0;
                $due_on_the_day = 0.0;
                $addon_items = [];

                // Get meta fields
                foreach ($item->get_meta_data() as $meta) {
                    $key = strtolower(trim($meta->key));
                    $value = $meta->value;

                    switch ($key) {
                        case 'selected add ons':
                            $value = wp_strip_all_tags($value);
                            preg_match_all('/([^,]+?) \(£([\d\.]+)\)/', $value, $matches, PREG_SET_ORDER);
                            foreach ($matches as $match) {
                                $label = trim($match[1]);
                                $price = floatval($match[2]);
                                $addons_total += $price;
                                $addon_items[] = array('label' => $label, 'price' => $price);
                            }
                            break;

                        case 'extra charge':
                            $extra_charge += (float) str_replace(['£', ','], '', $value);
                            break;

                        case 'mobile travel fee':
                            $mobile_fee += (float) str_replace(['£', ','], '', $value);
                            break;

                        case 'total due on the day':
                            $due_on_the_day += (float) str_replace(['£', ','], '', $value);
                            break;
                    }
                }

                $deposit_paid = $item->get_total();
                $total_service_fee = $base_service_price + $addons_total + $extra_charge + $mobile_fee;

                // Accumulate global totals
                $total_base_service_price += $base_service_price;
                $total_addons += $addons_total;
                $total_extra += $extra_charge;
                $total_mobile += $mobile_fee;
                $total_deposit += $deposit_paid;
                $total_due += $due_on_the_day;

                // Output item breakdown
                echo '<table border="0" cellpadding="5" style="margin-bottom: 10px;">';
                echo '<tr><td colspan="2"><strong>Item ' . ($index + 1) . '</strong></td></tr>';
                echo '<tr><td>Base Service Price</td><td>£' . number_format($base_service_price, 2) . '</td></tr>';

                if (!empty($addon_items)) {
                    echo '<tr><td colspan="2"><em>Selected Add-Ons:</em></td></tr>';
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

                echo '<tr><td><strong>Item Total Fee</strong></td><td><strong>£' . number_format($total_service_fee, 2) . '</strong></td></tr>';
                echo '<tr><td>Deposit Paid</td><td>£' . number_format($deposit_paid, 2) . '</td></tr>';
                echo '<tr><td>Due on the Day</td><td>£' . number_format($due_on_the_day, 2) . '</td></tr>';
                echo '</table>';
                echo '<hr style="margin: 20px 0;">';
            }

            echo '</div>';

            // Totals summary
            echo '<h3>Total Summary</h3>';
            echo '<table border="0" cellpadding="5">';
            echo '<tr><td>Total Base Service Price</td><td>£' . number_format($total_base_service_price, 2) . '</td></tr>';
            echo '<tr><td>Total Add-Ons</td><td>£' . number_format($total_addons, 2) . '</td></tr>';
            echo '<tr><td>Total Extra Charges</td><td>£' . number_format($total_extra, 2) . '</td></tr>';
            echo '<tr><td>Total Mobile Travel Fee</td><td>£' . number_format($total_mobile, 2) . '</td></tr>';
            echo '<tr style="font-weight:bold;"><td>Total Service Fee</td><td>£' . number_format($total_base_service_price + $total_addons + $total_extra + $total_mobile, 2) . '</td></tr>';
            echo '<tr><td>Total Deposit Paid *</td><td>£' . number_format($total_deposit, 2) . '</td></tr>';
            echo '<tr><td>Total Due on the Day</td><td>£' . number_format($total_due, 2) . '</td></tr>';
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
