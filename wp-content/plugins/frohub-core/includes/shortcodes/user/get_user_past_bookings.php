<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetUserPastBookings {

    public static function init() {
        $self = new self();
        add_shortcode('get_user_past_bookings', array($self, 'get_user_past_bookings_shortcode'));
    }

    public function get_user_past_bookings_shortcode() {
        $current_user_id = get_current_user_id();

        $args = array(
            'customer' => $current_user_id,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'status'   => ['completed', 'cancelled'],
        );

        $orders = wc_get_orders($args);
        $found_past_booking = false;

        ob_start();

        if (!empty($orders)) {
            echo '<table class="frohub_table">
                    <tr>
                        <th>Ref</th>
                        <th>Appointment</th>
                        <th>Service</th>
                        <th>Stylist</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Review</th>
                        <th></th>
                    </tr>';

            foreach ($orders as $order) {
                $order_id = $order->get_id();
                $order_status = $order->get_status();
                $review = get_field('review', $order_id);

                $display_order = in_array($order_status, ['completed', 'cancelled']);
                if (!$display_order) continue;

                $appointment = $service_name = $service_type = "";
                $partner_title = $partner_link = $partner_address = "";
                $deposit = 0;
                $total_due = 0;
                $product_id = 0;

                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() !== 28990) {
                        $product_id = $item->get_product_id();
                        $deposit += (float)$item->get_total();

                        $item_meta_data = $item->get_meta_data();
                        foreach ($item_meta_data as $meta) {
                            switch ($meta->key) {
                                case 'Start Date Time':
                                    $appointment = esc_html($meta->value);
                                    break;
                                case 'pa_service-type':
                                    $service_type = esc_html(ucwords(str_replace('-', ' ', $meta->value)));
                                    break;
                                case 'Total Due on the Day':
                                    $cleaned_value = str_replace(['£', ','], '', $meta->value);
                                    $total_due = (float)$cleaned_value;
                                    break;
                            }
                        }

                        $service_name = esc_html($item->get_name());

                        $partner_id = get_field('partner_id', $product_id);
                        if ($partner_id) {
                            $partner_title = get_the_title($partner_id);
                            $partner_link = get_permalink($partner_id);
                            $partner_address = get_field('partner_address', $partner_id);
                        }
                    }
                }

                $service_name_parts = explode(' - ', $service_name);
                $clean_service_name = esc_html($service_name_parts[0]);

                echo '<tr>';
                echo '<td><a href="' . home_url('/my-account/view-order/' . $order_id . '/?_wca_initiator=action') . '" class="order_id">#' . esc_html($order_id) . '</a></td>';
                echo '<td>' . esc_html($appointment) . '</td>';
                echo '<td>' . esc_html($clean_service_name) . '</td>';
                echo '<td><a href="' . esc_url($partner_link) . '">' . esc_html($partner_title) . '</a></td>';

                $total_price = $deposit + $total_due;
                echo '<td>£' . number_format($total_price, 2) . '</td>';

                $status_label = '';
                switch ($order_status) {
                    case 'expired':
                        $status_label = 'Booking expired';
                        break;
                    case 'cancelled':
                        $cancellation_status = get_field('cancellation_status', $order_id);
                        $cancellation_labels = [
                            'Early Cancellation'   => 'Cancelled by client (early)',
                            'Late Cancellation'    => 'Cancelled by client (late)',
                            'Declined by Client'   => 'Declined by Client',
                            'Declined by Stylist'  => 'Declined by Stylist',
                            'Cancelled by Stylist' => 'Cancelled by Stylist',
                        ];
                        $status_label = $cancellation_labels[$cancellation_status] ?? 'Cancelled';
                        break;
                    case 'completed':
                        $status_label = "Completed";
                        break;
                    default:
                        $status_label = ucfirst($order_status);
                        break;
                }

                echo '<td><span class="status_text">' . esc_html($status_label) . '</span></td>';

                echo '<td>';
                $data = json_encode([
                    'productImgURL' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                    'productName'   => $service_name,
                    'serviceType'   => $service_type,
                    'partnerTitle'  => $partner_title,
                    'selectedDate'  => $appointment,
                    'partnerAddress'=> $partner_address,
                    'orderId'       => $order_id,
                    'productId'     => $product_id,
                ]);

                $can_leave_review = false;
                if ($order_status === 'completed') {
                    $can_leave_review = true;
                } elseif ($order_status === 'cancelled') {
                    $cancellation_status = get_field('cancellation_status', $order_id);
                    if (in_array($cancellation_status, ['Cancelled by Stylist', 'Declined by Stylist'])) {
                        $can_leave_review = true;
                    }
                }

                if ($can_leave_review) {
                    if ($review && is_object($review)) {
                        $rating = (int) get_field('overall_rating', $review->ID);
                        echo $rating > 0
                            ? str_repeat('<i class="fas fa-star" style="color: black;"></i>', $rating)
                            : 'Thank you';
                    } else {
                        echo '<button class="myBtn w-btn us-btn-style_3" data-info=\'' . esc_attr($data) . '\'>Leave Review</button>';
                    }
                }
                echo '</td>';

                echo '<td><a href="' . esc_url(get_permalink($product_id)) . '" class="w-btn us-btn-style_7">Book again</a></td>';
                echo '</tr>';

                $found_past_booking = true;
            }

            echo '</table>';
        }

        if ($found_past_booking) {
            echo '<h5>Past Bookings</h5>';
            echo do_shortcode('[us_separator size="large"]');
        }

        ?>
        <!-- Modal HTML -->
        <div id="frohubReviewModal" class="frohub-modal">
          <div class="frohub-modal-content">
            <span class="frohub-close">×</span>
            <div class="frohub-modal-body">
                <h3>Leave a Review</h3>
                <div class="review-data"></div>
                <?php echo do_shortcode('[gravityform id="5" title="true" ajax="true"]'); ?>
            </div>
          </div>
        </div>

        <style>
            .fas.fa-star { margin-right: 2px; font-size: 16px; color: black; }
            .frohub-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.5); }
            .frohub-modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; position: relative; }
            .frohub-close { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
            .frohub-modal-body { padding: 10px 0; }
            .review-data { margin-bottom: 15px; font-size: 14px; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var modal = $('#frohubReviewModal');

            $('.myBtn').on('click', function() {
                var data = $(this).data('info');
                $('.review-data').html(`
                    <strong>Service:</strong> ${data.productName}<br>
                    <strong>Type:</strong> ${data.serviceType}<br>
                    <strong>Date:</strong> ${data.selectedDate}<br>
                    <strong>Stylist:</strong> ${data.partnerTitle}<br>
                    <strong>Address:</strong> ${data.partnerAddress}
                `);
                modal.fadeIn();
            });

            $('.frohub-close').on('click', function() {
                modal.fadeOut();
            });

            $(window).on('click', function(e) {
                if ($(e.target).is(modal)) {
                    modal.fadeOut();
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }
}
