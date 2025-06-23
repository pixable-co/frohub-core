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
    $now = current_time('timestamp') - 60; // buffer of 1 minute earlier
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


            if (!$appointment_datetime || !$end_datetime || $end_datetime < $now) {
                continue;
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
    
                // Accept Success Modal
        echo '<div id="acceptSuccessModal" class="status-modal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Appointment Confirmed!</h5>
                    <span class="close-modal">×</span>
                </div>
                <div class="modal-body">
                    <p>Your new appointment time has been successfully booked! 🎉</p>
                </div>
                <div class="modal-footer">
                    <a href="/my-account/orders/" class="w-btn us-btn-style_6 w-btn-underlined">Back to My Bookings</a>
                </div>
            </div>
        </div>';

        // Cancel Success Modal
        echo '<div id="cancelSuccessModal" class="status-modal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Proposed Appointment Declined</h5>
                    <span class="close-modal">×</span>
                </div>
                <div class="modal-body">
                    <p>Your booking request has been cancelled. No payment has been charged.</p>
                </div>
                <div class="modal-footer">
                    <a href="/my-account/orders/" class="w-btn us-btn-style_6 w-btn-underlined">Back to My Bookings</a>
                </div>
            </div>
        </div>';


        echo '<div id="' . esc_attr($modal_accept) . '" class="status-modal"  style="display:none;">
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

        echo '<div id="' . esc_attr($modal_decline) . '" class="status-modal" style="display:none;">
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

        echo '<div id="cancelReasonModal_' . esc_attr($booking['order_id']) . '" class="status-modal cancel-reason-modal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header"><h5>Help us improve, tell us why you’re cancelling</h5><span class="close-modal">×</span></div>
                <div class="modal-body">
                    <p>Your feedback helps us improve the booking experience.</p>
                    <p id="cancel-reason-error-' . esc_attr($booking['order_id']) . '" class="cancel-error-msg" style="color:#c00; display:none;"></p>
                    <form id="cancel-reason-form-' . esc_attr($booking['order_id']) . '">
                        <label><input type="radio" name="reason" value="scheduling"> I had a scheduling conflict</label><br>
                        <label><input type="radio" name="reason" value="changed-mind"> I changed my mind</label><br>
                        <label><input type="radio" name="reason" value="no-response"> The stylist didn’t respond in time</label><br>
                        <label><input type="radio" name="reason" value="stylist-cancel"> The stylist asked me to cancel</label><br>
                        <label><input type="radio" name="reason" value="other"> Other</label>
                        <div class="other-reason-wrapper" style="display: none; margin-top: 10px;">
                            <textarea name="other_reason" placeholder="Enter your reason here..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel"
                        data-order-id="' . esc_attr($booking['order_id']) . '">
                        <span class="spinner hidden"></span>
                        <span class="btn-text">Cancel Booking</span>
                    </button>
                </div>
            </div>
        </div>';


        echo $mobile_cards;
        echo '</div>'; // .frohub_table_wrapper
    }

echo '<script>
jQuery(document).ready(function($) {

    // Open modal
    $(".modal-trigger").click(function () {
        const modalId = $(this).data("modal");
        const startTime = $(this).data("start");
        const orderId = $(this).data("order-id");

        $("body").addClass("frohub-modal-open");
        $("#" + modalId).fadeIn(200);

        $("#" + modalId).find(".modal-start-time").text(startTime);
        $("#" + modalId).find(".confirm-proposed-date, .decline-proposed-date")
            .attr("data-order-id", orderId)
            .attr("data-start", startTime);
    });

    // Close modal
    $(".close-modal, .close-modal-text").click(function () {
        $(".status-modal").fadeOut(200);
        $("body").removeClass("frohub-modal-open");
    });

    // Click outside to close
    $(window).click(function (e) {
        $(".status-modal").each(function () {
            if (e.target === this) {
                $(this).fadeOut(200);
                $("body").removeClass("frohub-modal-open");
            }
        });
    });

    // Show "Other reason" field if selected
    $(document).on("change", "input[name=\'reason\']", function () {
        const wrapper = $(this).closest("form").find(".other-reason-wrapper");
        if ($(this).val() === "other") {
            wrapper.slideDown();
        } else {
            wrapper.slideUp();
        }
    });

    // Spinner controls
    function showSpinner(button) {
        const footer = button.closest(".modal-footer");
        footer.find("button").prop("disabled", true).not(button).hide();
        button.find(".btn-text").hide();
        button.find(".spinner").removeClass("hidden");
    }

    function hideSpinner(button) {
        const footer = button.closest(".modal-footer");
        footer.find("button").prop("disabled", false).show();
        button.find(".btn-text").show();
        button.find(".spinner").addClass("hidden");
    }

    // Accept proposed time
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
                $(".status-modal").fadeOut(200);
                $("#acceptSuccessModal").fadeIn(200);
            } else {
                alert("Error: " + (response?.data?.message || "Unknown error occurred."));
            }
        }).fail(function () {
            hideSpinner(button);
            alert("Network error. Please try again.");
        });
    });

    // Decline opens reason modal
    $(".decline-proposed-date").click(function () {
        const button = $(this);
        const orderId = button.data("order-id");

        $(".status-modal").fadeOut(200);
        $("#cancelReasonModal_" + orderId).fadeIn(200);
    });

    // Submit cancel reason
    $(".submit-final-cancel").click(function () {
        const button = $(this);
        const orderId = button.data("order-id");
        const form = $("#cancel-reason-form-" + orderId);
        const errorBox = $("#cancel-reason-error-" + orderId);

        const selectedReason = form.find("input[name=\'reason\']:checked").val();
        const otherText = form.find("textarea[name=\'other_reason\']").val();

        errorBox.hide().text("");

        if (!selectedReason) {
            errorBox.text("Please select a reason for declining.").show();
            return;
        }

        if (selectedReason === "other" && (!otherText || !otherText.trim())) {
            errorBox.text("Please enter your reason in the textbox.").show();
            return;
        }

        showSpinner(button);

        $.ajax({
            url: "' . admin_url('admin-ajax.php') . '",
            method: "POST",
            data: {
                action: "decline_new_proposed_time",
                security: "' . wp_create_nonce('ajax_nonce') . '",
                order_id: orderId,
                reason: selectedReason,
                other_reason: otherText
            },
            success: function (response) {
                hideSpinner(button);
                if (response.success) {
                    $(".status-modal").fadeOut(200);
                    $("#cancelSuccessModal").fadeIn(200);
                } else {
                    errorBox.text(response?.data?.message || "Something went wrong.").show();
                }
            },
            error: function () {
                hideSpinner(button);
                errorBox.text("Something went wrong. Please try again.").show();
            }
        });
    });

});
</script>';


    return ob_get_clean();
}

}
