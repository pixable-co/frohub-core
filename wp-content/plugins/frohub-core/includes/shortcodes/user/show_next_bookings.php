<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShowNextBookings {

    public static function init() {
        $self = new self();
        add_shortcode('show_next_bookings', array($self, 'show_next_bookings_shortcode'));
    }

    public function show_next_bookings_shortcode() {
        $current_user_id = get_current_user_id();
        $current_date = date('Y-m-d');

        $args = array(
            'posts_per_page' => 1,
            'customer'       => $current_user_id,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'status'         => ['on-hold', 'rescheduling', 'processing'],
        );

        $orders = wc_get_orders($args);
        $upcoming_bookings = [];

        if (empty($orders)) {
            return '<p>No upcoming bookings</p>';
        }

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if ($product_id == 28990) continue;

                $meta_data = $item->get_meta_data();
                $selected_date_time = null;
                $total_due = $duration = $size = $length = $service_type = '';

                foreach ($meta_data as $meta) {
                    switch ($meta->key) {
                        case 'Start Date Time':
                            $selected_date_time = strtotime($meta->value);
                            break;
                        case 'Total Due on the Day':
                            $total_due = $meta->value;
                            break;
                        case 'Duration':
                            $duration = $meta->value;
                            break;
                        case 'Size':
                            $size = $meta->value;
                            break;
                        case 'Length':
                            $length = $meta->value;
                            break;
                        case 'pa_service-type':
                            $service_type = $meta->value;
                            break;
                    }
                }

                if ($selected_date_time && $selected_date_time > strtotime($current_date)) {
                    $upcoming_bookings[] = [
                        'product_id'        => $product_id,
                        'product_name'      => $item->get_name(),
                        'product_link'      => get_permalink($product_id),
                        'order_id'          => $order->get_id(),
                        'partner_id'        => get_field('partner_id', $product_id),
                        'service_type'      => $service_type,
                        'total_due'         => $total_due,
                        'appointment_start' => date('H:i j F Y', $selected_date_time),
                        'duration'          => $duration,
                        'size'              => $size,
                        'length'            => $length,
                    ];
                }
            }
        }

        if (empty($upcoming_bookings)) {
            return '<p>No upcoming bookings</p>';
        }

        usort($upcoming_bookings, function ($a, $b) {
            return strtotime($a['appointment_start']) - strtotime($b['appointment_start']);
        });

        ob_start();

        foreach ($upcoming_bookings as $booking) {
            $partner_title = get_the_title($booking['partner_id']);
            $partner_link = get_permalink($booking['partner_id']);

            if ($booking['service_type'] === 'mobile') {
                $order = wc_get_order($booking['order_id']);
                $address_parts = array_filter([
                    $order->get_shipping_address_1(),
                    $order->get_shipping_address_2(),
                    $order->get_shipping_city(),
                    $order->get_shipping_postcode()
                ]);
                $partner_address = implode(' ', $address_parts);
            } else {
                $partner_address = get_field('partner_address', $booking['partner_id']);
            }

            echo '<div class="bookings_data_my_account">';
            echo '<a href="' . esc_url($booking['product_link']) . '">' . esc_html($booking['product_name']) . '</a><br>';
            echo '<span class="service_type_text">' . esc_html($booking['service_type']) . '</span><br>';
            echo '<a href="' . esc_url($partner_link) . '">' . esc_html($partner_title) . '</a><br>';
            echo '<span><i class="fas fa-calendar-alt"></i> ' . esc_html($booking['appointment_start']) . '</span><br>';
            echo '<span><i class="fas fa-map-marker-alt"></i> ' . esc_html($partner_address) . '</span><br>';
            echo '<span><i class="fas fa-clock"></i> Duration: ' . esc_html($booking['duration']) . '</span><br>';
            echo '<span><i class="fas fa-ruler"></i> Size: ' . esc_html($booking['size']) . '</span><br>';
            echo '<span><i class="fas fa-ruler-vertical"></i> Length: ' . esc_html($booking['length']) . '</span><br>';
            echo '<span><i class="fas fa-money-bill"></i> Due on the Day: ' . esc_html($booking['total_due']) . '</span><br>';
            echo '</div>';
        }

        return ob_get_clean();
    }
}
