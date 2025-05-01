<?php

namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class GetUserPastBookings
{

    public static function init()
    {
        $self = new self();
        add_shortcode('get_user_past_bookings', [$self, 'get_user_past_bookings_shortcode']);
    }

    public function get_user_past_bookings_shortcode()
    {
        $current_user_id = get_current_user_id();

        $args = [
            'customer' => $current_user_id,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'status'   => ['completed', 'cancelled'],
        ];

        $orders = wc_get_orders($args);
        $found_past_booking = false;
        $table_rows = '';
        $mobile_cards = '';

        ob_start();

        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $order_status = $order->get_status();
            $review = get_field('review', $order_id);

            if (!in_array($order_status, ['completed', 'cancelled'])) continue;

            $found_past_booking = true;

            $appointment = $service_name = $service_type = "";
            $partner_title = $partner_link = $partner_address = "";
            $deposit = $total_due = $product_id = 0;

            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() !== 28990) {
                    $product_id = $item->get_product_id();
                    $deposit += (float)$item->get_total();

                    foreach ($item->get_meta_data() as $meta) {
                        switch ($meta->key) {
                            case 'Start Date Time':
                                $appointment = esc_html($meta->value);
                                break;
                            case 'pa_service-type':
                                $service_type = esc_html(ucwords(str_replace('-', ' ', $meta->value)));
                                break;
                            case 'Total Due on the Day':
                                $cleaned = str_replace(['£', ','], '', $meta->value);
                                $total_due = (float)$cleaned;
                                break;
                        }
                    }

                    $service_name = esc_html($item->get_name());

                    $partner_id = get_field('partner_id', $product_id);
                    if ($partner_id) {
                        $partner_title = get_the_title($partner_id);
                        $partner_link = get_permalink($partner_id);
                        $address_parts = array_filter([
                            get_field('street_address', $partner_id),
                            get_field('city', $partner_id),
                            get_field('county_district', $partner_id),
                            get_field('postcode', $partner_id),
                        ]);
                        $partner_address = implode(', ', $address_parts);
                    }
                }
            }

            $service_name_parts = explode(' - ', $service_name);
            $clean_service_name = esc_html($service_name_parts[0]);
            $total_price = $deposit + $total_due;

            $status_label = match ($order_status) {
                'completed' => 'Completed',
                'cancelled' => $this->get_cancellation_label(get_field('cancellation_status', $order_id)),
                default => ucfirst($order_status),
            };

            $can_review = ($order_status === 'completed') ||
                in_array(get_field('cancellation_status', $order_id), ['Cancelled by Stylist', 'Declined by Stylist']);

            $review_data = [
                'productImgURL' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                'productName' => $service_name,
                'serviceType' => $service_type,
                'partnerTitle' => $partner_title,
                'selectedDate' => $appointment,
                'partnerAddress' => $partner_address,
                'orderId' => $order_id,
                'productId' => $product_id,
            ];

            // --- Build Desktop Table Row ---
            $table_rows .= '<tr>';
            $table_rows .= '<td><a href="' . home_url('/my-account/view-order/' . $order_id . '/?_wca_initiator=action') . '" class="order_id">#' . esc_html($order_id) . '</a></td>';
            $table_rows .= '<td>' . esc_html($appointment) . '</td>';
            $table_rows .= '<td>' . esc_html($clean_service_name) . '</td>';
            $table_rows .= '<td><a href="' . esc_url($partner_link) . '">' . esc_html($partner_title) . '</a></td>';
            $table_rows .= '<td>£' . number_format($total_price, 2) . '</td>';
            $table_rows .= '<td><span class="status_text">' . esc_html($status_label) . '</span></td>';

            // Review Column
            $table_rows .= '<td>';
            if ($can_review) {
                if ($review && is_object($review)) {
                    $rating = (int)get_field('overall_rating', $review->ID);
                    $table_rows .= $rating > 0 ? str_repeat('<i class="fas fa-star" style="color: black;"></i>', $rating) : 'Thank you';
                } else {
                    $table_rows .= '<button class="myBtn w-btn us-btn-style_7 w-btn-underlined" data-info=\'' . esc_attr(json_encode($review_data)) . '\'>Leave Review</button>';
                }
            }
            $table_rows .= '</td>';

            // Book Again Column
            $table_rows .= '<td><a href="' . esc_url(get_permalink($product_id)) . '" class="w-btn us-btn-style_7 w-btn-underlined">Book again</a></td>';
            $table_rows .= '</tr>';

            // --- Build Mobile Card ---
            $mobile_cards .= '<div class="frohub_card">';
            $mobile_cards .= '<p><strong>' . esc_html($appointment) . '</strong></p>';
            $mobile_cards .= '<p>' . esc_html($clean_service_name) . '</p>';
            $mobile_cards .= '<p>' . esc_html($partner_title) . '</p>';
            $mobile_cards .= '<p>Deposit: £' . number_format($deposit, 2) . '</p>';
            $mobile_cards .= '<p><input disabled type="text" value="Due on the day: £' . number_format($total_due, 2) . '" /></p>';
            $mobile_cards .= '<div class="actions">';
            if ($can_review) {
                if ($review && is_object($review)) {
                    $rating = (int)get_field('overall_rating', $review->ID);
                    $mobile_cards .= '<div class="review-stars">' . ($rating > 0 ? str_repeat('<i class="fas fa-star" style="color: black;"></i>', $rating) : 'Thank you') . '</div>';
                } else {
                    $mobile_cards .= '<button class="myBtn w-btn us-btn-style_7 w-btn-underlined" data-info=\'' . esc_attr(json_encode($review_data)) . '\'>Leave Review</button>';
                }
            }
            $mobile_cards .= '<a href="' . esc_url(get_permalink($product_id)) . '" class="w-btn us-btn-style_7 w-btn-underlined">Book again</a>';
            $mobile_cards .= '</div></div>';
        }

        // === Final Output ===
        if ($found_past_booking) {
            echo '<h5>Past Bookings</h5>';
            echo '<div class="frohub_table_wrapper">';
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
            echo $table_rows;
            echo '</table>';
            echo $mobile_cards;
            echo '</div>';
        } else {
            echo '<p>No past bookings found.</p>';
        }

        ?>
        <!-- Modal + Styles + Script (unchanged) -->
        <div id="frohubReviewModal" class="frohub-modal">
            <div class="frohub-modal-content">
                <span class="frohub-close">×</span>
                <div class="frohub-modal-body">
                    <h3>Leave a Review</h3>
                    <div class="review-data"></div>
                    <?php echo do_shortcode('[gravityform id="7" title="false" ajax="true"]'); ?>
                </div>
            </div>
        </div>

        <style>
            .fas.fa-star { margin-right: 2px; font-size: 16px; color: black; }
            .frohub-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0, 0, 0, 0.5); }
            .frohub-modal-content { background: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 600px; position: relative; height: 800px; overflow: auto; }
            .frohub-close { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
            @media only screen and (max-width: 768px) {
                .frohub_table { display: none; }
                .frohub_card { display: block; padding: 1rem; border: 1px solid #ccc; border-radius: 8px; background: #fff; margin-bottom: 1rem; }
                .frohub_card p { margin: 0.25rem 0; font-size: 0.9rem; }
                .frohub_card .actions { margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; }
                .frohub_card .actions button, .frohub_card .actions a { font-size: 0.8rem; }
            }
            @media only screen and (min-width: 769px) { .frohub_card { display: none; } }
        </style>

        <script>
            jQuery(function($) {
                let reviewData = null;
                $('.myBtn').on('click', function() {
                    reviewData = $(this).data('info');
                    $('.review-data').html(`
                        <img src="${reviewData.productImgURL}" alt="${reviewData.productName}" style="max-width: 350px; height: 350px; display: block; margin-bottom: 10px;" />
                        <strong>Service:</strong> ${reviewData.productName}<br>
                        <strong>Type:</strong> ${reviewData.serviceType}<br>
                        <strong>Date:</strong> ${reviewData.selectedDate}<br>
                        <strong>Stylist:</strong> ${reviewData.partnerTitle}<br>
                        <strong>Address:</strong> ${reviewData.partnerAddress}<br><br>
                    `);
                    $('#frohubReviewModal').fadeIn();
                    setTimeout(() => {
                        $('#input_7_18').val(reviewData.orderId).attr('readonly', true);
                        $('#input_7_19').val(reviewData.productId).attr('readonly', true);
                    }, 200);
                });
                $('.frohub-close').on('click', function() {
                    $('#frohubReviewModal').fadeOut();
                });
                $(window).on('click', function(e) {
                    if ($(e.target).is('#frohubReviewModal')) {
                        $('#frohubReviewModal').fadeOut();
                    }
                });
            });
        </script>
        <?php

        return ob_get_clean();
    }

    private function get_cancellation_label($status)
    {
        return match ($status) {
            'Early Cancellation'   => 'Cancelled by client (early)',
            'Late Cancellation'    => 'Cancelled by client (late)',
            'Declined by Client'   => 'Declined by Client',
            'Declined by Stylist'  => 'Declined by Stylist',
            'Cancelled by Stylist' => 'Cancelled by Stylist',
            default                => 'Cancelled',
        };
    }
}
