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
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['completed', 'cancelled'],
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

            if (!in_array($order_status, ['completed', 'cancelled']))
                continue;

            $found_past_booking = true;

            $appointment = $service_name = $service_type = "";
            $partner_title = $partner_link = $partner_address = "";
            $deposit = $total_due = $product_id = 0;

            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() !== 28990) {
                    $product_id = $item->get_product_id();
                    $deposit += (float) $item->get_total();

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
                                $total_due = (float) $cleaned;
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
                    $rating = (int) get_field('overall_rating', $review->ID);
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
                    $rating = (int) get_field('overall_rating', $review->ID);
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
        <!-- Modal Wrapper -->
        <div id="frohubReviewModal" class="frohub-modal">
            <div class="frohub-modal-content">
                <span class="frohub-close">×</span>

                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Leave a Review</h5>
                    </div>

                    <div class="modal-body">
                        <div class="product-details">
                            <div class="modal-body-left">
                                <img class="review-product-img" src="" alt="">
                            </div>
                            <div class="modal-body-right">
                                <p id="productName"></p>
                                <p id="serviceType"><span class="status_text"></span></p>
                                <p id="partnerTitle"></p>
                                <p id="bookingDate"><i class="fas fa-calendar-alt"></i> <span id="selectedDate"></span></p>
                                <p id="bookingAddress"><i class="fas fa-map-marker-alt"></i> <span id="partnerAddress"></span>
                                </p>
                            </div>
                        </div>

                        <div class="feedback-form" style="margin-top: 20px;">
                            <p id="feedbackHeading">Let’s See How You Slay (Share Your Photo)</p>
                            <p id="feedbackDesc">
                                Feel free to share a photo of your fabulous look! If you're a bit shy, you can always upload a
                                side shot
                                or one that keeps your face covered.
                                Sharing photos helps other clients see the stylist’s work and decide if they’d like to book the
                                service.
                            </p>
                            <?php echo do_shortcode('[gravityform id="7" title="false" description="false" ajax="true"]'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <style>
            /* General Modal Overlay */
.frohub-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

/* Modal Wrapper */
.frohub-modal-content {
    background-color: #fff;
    margin: 5% auto;
    border-radius: 5px;
    width: 90%;
    max-width: 800px;
    box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;
    position: relative;
}

/* Close Button */
.frohub-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
}

/* Header */
.modal-header {
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 2rem;
    text-align: center;
}

/* Modal Body */
.modal-body {
    margin-bottom: 2rem;
    text-align: center;
}

/* Product Details Grid */
.product-details {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 2rem;
    text-align: left;
}

/* Left column (image) */
.modal-body-left {
    flex: 1 1 40%;
    display: flex;
    justify-content: center;
}

.review-product-img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
}

/* Right column (info) */
.modal-body-right {
    flex: 1 1 55%;
    font-size: 0.95rem;
}

.modal-body-right p {
    margin: 0.5rem 0;
}

.modal-body-right .status_text {
    font-weight: 600;
    color: #333;
}

/* Feedback form */
.feedback-form {
    margin-top: 2rem;
    text-align: left;
}

#feedbackHeading {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

#feedbackDesc {
    font-size: 0.95rem;
    color: #666;
    margin-bottom: 1.5rem;
}

/* Star rating */
.fas.fa-star {
    margin-right: 2px;
    font-size: 16px;
    color: black;
}

/* Responsive Tweaks */
@media only screen and (max-width: 768px) {
    .product-details {
        flex-direction: column;
        text-align: center;
    }

    .modal-body-left,
    .modal-body-right {
        flex: 1 1 100%;
    }

    .modal-body-right {
        text-align: center;
    }
}

        </style>

        <script>
            jQuery(function ($) {
                $('.myBtn').on('click', function () {
                    const data = $(this).data('info');

                    $('#productName').text(data.productName);
                    $('#serviceType .status_text').text(data.serviceType);
                    $('#partnerTitle').text(data.partnerTitle);
                    $('#selectedDate').text(data.selectedDate);
                    $('#partnerAddress').text(data.partnerAddress);
                    $('.review-product-img').attr('src', data.productImgURL);

                    $('#frohubReviewModal').fadeIn();

                    $(document).on('gform_post_render', function (event, formId) {
                        if (formId === 7) {
                            $('#input_7_18').val(data.orderId).prop('readonly', true);
                            $('#input_7_19').val(data.productId).prop('readonly', true);
                        }
                    });
                });

                $('.frohub-close').on('click', function () {
                    $('#frohubReviewModal').fadeOut();
                });

                $(window).on('click', function (e) {
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
            'Early Cancellation' => 'Cancelled by client (early)',
            'Late Cancellation' => 'Cancelled by client (late)',
            'Declined by Client' => 'Declined by Client',
            'Declined by Stylist' => 'Declined by Stylist',
            'Cancelled by Stylist' => 'Cancelled by Stylist',
            default => 'Cancelled',
        };
    }
}
