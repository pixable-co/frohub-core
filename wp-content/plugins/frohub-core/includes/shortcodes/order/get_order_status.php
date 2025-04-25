<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class GetOrderStatus
{

    public static function init()
    {
        $self = new self();
        add_shortcode('get_order_status', array($self, 'get_order_status_shortcode'));
    }

    public function get_order_status_shortcode()
    {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            $status = $order->get_status();
            $cancellation_status = get_field('cancellation_status', $order_id);
            
            $status_labels = [
                'pending'       => 'Pending payment',
                'processing'    => 'Processing',
                'on-hold'       => 'On hold',
                'completed'     => 'Completed',
                'cancelled'     => 'Cancelled',
                'refunded'      => 'Refunded',
                'failed'        => 'Failed',
                'rescheduling'  => 'Rescheduling'
            ];
            
            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : 'Unknown Status';
            
// Override label if status is 'cancelled' and cancellation_status field is meaningful
if ($status === 'cancelled') {
    $field_obj = get_field_object('cancellation_status', $order_id);
    $value = isset($field_obj['value']) ? $field_obj['value'] : '';
    $label = isset($field_obj['choices'][$value]) ? $field_obj['choices'][$value] : '';

    if (!empty($value) && $value !== 'N/A' && !empty($label)) {
        $status_label = esc_html($label);
    }
}

            
            echo '<span class="status_text">' . esc_html($status_label) . '</span>';
            

            if ($status === 'on-hold' || $status === 'processing') {
                $is_early_cancel = false;
                if ($status === 'processing') {
                    $start_date = '';
                    foreach ($order->get_items() as $item) {
                        $product_id = $item->get_product_id();
                        if ($product_id == 28990) continue;
                        $start_date = $item->get_meta('Start Date Time');
                        if (!empty($start_date)) break;
                    }
                    $days_difference = floor((strtotime($start_date) - time()) / (60 * 60 * 24));
                    $is_early_cancel = $days_difference > 7;
                }

                $cancel_type = ($status === 'on-hold') ? 'normal' : ($is_early_cancel ? 'early' : 'late');
                $modal_id = $cancel_type . "CancelModal_" . $order_id;

                echo '<button class="modal-trigger btn-danger btn-sm ms-3"
                        data-modal="' . esc_attr($modal_id) . '"
                        data-order-id="' . esc_attr($order_id) . '">
                        Cancel Order</button>';

                // Confirmation modals
                echo '<div id="normalCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                            <div class="modal-body"><p class="confirmation-text">Are you sure you want to cancel this booking request?</p></div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined confirm-normal-cancel-order" data-order-id="' . esc_attr($order_id) . '"><span class="spinner hidden"></span> Yes, Cancel Booking</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                      </div>';

                echo '<div id="earlyCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                            <div class="modal-body"><p>You\'re within the early cancellation window. If you proceed, your deposit will be refunded, but the booking fee is non-refundable.</p></div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined confirm-early-cancel-order" data-order-id="' . esc_attr($order_id) . '"><span class="spinner hidden"></span> Yes, Cancel Booking</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                      </div>';

                echo '<div id="lateCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                            <div class="modal-body"><p>If you cancel now, your deposit and booking fee will not be refunded as per our cancellation policy.</p><p class="confirmation-text">Are you sure you want to cancel?</p></div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined confirm-late-cancel-order" data-order-id="' . esc_attr($order_id) . '"><span class="spinner hidden"></span> Yes, Cancel Anyway</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                      </div>';

                // Shared reason modal
                echo '<div id="cancelReasonModal_' . $order_id . '" class="status-modal cancel-reason-modal">
                        <div class="modal-content">
                            <div class="modal-header"><h5>Help us improve, tell us why you’re cancelling</h5><span class="close-modal">×</span></div>
                            <div class="modal-body">
                                <p>Your feedback helps us improve the booking experience.</p>
                                <p id="cancel-reason-error-' . $order_id . '" class="cancel-error-msg" style="color:#c00; display:none;"></p>
                                <form id="cancel-reason-form-' . $order_id . '">
                                    <label><input type="radio" name="reason" value="scheduling"> I had a scheduling conflict</label><br>
                                    <label><input type="radio" name="reason" value="changed-mind"> I changed my mind</label><br>
                                    <label><input type="radio" name="reason" value="no-response"> The stylist didn’t respond in time</label><br>
                                    <label><input type="radio" name="reason" value="stylist-cancel"> The stylist asked me to cancel</label><br>
                                    <label><input type="radio" name="reason" value="other"> Other</label>
                                    <div class="other-reason-wrapper" style="display: none; margin-top: 10px;"><textarea name="other_reason" placeholder="Enter your reason here..."></textarea></div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel-normal" data-order-id="' . $order_id . '" style="display:none;">Cancel Booking</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel-early" data-order-id="' . $order_id . '" style="display:none;">Cancel Booking</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel-late" data-order-id="' . $order_id . '" style="display:none;">Cancel Booking</button>
                            </div>
                        </div>
                      </div>';

                // Success modal (shared with dynamic content)
                echo '<div id="cancelSuccessModal" class="status-modal" style="display:none;">
                        <div class="modal-content">
                            <div class="modal-header"><h5 id="cancel-success-title">Booking Cancelled</h5></div>
                            <div class="modal-body"><p id="cancel-success-message">Your booking has been successfully cancelled.</p></div>
                            <div class="modal-footer"><a href="/my-account/bookings/" class="w-btn us-btn-style_6 w-btn-underlined">Back to My Bookings</a></div>
                      </div></div>';
            }
        }
?>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function showSpinner(button) {
                    $(button).prop("disabled", true).find(".spinner").removeClass("hidden");
                }

                function hideSpinner(button) {
                    $(button).prop("disabled", false).find(".spinner").addClass("hidden");
                }

                function handleReasonSubmit(button, orderId, action) {
                    var form = $("#cancel-reason-form-" + orderId);
                    var errorBox = $("#cancel-reason-error-" + orderId);
                    var selectedReason = form.find("input[name='reason']:checked").val();
                    var otherText = form.find("textarea[name='other_reason']").val();
                    errorBox.hide().text("");

                    if (!selectedReason) {
                        errorBox.text("Please select a reason for cancellation.").show();
                        return;
                    }
                    if (selectedReason === 'other' && !otherText.trim()) {
                        errorBox.text("Please enter a reason in the text box.").show();
                        return;
                    }

                    showSpinner(button);
                    $.post("<?php echo admin_url('admin-ajax.php'); ?>", {
                        action: action,
                        security: "<?php echo wp_create_nonce('ajax_nonce'); ?>",
                        order_id: orderId,
                        reason: selectedReason,
                        other_reason: otherText
                    }, function(response) {
                        hideSpinner(button);
                        if (response.success) {
                            let title = "Booking Cancelled";
                            let message = "Your booking has been successfully cancelled.";
                            if (action === "early_cancel_order") {
                                title = "Booking Cancelled – Deposit Refund on Its Way!";
                                message = "Your booking has been successfully cancelled. Your deposit refund is being processed and will be returned to your original payment method shortly.";
                            } else if (action === "late_cancel_order") {
                                message = "Your booking has been successfully cancelled. Please note the deposit and booking fee were non-refundable.";
                            }
                            $("#cancel-success-title").text(title);
                            $("#cancel-success-message").text(message);
                            $(".status-modal").hide();
                            $("#cancelSuccessModal").fadeIn();
                        } else {
                            errorBox.text(response.data.message).show();
                        }
                    });
                }

                $(".modal-trigger").click(function() {
                    $("#" + $(this).data("modal")).fadeIn();
                });

                $(".close-modal, .close-modal-text").click(function() {
                    $(".status-modal").fadeOut();
                });

                $(window).click(function(event) {
                    $(".status-modal").each(function() {
                        if (event.target === this) $(this).fadeOut();
                    });
                });

                $('input[name="reason"]').on('change', function() {
                    var form = $(this).closest("form");
                    var selected = form.find('input[name="reason"]:checked').val();
                    form.find('.other-reason-wrapper').toggle(selected === 'other');
                });

                $(".confirm-normal-cancel-order").click(function() {
                    var orderId = $(this).data("order-id");
                    $("#normalCancelModal_" + orderId).hide();
                    var modal = $("#cancelReasonModal_" + orderId);
                    modal.find(".submit-final-cancel-normal").show();
                    modal.find(".submit-final-cancel-early, .submit-final-cancel-late").hide();
                    modal.show();
                });


                $(".confirm-early-cancel-order").click(function() {
                    var orderId = $(this).data("order-id");
                    $("#earlyCancelModal_" + orderId).hide();
                    var modal = $("#cancelReasonModal_" + orderId);
                    modal.find(".submit-final-cancel-early").show();
                    modal.find(".submit-final-cancel-normal, .submit-final-cancel-late").hide();
                    modal.show();
                });

                $(".confirm-late-cancel-order").click(function() {
                    var orderId = $(this).data("order-id");
                    $("#lateCancelModal_" + orderId).hide();
                    var modal = $("#cancelReasonModal_" + orderId);
                    modal.find(".submit-final-cancel-late").show();
                    modal.find(".submit-final-cancel-normal, .submit-final-cancel-early").hide();
                    modal.show();
                });

                $(".submit-final-cancel-normal").click(function() {
                    handleReasonSubmit(this, $(this).data("order-id"), "cancel_order");
                });

                $(".submit-final-cancel-early").click(function() {
                    handleReasonSubmit(this, $(this).data("order-id"), "early_cancel_order");
                });

                $(".submit-final-cancel-late").click(function() {
                    handleReasonSubmit(this, $(this).data("order-id"), "late_cancel_order");
                });
            });
        </script>

<?php
        return ob_get_clean();
    }
}
