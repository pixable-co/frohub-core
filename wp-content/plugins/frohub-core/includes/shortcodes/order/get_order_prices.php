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

                if ($product_id == 28990) {
                    $booking_fee += (float) $item->get_total();
                    continue;
                }

                $base_service_price += (float) get_field('service_price', $product_id);

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

                $deposit_paid += $item->get_total();
            }

            $total_service_fee = $base_service_price + $addons_total + $extra_charge + $mobile_fee;

            // --- OUTPUT SECTION ---
            echo '<div style="font-family: monospace; white-space: pre-wrap; line-height: 1.8;">';

            echo str_pad('Base Service Price', 28) . '£' . number_format($base_service_price, 2) . "\n\n";

            if (!empty($addon_items)) {
                echo "Selected Add-Ons\n";
                foreach ($addon_items as $addon) {
                    echo str_pad($addon['label'], 28) . '£' . number_format($addon['price'], 2) . "\n";
                }
                echo str_pad('Add-Ons Total', 28) . '£' . number_format($addons_total, 2) . "\n";
                echo str_repeat('-', 28) . "\n";
            }

            if ($extra_charge > 0) {
                echo str_pad('Extra Charges', 28) . '£' . number_format($extra_charge, 2) . "\n";
            }

            if ($mobile_fee > 0) {
                echo str_pad('Mobile Travel Fee', 28) . '£' . number_format($mobile_fee, 2) . "\n";
            }

            echo str_pad('Total Service Fee', 28) . '£' . number_format($total_service_fee, 2) . "\n\n";

            echo str_pad('Deposit Paid *', 28) . '£' . number_format($deposit_paid, 2) . "\n";
            echo str_pad('Due on the Day', 28) . '£' . number_format($due_on_the_day, 2) . "\n\n";

            echo '* Exclusive of £' . number_format($booking_fee, 2) . ' Booking fee. ';
            echo 'Total paid on FroHub: £' . number_format($order->get_total(), 2) . "\n\n";

            $order_date = $order->get_date_created();
            if ($order_date) {
                echo 'Order date: ' . esc_html($order_date->date('d M Y')) . "\n";
            }

            echo '</div>';
        }

        return ob_get_clean();
    }
}
