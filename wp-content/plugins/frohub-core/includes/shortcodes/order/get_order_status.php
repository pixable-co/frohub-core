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

            echo '<span class="status_text">' . esc_html($status_label) . '</span>';

            if ($status === 'on-hold') {
                $modal_cancel = "cancelModal_" . $order_id;

                echo '<button class="modal-trigger btn-danger btn-sm ms-3"
                        data-modal="' . esc_attr($modal_cancel) . '"
                        data-order-id="' . esc_attr($order_id) . '">
                        Cancel Order</button>';

                // Modal for cancellation confirmation
                echo '<div id="' . esc_attr($modal_cancel) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5>Cancel Booking?</h5>
                                <span class="close-modal">×</span>
                            </div>
                            <div class="modal-body">
                                <p class="confirmation-text">Are you sure you want to cancel this booking request?</p>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined confirm-cancel-order"
                                    data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span> Yes, Cancel Booking</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                      </div>';
                // Modal for cancellation reason
                echo '<div id="cancelReasonModal_' . $order_id . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                            <h5>Help us improve, tell us why you’re cancelling</h5>
                            <span class="close-modal">×</span>
                            </div>
                            <div class="modal-body">
                            <p>We’d love to know why you decided to cancel. Your feedback helps us improve the booking experience for you and others.</p>
                           
                            <p id="cancel-reason-error-' . $order_id . '" class="cancel-error-msg" style="display:none; color: #c00; margin-bottom: 10px;"></p>

                            <form id="cancel-reason-form-' . $order_id . '">
                                <label><input type="radio" name="reason" value="scheduling"> I had a scheduling conflict</label><br>
                                <label><input type="radio" name="reason" value="changed-mind"> I changed my mind</label><br>
                                <label><input type="radio" name="reason" value="no-response"> The stylist didn’t respond in time</label><br>
                                <label><input type="radio" name="reason" value="stylist-cancel"> The stylist asked me to cancel</label><br>
                                <label>
                                <input type="radio" name="reason" value="other"> Other
                                </label>
                                <div class="other-reason-wrapper" style="display: none; margin-top: 10px;">
                                <textarea name="other_reason" placeholder="Enter your reason here..."></textarea>
                                </div>
                            </form>
                            </div>
                            <div class="modal-footer">
                            <button class="w-btn us-btn-style_6 w-btn-underlined submit-final-cancel" data-order-id="' . $order_id . '">Cancel Booking</button>
                            </div>
                        </div>
                        </div>';
                // Modal for booking cancellation confirmation
                echo '<div id="cancelSuccessModal" class="status-modal" style="display:none;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5>Booking Cancelled</h5>
                            </div>
                            <div class="modal-body">
                                <p>Your booking has been successfully cancelled.</p>
                            </div>
                            <div class="modal-footer">
                                <a href="/my-account/bookings/" class="w-btn us-btn-style_6 w-btn-underlined">Back to My Bookings</a>
                            </div>
                        </div>
                    </div>';
            } elseif ($status === 'processing') {
                $start_date = '';

                foreach ($order->get_items() as $item_id => $item) {
                    $product_id = $item->get_product_id();
                    if ($product_id == 28990) continue;

                    $start_date = $item->get_meta('Start Date Time');
                    if (!empty($start_date)) break;
                }

                $start_date_timestamp = strtotime($start_date);
                $current_date_timestamp = time();
                $days_difference = floor(($start_date_timestamp - $current_date_timestamp) / (60 * 60 * 24));

                $cancel_type = ($days_difference > 7) ? 'early' : 'late';
                $modal_id = ($cancel_type === 'early') ? "earlyCancelModal_" . $order_id : "lateCancelModal_" . $order_id;

                echo '<button class="modal-trigger btn-danger btn-sm ms-3"
                        data-modal="' . esc_attr($modal_id) . '"
                        data-order-id="' . esc_attr($order_id) . '">
                        Cancel Order</button>';

                echo '<div id="earlyCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5>Cancel Booking?</h5>
                                <span class="close-modal">×</span>
                            </div>
                            <div class="modal-body">
                                <p>You\'re within the early cancellation window. If you proceed, your deposit will be refunded, but the booking fee is non-refundable.</p>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined confirm-early-cancel-order"
                                    data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span> Yes, Cancel Booking</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                      </div>';

                echo '<div id="lateCancelModal_' . esc_attr($order_id) . '" class="status-modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5>Cancel Booking?</h5>
                                <span class="close-modal">×</span>
                            </div>
                            <div class="modal-body">
                                <p>If you cancel now, your deposit and booking fee will not be refunded as per our cancellation policy.</p>
                                <p class="confirmation-text">Are you sure you want to cancel?</p>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined confirm-late-cancel-order"
                                    data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span> Yes, Cancel Anyway</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                            </div>
                        </div>
                      </div>';
            }
        }
