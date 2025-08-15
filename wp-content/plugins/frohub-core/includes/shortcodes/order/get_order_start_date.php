<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class GetOrderStartDate
{

    public static function init()
    {
        $self = new self();
        add_shortcode('get_order_start_date', array($self, 'get_order_start_date_shortcode'));
    }

public function get_order_start_date_shortcode()
{
    ob_start();

    $order_id = $GLOBALS['single_order_id'];
    $order = wc_get_order($order_id);

    if (!empty($order)) {
        $order_status = $order->get_status();

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            if ($product_id == 28990) continue;

            $start_date_time          = wc_get_order_item_meta($item_id, 'Start Date Time', true);
            $proposed_start_date_time = wc_get_order_item_meta($item_id, 'Proposed Start Date Time', true);
            $proposed_end_date_time   = wc_get_order_item_meta($item_id, 'Proposed End Date Time', true);

            $formatted_start_date_time = !empty($start_date_time)
                ? date('d M Y, \a\t H:i', strtotime($start_date_time))
                : '—';

            // Unique IDs
            $modal_accept         = "acceptModal_{$order_id}_{$item_id}";
            $modal_decline        = "declineModal_{$order_id}_{$item_id}";
            $modal_reason         = "cancelReasonModal_{$order_id}";
            $modal_success_cancel = "cancelSuccessModal_{$order_id}";
            $modal_success_accept = "acceptSuccessModal_{$order_id}";

            echo '<div class="appointment-container">';

            // Always open + close the proposed-time container
            echo '<div class="appointment-proposed-time-container">';
            echo '<span class="fw-bold me-2">' . esc_html($formatted_start_date_time) . '</span>';

            if ($order_status === 'rescheduling' && !empty($proposed_start_date_time)) {
                $formatted_proposed_start = date('d M Y, \a\t H:i', strtotime($proposed_start_date_time));
                $formatted_proposed_end   = !empty($proposed_end_date_time) ? date('d M Y, \a\t H:i', strtotime($proposed_end_date_time)) : '';

                $conversation_post = get_field('conversation', $order_id);
                $conversation_url  = !empty($conversation_post) ? get_permalink($conversation_post) : '#';

                echo '<i class="fas fa-arrows-alt-h"></i><span class="text-muted me-3">' . esc_html($formatted_proposed_start) . '</span>';
            }

            echo '</div>'; // end .appointment-proposed-time-container

            // Rescheduling buttons + modals (only when rescheduling)
            if ($order_status === 'rescheduling' && !empty($proposed_start_date_time)) {
                echo '<div class="button-container">';
                echo '<button class="modal-trigger w-btn us-btn-style_5"
                        data-modal="' . esc_attr($modal_accept) . '"
                        data-order-id="' . esc_attr($order_id) . '"
                        data-start="' . esc_attr($formatted_proposed_start) . '"
                        data-end="' . esc_attr($formatted_proposed_end) . '">Accept</button>';

                echo '<button class="modal-trigger w-btn us-btn-style_6"
                        data-modal="' . esc_attr($modal_decline) . '"
                        data-order-id="' . esc_attr($order_id) . '"
                        data-start="' . esc_attr($formatted_proposed_start) . '"
                        data-end="' . esc_attr($formatted_proposed_end) . '">Decline</button>';
                echo '</div>';

                // Accept modal
                echo '<div id="' . esc_attr($modal_accept) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5>Confirm New Appointment</h5>
                                <span class="close-modal">×</span>
                            </div>
                            <div class="modal-body">
                                <p>You’re about to confirm the new appointment time proposed by your stylist. Once confirmed, the booking will be updated.</p>
                                <p><strong>Proposed Time:</strong> <span class="modal-start-time"></span></p>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined confirm-proposed-date"
                                    data-order-id="' . esc_attr($order_id) . '"
                                    data-start="' . esc_attr($formatted_proposed_start) . '">
                                    <span class="spinner hidden"></span> <span class="btn-text">Yes, Confirm Appointment</span></button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Go Back</button>
                            </div>
                        </div>
                    </div>';

                // Decline modal
                echo '<div id="' . esc_attr($modal_decline) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5>Decline Proposed Appointment</h5>
                                <span class="close-modal">×</span>
                            </div>
                            <div class="modal-body">
                                <p>If you decline this proposed appointment time, your booking request will be rejected.</p>
                                <p><strong>Proposed Time:</strong> <span class="modal-start-time"></span></p>
                                <p>Want a different time? <a href="' . esc_url($conversation_url) . '" class="underline" target="_blank">Message your stylist</a> to suggest an alternative before you decline.</p>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined decline-proposed-date"
                                    data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span> <span class="btn-text">Yes, Decline Appointment</span></button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Go Back</button>
                            </div>
                        </div>
                    </div>';

                // Shared Cancel Reason Modal (one per order)
                // (Safe to output once per order-item loop: it reuses same ID for the order, not the item)
                echo '<div id="' . esc_attr($modal_reason) . '" class="status-modal cancel-reason-modal" style="display:none;">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Help us improve, tell us why you’re cancelling</h5><span class="close-modal">×</span></div>
                            <div class="modal-body">
                                <p>Your feedback helps us improve the booking experience.</p>
                                <p id="cancel-reason-error-' . esc_attr($order_id) . '" class="cancel-error-msg" style="color:#c00; display:none;"></p>
                                <form id="cancel-reason-form-' . esc_attr($order_id) . '">
                                    <label><input type="radio" name="reason" value="scheduling"> I had a scheduling conflict</label><br>
                                    <label><input type="radio" name="reason" value="changed-mind"> I changed my mind</label><br>
                                    <label><input type="radio" name="reason" value="no-response"> The stylist didn’t respond in time</label><br>
                                    <label><input type="radio" name="reason" value="stylist-cancel"> The stylist asked me to cancel</label><br>
                                    <label><input type="radio" name="reason" value="other"> Other</label>
                                    <div class="other-reason-wrapper" style="display:none; margin-top:10px;">
                                        <textarea name="other_reason" placeholder="Enter your reason here..."></textarea>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel" data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span>
                                    <span class="btn-text">Cancel Booking</span>
                                </button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Go Back</button>
                            </div>
                        </div>
                    </div>';

                // Success modals (order-scoped)
                echo '<div id="' . esc_attr($modal_success_cancel) . '" class="status-modal" style="display:none;">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Proposed Appointment Declined</h5></div>
                            <div class="modal-body"><p>Your booking request has been cancelled. No payment has been charged.</p></div>
                            <div class="modal-footer"><a href="/my-account/orders/" class="w-btn us-btn-style_6 w-btn-underlined">Back to My Bookings</a></div>
                        </div>
                    </div>';

                echo '<div id="' . esc_attr($modal_success_accept) . '" class="status-modal" style="display:none;">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Appointment Confirmed!</h5></div>
                            <div class="modal-body"><p>Your new appointment time has been successfully booked! 🎉</p></div>
                            <div class="modal-footer"><a href="/my-account/orders/" class="w-btn us-btn-style_6 w-btn-underlined">Back to My Bookings</a></div>
                        </div>
                    </div>';
            }

            // If order is on hold or processing (use $order_status)
            if ($order_status === 'on-hold' || $order_status === 'processing') {
                $is_early_cancel = false;
                if ($order_status === 'processing') {
                    $start_date = '';
                    foreach ($order->get_items() as $it) {
                        $pid = $it->get_product_id();
                        if ($pid == 28990) continue;
                        $start_date = $it->get_meta('Start Date Time');
                        if (!empty($start_date)) break;
                    }
                    $days_difference = $start_date ? floor((strtotime($start_date) - time()) / (60 * 60 * 24)) : 0;
                    $is_early_cancel = $days_difference > 7;
                }

                $cancel_type = ($order_status === 'on-hold') ? 'normal' : ($is_early_cancel ? 'early' : 'late');
                $modal_id    = $cancel_type . "CancelModal_" . $order_id;

                echo '<button class="modal-trigger w-btn us-btn-style_6 w-btn-underlined"
                        data-modal="' . esc_attr($modal_id) . '"
                        data-order-id="' . esc_attr($order_id) . '">Cancel Booking</button>';

                // Confirmation modals
                echo '<div id="normalCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                            <div class="modal-body"><p class="confirmation-text">Are you sure you want to cancel this booking request?</p></div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel" data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span><span class="btn-text">Yes, Cancel Booking</span>
                                </button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                    </div>';

                echo '<div id="earlyCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                            <div class="modal-body"><p>You\'re within the early cancellation window. If you proceed, your deposit will be refunded, but the booking fee is non-refundable.</p></div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel" data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span><span class="btn-text">Yes, Cancel Booking</span>
                                </button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                    </div>';

                echo '<div id="lateCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                            <div class="modal-body"><i class="fas fa-exclamation-triangle" style="font-size:2rem; margin-bottom:1rem;"></i><p>Since your appointment is within 7 days, this counts as a late cancellation. Your deposit and booking fee won’t be refunded as per our cancellation policy.</p><p class="confirmation-text">Are you sure you want to cancel?</p></div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel" data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span><span class="btn-text">Yes, Cancel Anyway</span>
                                </button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                    </div>';
            }

            echo '</div>'; // end .appointment-container
        }
    }
    ?>

    <script type="text/javascript">
    jQuery(document).ready(function ($) {
        $(".modal-trigger").click(function () {
            var modalId = $(this).data("modal");
            var startTime = $(this).data("start");
            var orderId = $(this).data("order-id");

            $("#" + modalId).css("display", "block");
            $("#" + modalId).find(".modal-start-time").text(startTime || "");
            $("#" + modalId).find(".confirm-proposed-date, .decline-proposed-date")
                .attr("data-order-id", orderId)
                .attr("data-start", startTime || "");
        });

        $(".close-modal, .close-modal-text").click(function () {
            $(".status-modal").css("display", "none");
        });

        $(window).on("click", function (event) {
            $(".status-modal").each(function () {
                if (event.target === this) $(this).css("display", "none");
            });
        });

        function showSpinner(button) {
            var modalFooter = button.closest(".modal-footer");
            modalFooter.find("button").prop("disabled", true);
            modalFooter.find("button").not(button).hide();
            button.find(".btn-text").hide();
            button.find(".spinner").removeClass("hidden");
        }

        function hideSpinner(button) {
            var modalFooter = button.closest(".modal-footer");
            modalFooter.find("button").prop("disabled", false).show();
            button.find(".btn-text").show();
            button.find(".spinner").addClass("hidden");
        }

        $(".confirm-proposed-date").click(function () {
            var button = $(this);
            var orderId = button.data("order-id");
            showSpinner(button);

            $.ajax({
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                type: "POST",
                data: {
                    action: "accept_new_time",
                    security: "<?php echo wp_create_nonce('ajax_nonce'); ?>",
                    order_id: orderId
                },
                success: function (response) {
                    hideSpinner(button);
                    if (response && response.success) {
                        $(".status-modal").hide();
                        $("#acceptSuccessModal_" + orderId).fadeIn();
                    } else {
                        console.error("Error:", response && response.data ? response.data.message : "Unknown error");
                    }
                },
                error: function (xhr, status, error) {
                    hideSpinner(button);
                    console.error("AJAX Error:", status, error);
                }
            });
        });

        $(".decline-proposed-date").click(function () {
            var button = $(this);
            var orderId = button.data("order-id");
            $(".status-modal").hide();
            $("#cancelReasonModal_" + orderId).show();
        });

        $(document).on("change", 'input[name="reason"]', function () {
            var wrap = $(this).closest("form").find(".other-reason-wrapper");
            if ($(this).val() === "other") wrap.slideDown(); else wrap.slideUp();
        });

        // One handler for all final cancel buttons (normal/early/late)
        $(document).on("click", ".submit-final-cancel", function () {
            var button = $(this);
            var orderId = button.data("order-id");
            var form = $("#cancel-reason-form-" + orderId);
            var errorBox = $("#cancel-reason-error-" + orderId);

            var selectedReason = form.find("input[name='reason']:checked").val();
            var otherText = form.find("textarea[name='other_reason']").val();

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
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                type: "POST",
                data: {
                    action: "decline_new_proposed_time",
                    security: "<?php echo wp_create_nonce('ajax_nonce'); ?>",
                    order_id: orderId,
                    reason: selectedReason,
                    other_reason: otherText
                },
                success: function (response) {
                    hideSpinner(button);
                    if (response && response.success) {
                        $(".status-modal").hide();
                        $("#cancelSuccessModal_" + orderId).fadeIn();
                    } else {
                        errorBox.text((response && response.data && response.data.message) || "An unexpected error occurred.").show();
                    }
                },
                error: function () {
                    hideSpinner(button);
                    errorBox.text("Something went wrong. Please try again.").show();
                }
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}

}
