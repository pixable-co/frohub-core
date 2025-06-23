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
    $now = current_time('Y-m-d H:i');
    $orders = wc_get_orders(array(
        'posts_per_page' => -1,
        'customer'       => $current_user_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'status'         => ['on-hold', 'rescheduling', 'processing'],
    ));

    $upcoming_orders = [];

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $order_status = $order->get_status();

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if ($product_id === 28990) continue;

            $appointment = $service_name = $service_type = $duration = $total_due = '';
            $partner_title = get_the_title(get_field('partner_id', $product_id));
            $appointment_datetime = null;
            $end_datetime = null;
            $deposit = (float) $item->get_total();
            $item_meta_data = $item->get_meta_data();

            foreach ($item_meta_data as $meta) {
                switch ($meta->key) {
                    case 'Start Date Time':
                        $appointment = esc_html($meta->value);
                        $appointment_datetime = strtotime($appointment);
                        break;
                    case 'End Date Time':
                        $end_datetime = strtotime($meta->value);
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


            if (!$appointment_datetime || !$end_datetime || $end_datetime < strtotime($now)) {
                continue; // Skip if appointment has ended
            }


            $upcoming_orders[] = array(
                'order_id'            => $order_id,
                'appointment'         => $appointment,
                'appointment_ts'      => $appointment_datetime,
                'service_name'        => $item->get_name(),
                'partner_title'       => $partner_title,
                'deposit'             => $deposit,
                'total_due'           => $total_due,
                'order_status'        => $order_status,
            );
        }
    }

    // Sort by appointment timestamp ascending
    usort($upcoming_orders, function($a, $b) {
        return $a['appointment_ts'] <=> $b['appointment_ts'];
    });

    ob_start();
    echo '<h5>Upcoming & Pending Bookings</h5>';

    if (empty($upcoming_orders)) {
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

        $mobile_cards = '';
        foreach ($upcoming_orders as $booking) {
            $clean_service_name = esc_html(explode(' - ', $booking['service_name'])[0]);
            $status_label = match ($booking['order_status']) {
                'on-hold' => 'Pending',
                'processing' => 'Confirmed',
                'rescheduling' => 'Reschedule proposed',
                default => ucfirst($booking['order_status']),
            };

            echo '<tr>';
            echo '<td><a href="' . home_url('/my-account/view-order/' . $booking['order_id'] . '/?_wca_initiator=action') . '" class="order_id">#' . esc_html($booking['order_id']) . '</a></td>';
            echo '<td>' . esc_html($booking['appointment']) . '</td>';
            echo '<td>' . $clean_service_name . '</td>';
            echo '<td>' . esc_html($booking['partner_title']) . '</td>';
            echo '<td>
                    <div class="price-block">
                        <div class="deposit"><strong>Deposit:</strong> £' . number_format($booking['deposit'], 2) . '</div>
                        <div class="due-on-day"> Due on the day: ' . esc_attr($booking['total_due']) . '</div>
                    </div>
                </td>';
            echo '<td><span class="status_text">' . esc_html($status_label) . '</span></td>';
            echo '<td>';
            if ($booking['order_status'] === 'rescheduling') {
                $item_id = $booking['order_id']; // If you're not already passing item_id, use a unique order ID fallback
                $modal_accept = "acceptModal_" . $item_id;
                $modal_decline = "declineModal_" . $item_id;

                echo '<div class="table-action-buttons">';
                echo '<button class="modal-trigger w-btn us-btn-style_7 w-btn-underlined accept-button" 
                        data-modal="' . esc_attr($modal_accept) . '"
                        data-order-id="' . esc_attr($booking['order_id']) . '"
                        data-start="' . esc_attr($booking['appointment']) . '">
                        Accept</button>';
                echo '<span> / </span>';
                echo '<button class="modal-trigger w-btn us-btn-style_7 w-btn-underlined decline-button" 
                        data-modal="' . esc_attr($modal_decline) . '"
                        data-order-id="' . esc_attr($booking['order_id']) . '"
                        data-start="' . esc_attr($booking['appointment']) . '">
                        Decline</button>';
                echo '</div>';
            }
            else {
                echo '<a href="' . home_url('/my-account/view-order/' . $booking['order_id']) . '" class="w-btn us-btn-style_7 w-btn-underlined view-button">View</a>';
            }
            echo '</td>';
            echo '</tr>';

            $mobile_cards .= '<div class="frohub_card">';
            $mobile_cards .= '<p><strong>' . esc_html($booking['appointment']) . '</strong></p>';
            $mobile_cards .= '<p>' . $clean_service_name . '</p>';
            $mobile_cards .= '<p>' . esc_html($booking['partner_title']) . '</p>';
            $mobile_cards .= '<p>Deposit: £' . number_format($booking['deposit'], 2) . '</p>';
            $mobile_cards .= '<p><input disabled type="text" value="Due on the day: ' . esc_attr($booking['total_due']) . '" /></p>';
            $mobile_cards .= '<div class="actions">';
            if ($booking['order_status'] === 'rescheduling') {
                $mobile_cards .= '<a href="#" class="w-btn us-btn-style_7 w-btn-underlined accept-button" data-order-id="' . esc_attr($booking['order_id']) . '">Accept</a>';
                $mobile_cards .= '<a href="#" class="w-btn us-btn-style_7 w-btn-underlined decline-button" data-order-id="' . esc_attr($booking['order_id']) . '">Decline</a>';
            } else {
                $mobile_cards .= '<a href="' . home_url('/my-account/view-order/' . $booking['order_id']) . '" class="w-btn us-btn-style_7 w-btn-underlined view-button">View</a>';
            }
            $mobile_cards .= '</div></div>';
        }

        echo '</table>';
        echo '<div id="' . esc_attr($modal_accept) . '" class="status-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Confirm New Appointment</h5>
                    <span class="close-modal">×</span>
                </div>
                <div class="modal-body">
                    <p>You’re about to confirm the new appointment time proposed by your stylist.</p>
                    <p><strong>Proposed Time:</strong> <span class="modal-start-time"></span></p>
                </div>
                <div class="modal-footer">
                    <button class="w-btn us-btn-style_6 w-btn-underlined confirm-proposed-date"
                        data-order-id="' . esc_attr($booking['order_id']) . '"
                        data-start="' . esc_attr($booking['appointment']) . '">
                        <span class="spinner hidden"></span> Yes, Confirm Appointment</button>
                    <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Go Back</button>
                </div>
            </div>
        </div>';

        echo '<div id="' . esc_attr($modal_decline) . '" class="status-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Decline Proposed Appointment</h5>
                    <span class="close-modal">×</span>
                </div>
                <div class="modal-body">
                    <p>If you decline this appointment, your booking will be cancelled.</p>
                    <p><strong>Proposed Time:</strong> <span class="modal-start-time"></span></p>
                </div>
                <div class="modal-footer">
                    <button class="w-btn us-btn-style_6 w-btn-underlined decline-proposed-date"
                        data-order-id="' . esc_attr($booking['order_id']) . '">
                        <span class="spinner hidden"></span> Yes, Decline Appointment</button>
                    <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep Proposed Time</button>
                </div>
            </div>
        </div>';

        echo $mobile_cards;
        echo '</div>'; // .frohub_table_wrapper
    }

    echo '<script>
        jQuery(document).ready(function($) {
            $(".modal-trigger").click(function () {
                const modalId = $(this).data("modal");
                const startTime = $(this).data("start");
                const orderId = $(this).data("order-id");

                $("#" + modalId).fadeIn(200);
                $("#" + modalId).find(".modal-start-time").text(startTime);
                $("#" + modalId).find(".confirm-proposed-date, .decline-proposed-date")
                    .attr("data-order-id", orderId)
                    .attr("data-start", startTime);
            });

            $(".close-modal, .close-modal-text").click(function () {
                $(".status-modal").hide();
            });

            $(window).click(function (e) {
                $(".status-modal").each(function () {
                    if (e.target === this) {
                        $(this).hide();
                    }
                });
            });

            function showSpinner(button) {
                var footer = button.closest(".modal-footer");
                footer.find("button").prop("disabled", true);
                footer.find("button").not(button).hide();
                button.find(".btn-text").hide();
                button.find(".spinner").removeClass("hidden");
            }

            function hideSpinner(button) {
                var footer = button.closest(".modal-footer");
                footer.find("button").prop("disabled", false).show();
                button.find(".btn-text").show();
                button.find(".spinner").addClass("hidden");
            }

            $(".confirm-proposed-date").click(function () {
                const button = $(this);
                const orderId = button.data("order-id");
                showSpinner(button);

                $.post("' . admin_url('admin-ajax.php') . '", {
                    action: "accept_new_time",
                    security: "' . wp_create_nonce('ajax_nonce') . '",
                    order_id: orderId
                }, function (response) {
                    hideSpinner(button);
                    if (response.success) {
                        $(".status-modal").hide();
                        $("#acceptSuccessModal").fadeIn();
                    } else {
                        alert("Error: " + (response.data?.message || "Unknown"));
                    }
                });
            });

            $(".decline-proposed-date").click(function () {
                const button = $(this);
                const orderId = button.data("order-id");
                $(".status-modal").hide();
                $("#cancelReasonModal_" + orderId).show();
            });
        });
        </script>';


    return ob_get_clean();
}

}