?>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(".modal-trigger").click(function() {
                    var modalId = $(this).data("modal");
                    var orderId = $(this).data("order-id");

                    $("#" + modalId).css("display", "block");
                    $("#" + modalId).find(".confirm-early-cancel-order, .confirm-late-cancel-order").attr("data-order-id", orderId);
                });

                $(".close-modal, .close-modal-text").click(function() {
                    $(".status-modal").css("display", "none");
                });

                $(window).click(function(event) {
                    $(".status-modal").each(function() {
                        if (event.target === this) {
                            $(this).css("display", "none");
                        }
                    });
                });

                function showSpinner(button) {
                    $(button).prop("disabled", true);
                    $(button).find(".spinner").removeClass("hidden");
                }

                function hideSpinner(button) {
                    $(button).prop("disabled", false);
                    $(button).find(".spinner").addClass("hidden");
                }

                // Toggle visibility of "Other" textarea
                $('input[name="reason"]').on('change', function() {
                    var form = $(this).closest("form");
                    var selected = form.find('input[name="reason"]:checked').val();

                    if (selected === 'other') {
                        form.find('.other-reason-wrapper').slideDown();
                    } else {
                        form.find('.other-reason-wrapper').slideUp();
                    }
                });


                $(".confirm-cancel-order").click(function() {
                    // First modal triggers second modal instead of AJAX directly
                    var orderId = $(this).data("order-id");
                    $("#cancelModal_" + orderId).hide();
                    $("#cancelReasonModal_" + orderId).show();
                });

                $(".submit-final-cancel").click(function() {

                    var button = $(this);
                    var orderId = button.data("order-id");
                    var form = $("#cancel-reason-form-" + orderId);
                    var selectedReason = form.find("input[name='reason']:checked").val();
                    var otherText = form.find("textarea[name='other_reason']").val();
                    var errorBox = $("#cancel-reason-error-" + orderId);

                    // Reset error box
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
                        action: "cancel_order",
                        security: "<?php echo wp_create_nonce('ajax_nonce'); ?>",
                        order_id: orderId,
                        reason: selectedReason,
                        other_reason: otherText
                    }, function(response) {
                        hideSpinner(button);
                        if (response.success) {
                            $(".status-modal").hide(); // Hide all open modals
                            $("#cancelSuccessModal").fadeIn();
                        } else {
                            alert(response.data.message);
                        }
                    });
                });


                $(".confirm-early-cancel-order").click(function() {
                    var button = $(this);
                    var orderId = button.data("order-id");

                    showSpinner(button);

                    $.post("<?php echo admin_url('admin-ajax.php'); ?>", {
                        action: "early_cancel_order",
                        security: "<?php echo wp_create_nonce('ajax_nonce'); ?>",
                        order_id: orderId
                    }, function(response) {
                        hideSpinner(button);
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    });
                });

                $(".confirm-late-cancel-order").click(function() {
                    var button = $(this);
                    var orderId = button.data("order-id");

                    showSpinner(button);

                    $.post("<?php echo admin_url('admin-ajax.php'); ?>", {
                        action: "late_cancel_order",
                        security: "<?php echo wp_create_nonce('ajax_nonce'); ?>",
                        order_id: orderId
                    }, function(response) {
                        hideSpinner(button);
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    });
                });
            });
        </script>

<?php
        return ob_get_clean();
    }
}
