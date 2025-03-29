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
            $Service_Fee = 0.0;
            $deposit_paid = 0.0;
            $due_on_the_day = 0.0;
            $price_of_product_2600 = 0.0;

            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();

                if ($product_id != 2600) {
                    $Service_Fee = (float) get_field('service_price', $product_id);
                    $item_meta_data = $item->get_meta_data();

                    if (!empty($item_meta_data)) {
                        foreach ($item_meta_data as $meta) {
                            if ($meta->key == 'Total due on day') {
                                $due_on_the_day += (float) $meta->value;
                            }
                        }
                    }

                    $deposit_paid += $item->get_total();
                }

                if ($product_id == 2600) {
                    $product_2600 = wc_get_product($product_id);
                    if ($product_2600) {
                        $price_of_product_2600 = (float) $product_2600->get_price();
                    }
                }
            }

            echo '<table border="0">';
            echo '<tr><td>Total Service Fee</td><td>£' . number_format($Service_Fee, 2) . '</td></tr>';
            echo '<tr><td>Deposit Paid *</td><td>£' . number_format($deposit_paid, 2) . '</td></tr>';
            echo '<tr><td>Due on the day</td><td>£' . number_format($Service_Fee - $deposit_paid, 2) . '</td></tr>';
            echo '</table>';
            echo '<hr><br>';
            echo '* Exclusive of £' . number_format($price_of_product_2600, 2) . ' Booking fee. Total paid on FroHub: £' . number_format($order->get_total(), 2) . '<br><br>';

            $order_date = $order->get_date_created();
            if ($order_date) {
                echo '<p class="order_date">Order date: ' . esc_html($order_date->date('d M Y')) . '</p>';
            }
        }

        return ob_get_clean();
    }
}
