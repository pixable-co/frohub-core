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
            $base_price = 0.0;
            $addons_total = 0.0;
            $extra_charge = 0.0;
            $mobile_fee = 0.0;
            $deposit_paid = 0.0;
            $due_on_the_day = 0.0;
            $booking_fee_price = 0.0;
    
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
    
                if ($product_id != 28990) {
                    // Get base price
                    $base_price = (float) get_field('service_price', $product_id);
    
                    // Add add-on prices if any
                    $add_ons = $item->get_meta('Selected Add Ons', true);
                    if (!empty($add_ons)) {
                        preg_match_all('/\£([\d\.]+)/', $add_ons, $matches);
                        foreach ($matches[1] as $price) {
                            $addons_total += floatval($price);
                        }
                    }
    
                    // Extra charges
                    $extra = $item->get_meta('Extra Charge');
                    if (!empty($extra)) {
                        $extra_charge += floatval(str_replace(['£', ','], '', $extra));
                    }
    
                    // Mobile travel fee
                    $mobile = $item->get_meta('Mobile Travel Fee');
                    if (!empty($mobile)) {
                        $mobile_fee += floatval(str_replace(['£', ','], '', $mobile));
                    }
    
                    // Due on the day
                    $due = $item->get_meta('Total due on day');
                    if (!empty($due)) {
                        $due_on_the_day += floatval(str_replace(['£', ','], '', $due));
                    }
    
                    // Deposit paid (only for non-booking fee items)
                    $deposit_paid += $item->get_total();
                }
    
                if ($product_id == 28990) {
                    $booking_fee_price = (float) $item->get_total();
                }
            }
    
            $total_service_cost = $base_price + $addons_total + $extra_charge + $mobile_fee;
    
            echo '<table border="0" cellpadding="5">';
            echo '<tr><td>Base Service Price</td><td>£' . number_format($base_price, 2) . '</td></tr>';
            echo '<tr><td>Add-Ons</td><td>£' . number_format($addons_total, 2) . '</td></tr>';
            echo '<tr><td>Extra Charges</td><td>£' . number_format($extra_charge, 2) . '</td></tr>';
            echo '<tr><td>Mobile Travel Fee</td><td>£' . number_format($mobile_fee, 2) . '</td></tr>';
            echo '<tr style="font-weight:bold;"><td>Total Service Cost</td><td>£' . number_format($total_service_cost, 2) . '</td></tr>';
            echo '<tr><td>Deposit Paid *</td><td>£' . number_format($deposit_paid, 2) . '</td></tr>';
            echo '<tr style="font-weight:bold;"><td>Due on the Day</td><td>£' . number_format($due_on_the_day, 2) . '</td></tr>';
            echo '</table>';
    
            echo '<hr><br>';
            echo '* Exclusive of £' . number_format($booking_fee_price, 2) . ' Booking fee. Total paid on FroHub: £' . number_format($order->get_total(), 2) . '<br><br>';
    
            $order_date = $order->get_date_created();
            if ($order_date) {
                echo '<p class="order_date">Order date: ' . esc_html($order_date->date('d M Y')) . '</p>';
            }
        }
    
        return ob_get_clean();
    }
    
}
