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
            echo '<div class="payment-summary">';

            echo '<h6>Payment Summary</h6>';
            if ($base_service_price > 0) {
                echo '<div class="base-service line-item">
            <div class="label">' . esc_html('Service Price') . ' </div>
            <div class="price">£' . number_format($base_service_price, 2) . '</div>
          </div>';
            }
            if (!empty($addon_items)) {
                echo '<div class="add-on-line-items">';
                echo '<div class="section-label">Selected Add-Ons</div>';
                foreach ($addon_items as $addon) {
                    echo '<div class="line-item">
                            <div class="label">' . esc_html($addon['label']) . '</div>
                            <div class="price">£' . number_format($addon['price'], 2) . '</div>
                          </div>';
                }

                echo '<div class="receipt-separator"></div>';
                echo '</div>';
            }

            if ($extra_charge > 0) {
                echo '<div class="extra-charges line-item">
                        <div class="label">' . esc_html('Premium Time Fee') . '</div>
                        <div class="price">£' . number_format($extra_charge, 2) . '</div>
                      </div>';
            }

            if ($mobile_fee > 0) {
                echo '<div class="mobile-travel-fee line-item">
                        <div class="label">' . esc_html('Mobile Travel Fee') . '</div>
                        <div class="price">£' . number_format($mobile_fee, 2) . '</div>
                      </div>';
            }

            echo '<br>';

            echo '<div class="total-service-fee line-item">
                    <div class="label">' . esc_html('Total Service Cost') . '</div>
                    <div class="price"><strong>£' . number_format($total_service_fee, 2) . '</strong></div>
                  </div><br>';

            echo '<div class="deposit-paid line-item">
                    <div class="label">' . esc_html('Deposit Paid *') . '</div>
                    <div class="price">£' . number_format($deposit_paid, 2) . '</div>
                  </div>';

            echo '<div class="due-on-the-day line-item">
                    <div class="label">' . esc_html('Due on the Day') . '</div>
                    <div class="price">£' . number_format($due_on_the_day, 2) . '</div>
                  </div><br>';

            echo '<div class="booking-fee-note">* Exclusive of £' . number_format($booking_fee, 2) . ' Booking fee. Total paid on FroHub: £' . number_format($order->get_total(), 2) . '
                  </div><br>';

            if ($order_date = $order->get_date_created()) {
                echo '<div class="order-date">Order date: ' . esc_html($order_date->date('d M Y')) . '</div>';
            }

            echo '</div>';


        }

        return ob_get_clean();
    }
}
