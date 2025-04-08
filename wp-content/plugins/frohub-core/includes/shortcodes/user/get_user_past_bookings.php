<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class GetUserPastBookings {

    public static function init() {
        $self = new self();
        add_shortcode('get_user_past_bookings', [$self, 'get_user_past_bookings_shortcode']);
    }

    public function get_user_past_bookings_shortcode() {
        $current_user_id = get_current_user_id();

        $args = [
            'customer' => $current_user_id,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'status'   => ['completed', 'cancelled'],
        ];

        $orders = wc_get_orders($args);
        $found_past_booking = false;
        $mobile_cards = ''; // Collect mobile card markup separately

        ob_start();

        echo '<div class="frohub_table_wrapper">';

        // Desktop Table
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

            if (!in_array($order_status, ['completed', 'cancelled'])) continue;

            $appointment = $service_name = $service_type = "";
            $partner_title = $partner_link = $partner_address = "";
            $deposit = 0;
            $total_due = 0;
            $product_id = 0;

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
                        $partner_address = get_field('partner_address', $partner_id);
                    }
                }
            }

            $service_name_parts = explode(' - ', $service_name);
            $clean_service_name = esc_html($service_name_parts[0]);
            $total_price = $deposit + $total_due;

            // Table Row
            echo '<tr>';
            echo '<td><a href="' . home_url('/my-account/view-order/' . $order_id . '/?_wca_initiator=action') . '" class="order_id">#' . esc_html($order_id) . '</a></td>';
            echo '<td>' . esc_html($appointment) . '</td>';
            echo '<td>' . esc_html($clean_service_name) . '</td>';
            echo '<td><a href="' . esc_url($partner_link) . '">' . esc_html($partner_title) . '</a></td>';
            echo '<td>£' . number_format($total_price, 2) . '</td>';

            $status_label = match ($order_status) {
                'completed' => 'Completed',
                'cancelled' => $this->get_cancellation_label(get_field('cancellation_status', $order_id)),
                default => ucfirst($order_status),
            };

            echo '<td><span class="status_text">' . esc_html($status_label) . '</span></td>';

            echo '<td>';
            $can_review = ($order_status === 'completed') ||
                          in_array(get_field('cancellation_status', $order_id), ['Cancelled by Stylist', 'Declined by Stylist']);

            $data = json_encode([
                'productImgURL' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                'productName' => $service_name,
                'serviceType' => $service_type,
                'partnerTitle' => $partner_title,
                'selectedDate' => $appointment,
                'partnerAddress' => $partner_address,
                'orderId' => $order_id,
                'productId' => $product_id,
            ]);

            if ($can_review) {
                if ($review && is_object($review)) {
                    $rating = (int)get_field('overall_rating', $review->ID);
                    echo $rating > 0 ? str_repeat('<i class="fas fa-star" style="color: black;"></i>', $rating) : 'Thank you';
                } else {
                    echo '<button class="myBtn w-btn us-btn-style_3" data-info=\'' . esc_attr($data) . '\'>Leave Review</button>';
                }
            }
            echo '</td>';

            echo '<td><a href="' . esc_url(get_permalink($product_id)) . '" class="w-btn us-btn-style_7">Book again</a></td>';
            echo '</tr>';

            // Mobile Card HTML (outside table, but saved for later)
            $mobile_cards .= '<div class="frohub_card">';
            $mobile_cards .= '<p><strong>' . esc_html($appointment) . '</strong></p>';
            $mobile_cards .= '<p>' . esc_html($clean_service_name) . '</p>';
            $mobile_cards .= '<p>' . esc_html($partner_title) . '</p>';
            $mobile_cards .= '<p>Deposit: £' . number_format($deposit, 2) . '</p>';
            $mobile_cards .= '<p><input disabled type="text" value="Due on the day: £' . number_format($total_due, 2) . '" /></p>';
            $mobile_cards .= '<div class="actions">';
            $mobile_cards .= '<button class="w-btn us-btn-style_3">Reschedule requested</button>';
            $mobile_cards .= '<a href="#">Accept/Decline</a>';
            $mobile_cards .= '</div>';
            $mobile_cards .= '</div>';

            $found_past_booking = true;
        }

        echo '</table>';
        echo $mobile_cards;
        echo '</div>'; // frohub_table_wrapper

        if ($found_past_booking) {
            echo '<h5>Past Bookings</h5>';
            echo do_shortcode('[us_separator size="large"]');
        }

        ?>
        <!-- Modal -->
        <div id="frohubReviewModal" class="frohub-modal">
          <div class="frohub-modal-content">
            <span class="frohub-close">×</span>
            <div class="frohub-modal-body">
                <h3>Leave a Review</h3>
                <div class="review-data"></div>
                <?php echo do_shortcode('[gravityform id="7" title="true" ajax="true"]'); ?>
            </div>
          </div>
        </div>

        <style>
            .fas.fa-star { margin-right: 2px; font-size: 16px; color: black; }
            .frohub-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0, 0, 0, 0.5); }
            .frohub-modal-content { background: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; position: relative; }
            .frohub-close { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
            .gform_title {display: none;}

            /* Responsive layout */
            @media only screen and (max-width: 768px) {
                .frohub_table { display: none; }
                .frohub_card {
                    display: block;
                    padding: 1rem;
                    border: 1px solid #ccc;
                    border-radius: 8px;
                    background: #fff;
                    margin-bottom: 1rem;
                }
                .frohub_card p {
                    margin: 0.25rem 0;
                    font-size: 0.9rem;
                }
                .frohub_card .actions {
                    margin-top: 0.75rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .frohub_card .actions button,
                .frohub_card .actions a {
                    font-size: 0.8rem;
                }
            }

            @media only screen and (min-width: 769px) {
                .frohub_card { display: none; }
            }
        </style>

        <script>
        jQuery(function($) {
            let reviewData = null;
            let modalOpened = false;

            // Handle modal open button
            $('.myBtn').on('click', function() {
                reviewData = $(this).data('info');
                modalOpened = true;

                // Populate booking summary
                $('.review-data').html(`
                    <strong>Service:</strong> ${reviewData.productName}<br>
                    <strong>Type:</strong> ${reviewData.serviceType}<br>
                    <strong>Date:</strong> ${reviewData.selectedDate}<br>
                    <strong>Stylist:</strong> ${reviewData.partnerTitle}<br>
                    <strong>Address:</strong> ${reviewData.partnerAddress}<br><br>
                `);

                // Show modal
                $('#frohubReviewModal').fadeIn();
            });

            // Gravity Form post-render hook
            $(document).on('gform_post_render', function(event, formId) {
                if (formId === 7 && modalOpened && reviewData !== null) {
                    const $orderField = $('#input_7_18');
                    const $productField = $('#input_7_19');

                    if ($orderField.length) {
                        $orderField.val(reviewData.orderId).attr('readonly', true);
                    }

                    if ($productField.length) {
                        $productField.val(reviewData.productId).attr('readonly', true);
                    }
                }
            });

            // Close modal logic
            $('.frohub-close').on('click', function() {
                $('#frohubReviewModal').fadeOut();
                modalOpened = false;
            });

            $(window).on('click', function(e) {
                if ($(e.target).is('#frohubReviewModal')) {
                    $('#frohubReviewModal').fadeOut();
                    modalOpened = false;
                }
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }

    private function get_cancellation_label($status) {
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
