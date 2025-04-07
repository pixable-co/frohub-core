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
        $mobile_cards = ''; // Holds mobile view HTML

        ob_start();

        echo '<h5> Upcoming & Pending Bookings </h5>';

        if (empty($orders)) {
            echo '<p>You don’t have any upcoming bookings</p>';
        } else {
            echo '<div class="frohub_table_wrapper">';
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
                    if ($product_id === 28990) continue;

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
                        case 'on-hold': $status_label = 'Pending'; break;
                        case 'processing': $status_label = 'Confirmed'; break;
                        case 'rescheduling': $status_label = 'Reschedule proposed'; break;
                        default: $status_label = ucfirst($order_status); break;
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

                    // MOBILE CARD version
                    $mobile_cards .= '<div class="frohub_card">';
                    $mobile_cards .= '<p><strong>' . esc_html($appointment) . '</strong></p>';
                    $mobile_cards .= '<p>' . esc_html($clean_service_name) . '</p>';
                    $mobile_cards .= '<p>' . esc_html($partner_title) . '</p>';
                    $mobile_cards .= '<p>Deposit: £' . number_format($deposit, 2) . '</p>';
                    $mobile_cards .= '<p><input disabled type="text" value="Due on the day: ' . esc_attr($total_due) . '" /></p>';
                    $mobile_cards .= '<div class="actions">';

                    if ($order_status === 'on-hold') {
                        $mobile_cards .= '<a href="#" class="w-btn us-btn-style_7 accept-button" data-order-id="' . esc_attr($order_id) . '">Accept</a>';
                        $mobile_cards .= '<a href="#" class="w-btn us-btn-style_7 decline-button" data-order-id="' . esc_attr($order_id) . '">Decline</a>';
                    } else {
                        $mobile_cards .= '<a href="' . home_url('/my-account/view-order/' . $order_id) . '" class="w-btn us-btn-style_7 view-button">View</a>';
                    }

                    $mobile_cards .= '</div>';
                    $mobile_cards .= '</div>';
                }
            }

            echo '</table>';
            echo $mobile_cards;
            echo '</div>'; // .frohub_table_wrapper
            echo do_shortcode('[us_separator size="large"]');
        }

        return ob_get_clean();
    }
}
