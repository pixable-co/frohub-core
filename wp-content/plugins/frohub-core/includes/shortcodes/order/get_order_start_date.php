<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetOrderStartDate {

    public static function init() {
        $self = new self();
        add_shortcode( 'get_order_start_date', array($self, 'get_order_start_date_shortcode') );
    }

    public function get_order_start_date_shortcode() {
        ob_start();

        $order_id = $GLOBALS['single_order_id'];
        $order = wc_get_order($order_id);

        if (!empty($order)) {
            $order_status = $order->get_status();

            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();

                if ($product_id == 28990 ) continue;

                $start_date_time = wc_get_order_item_meta($item_id, 'Start Date Time', true);
                $proposed_start_date_time = wc_get_order_item_meta($item_id, 'Proposed Start Date Time', true);
                $proposed_end_date_time = wc_get_order_item_meta($item_id, 'Proposed End Date Time', true);

                if (!empty($start_date_time)) {
                    $formatted_start_date_time = date('d M Y, \a\t H:i', strtotime($start_date_time));
                }

                echo '<div class="appointment-container">
                        <span class="fw-bold me-2">' . esc_html($formatted_start_date_time) . '</span>';

                if ($order_status === 'rescheduling' && !empty($proposed_start_date_time)) {
                    $formatted_proposed_start = date('d M Y, \a\t H:i', strtotime($proposed_start_date_time));
                    $formatted_proposed_end = !empty($proposed_end_date_time) ? date('d M Y, \a\t H:i', strtotime($proposed_end_date_time)) : '';

                    $modal_accept = "acceptModal_" . $item_id;
                    $modal_decline = "declineModal_" . $item_id;

                    $conversation_post = get_field('conversation', $order_id);
                    $conversation_url = !empty($conversation_post) ? get_permalink($conversation_post) : '#';


                    echo '<i class="fas fa-arrows-alt-h mx-2"></i>
                          <span class="text-muted me-3">' . esc_html($formatted_proposed_start) . '</span>';

                    echo '<button class="modal-trigger btn-light btn-sm me-2"
                            data-modal="' . esc_attr($modal_accept) . '"
                            data-order-id="' . esc_attr($order_id) . '"
                            data-start="' . esc_attr($formatted_proposed_start) . '"
                            data-end="' . esc_attr($formatted_proposed_end) . '">
                            Accept</button>';

                    echo '<button class="modal-trigger btn-link text-dark btn-sm"
                            data-modal="' . esc_attr($modal_decline) . '"
                            data-order-id="' . esc_attr($order_id) . '"
                            data-start="' . esc_attr($formatted_proposed_start) . '"
                            data-end="' . esc_attr($formatted_proposed_end) . '">
                            Decline</button>';

                            echo '<div id="' . esc_attr($modal_accept) . '" class="status-modal">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5>Confirm New Appointment</h5>
                                    <span class="close-modal">×</span>
                                </div>
                                <div class="modal-body">
                                    <p>You’re about to confirm the new appointment time proposed by your stylist. Once confirmed, the booking will be updated.</p>
                                    <p><strong>Proposed Start:</strong> <span class="modal-start-time"></span></p>
                                </div>
                                <div class="modal-footer">
                                    <button class="w-btn us-btn-style_6 w-btn-underlined confirm-proposed-date"
                                        data-order-id="' . esc_attr($order_id) . '"
                                        data-start="' . esc_attr($formatted_proposed_start) . '">
                                        <span class="spinner hidden"></span> Yes, Confirm Appointment</button>
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
                                <p>If you decline this proposed appointment time, your booking request will be rejected.</p>
                                <p><strong>Proposed Start:</strong> <span class="modal-start-time"></span></p>
                                <p>Want a different time? <a href="' . esc_url($conversation_url) . '" class="underline" target="_blank">Message your stylist</a> to suggest an alternative before you decline.</p>
                            </div>
                            <div class="modal-footer">
                                <button class="w-btn us-btn-style_6 w-btn-underlined decline-proposed-date"
                                    data-order-id="' . esc_attr($order_id) . '">
                                    <span class="spinner hidden"></span> Yes, Decline Appointment</button>
                                <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">No, Keep Proposed Time</button>
                            </div>
                        </div>
                    </div>';                                      
                    
                }

                echo '</div>';
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
    $("#" + modalId).find(".modal-start-time").text(startTime);
    $("#" + modalId).find(".confirm-proposed-date, .decline-proposed-date")
        .attr("data-order-id", orderId)
        .attr("data-start", startTime);
});



            $(".close-modal, .close-modal-text").click(function () {
                $(".status-modal").css("display", "none");
            });

            $(window).click(function (event) {
                $(".status-modal").each(function () {
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
                        if (response.success) {
                            location.reload();
                        } else {
                            console.error("Error:", response.data.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                    }
                });
            });

            $(".decline-proposed-date").click(function () {
                var button = $(this);
                var orderId = button.data("order-id");

                showSpinner(button);

                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: "POST",
                    data: {
                        action: "decline_new_proposed_time",
                        security: "<?php echo wp_create_nonce('ajax_nonce'); ?>",
                        order_id: orderId
                    },
                    success: function (response) {
                        hideSpinner(button);
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }
}
