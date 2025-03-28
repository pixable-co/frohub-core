<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetUpcomingBookings {

    public static function init() {
        $self = new self();
        add_shortcode('get_upcoming_bookings', array($self, 'get_upcoming_bookings_shortcode'));
    }

    public function get_upcoming_bookings_shortcode() {
        $current_user_id = get_current_user_id();
        $current_datetime = date('Y-m-d H:i');

        $args = array(
            'posts_per_page' => -1,
            'customer'       => $current_user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'status'         => ['on-hold', 'rescheduling', 'processing'],
        );

        $orders = wc_get_orders($args);

        ob_start();

        echo '<h5> Upcoming & Pending Bookings </h5>';

        if (empty($orders)) {
            echo '<p>You don’t have any upcoming bookings</p>';
        } else {
            echo '<table class="frohub_table">
                <tr>
                    <th>Ref</th>
                    <th>Appointment</th>
                    <th>Service</th>
                    <th>Stylist</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th></th>
                </tr>';

            foreach ($orders as $order) {
                $order_id = $order->get_id();
                $order_status = $order->get_status();
                $display_order = false;

                $appointment = $service_name = $service_type = $duration = "";
                $total_due = "";
                $deposit = 0;
                $partner_title = '';

                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    if ($product_id === 2600) continue;

                    $partner_id = get_field('partner_id', $product_id);
                    $partner_title = get_the_title($partner_id);

                    $line_total = $item->get_total();
                    $service_name = $item->get_name();
                    $item_meta_data = $item->get_meta_data();

                    foreach ($item_meta_data as $meta) {
                        switch ($meta->key) {
                            case 'Start Date Time':
                                $appointment = esc_html($meta->value);
                                $display_order = true;
                                break;
                            case 'pa_service-type':
                                $service_type = esc_html(ucwords(str_replace('-', ' ', $meta->value)));
                                break;
                            case 'Duration':
                                $duration = esc_html($meta->value);
                                break;
                            case 'Total Due on the Day':
                                $total_due = esc_html($meta->value);
                                break;
                        }
                    }

                    $deposit += (float)$line_total;
                }

                $service_name_parts = explode(' - ', $service_name);
                $clean_service_name = esc_html($service_name_parts[0]);

                if ($display_order) {
                    echo '<tr>';
                    echo '<td><a href="' . home_url('/my-account/view-order/' . $order_id . '/?_wca_initiator=action') . '" class="order_id">#' . $order_id . '</a></td>';
                    echo '<td>' . esc_html($appointment) . '</td>';
                    echo '<td>' . esc_html($clean_service_name) . '</td>';
                    echo '<td>' . esc_html($partner_title) . '</td>';

                    echo '<td>';
                    echo '<div class="price-block">';
                    echo '<div><strong>Deposit:</strong> £' . number_format($deposit, 2) . '</div>';
                    echo '<div class="due-on-day"><input type="text" value="Due on the day: ' . esc_attr($total_due) . '" readonly></div>';
                    echo '</div>';
                    echo '</td>';

                    $status_label = '';
                    switch ($order_status) {
                        case 'on-hold':
                            $status_label = 'Pending';
                            break;
                        case 'processing':
                            $status_label = 'Confirmed';
                            break;
                        case 'rescheduling':
                            $status_label = 'Reschedule proposed';
                            break;
                        default:
                            $status_label = ucfirst($order_status);
                            break;
                    }

                    echo '<td><span class="status_text">' . esc_html($status_label) . '</span></td>';

                    echo '<td>';
                    if ($order_status === 'on-hold') {
                        echo '<div class="table-action-buttons">';
                        echo '<a href="#" class="w-btn us-btn-style_7 accept-button" data-order-id="' . esc_attr($order_id) . '">Accept</a>';
                        echo '<span> / </span>';
                        echo '<a href="#" class="w-btn us-btn-style_7 decline-button" data-order-id="' . esc_attr($order_id) . '">Decline</a>';
                        echo '</div>';
                    } else {
                        echo '<a href="' . home_url('/my-account/view-order/' . $order_id) . '" class="w-btn us-btn-style_7 view-button">View</a>';
                    }
                    echo '</td>';

                    echo '</tr>';
                }
            }

            echo '</table>';
            echo do_shortcode('[us_separator size="large"]');
        }

        ?>
        <style>
        .price-block {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .price-block input {
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #f5f5f5;
            color: #555;
            padding: 4px 8px;
            font-size: 0.9em;
            width: auto;
            max-width: 200px;
        }

        .table-action-buttons {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        </style>
        <?php

        return ob_get_clean();
    }
}
