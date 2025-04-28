<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class CancelOrderButton
{
    public static function init()
    {
        $self = new self();
        add_shortcode('cancel_order_button', [$self, 'cancel_order_button_shortcode']);
    }

    public function cancel_order_button_shortcode()
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $order_id = isset($GLOBALS['single_order_id']) ? $GLOBALS['single_order_id'] : null;

        if (!$order_id) {
            return '';
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return '';
        }

        $order_status = $order->get_status();

        // Only allow cancel if on-hold or processing
        if (!in_array($order_status, ['on-hold', 'processing'])) {
            return '';
        }

        // Determine cancel type
        $cancel_type = 'normal';
        if ($order_status === 'processing') {
            $start_date = $this->get_order_start_date($order);
            if ($start_date) {
                $days_difference = floor((strtotime($start_date) - time()) / (60 * 60 * 24));
                $cancel_type = $days_difference > 7 ? 'early' : 'late';
            }
        }

        ob_start();
        $this->render_cancel_button($order_id, $cancel_type);
        $this->render_modals($order_id);
        $this->render_scripts($order_id);
        return ob_get_clean();
    }

    private function get_order_start_date($order)
    {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if ($product_id == 28990) {
                continue;
            }
            $start_date = $item->get_meta('Start Date Time');
            if (!empty($start_date)) {
                return $start_date;
            }
        }
        return null;
    }

    private function render_cancel_button($order_id, $cancel_type)
    {
        $modal_id = $cancel_type . 'CancelModal_' . $order_id;
        ?>
        <div class="cancel_order_button" data-order-id="<?php echo esc_attr($order_id); ?>">
            <button class="modal-trigger w-btn us-btn-style_6 w-btn-underlined"
                data-modal="<?php echo esc_attr($modal_id); ?>">
                Cancel Order
            </button>
        </div>
        <?php
    }

    private function render_modals($order_id)
    {
        // Normal Cancel Modal
        ?>
        <div id="normalCancelModal_<?php echo esc_attr($order_id); ?>" class="status-modal">
            <div class="modal-content">
                <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                <div class="modal-body"><p>Are you sure you want to cancel this booking request?</p></div>
                <div class="modal-footer">
                    <button class="w-btn us-btn-style_6 w-btn-underlined confirm-normal-cancel-order"
                        data-order-id="<?php echo esc_attr($order_id); ?>">Yes, Cancel Booking</button>
                    <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                </div>
            </div>
        </div>

        <!-- Early Cancel Modal -->
        <div id="earlyCancelModal_<?php echo esc_attr($order_id); ?>" class="status-modal">
            <div class="modal-content">
                <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                <div class="modal-body"><p>You're within the early cancellation window. Your deposit will be refunded, but booking fee is non-refundable.</p></div>
                <div class="modal-footer">
                    <button class="w-btn us-btn-style_6 w-btn-underlined confirm-early-cancel-order"
                        data-order-id="<?php echo esc_attr($order_id); ?>">Yes, Cancel Booking</button>
                    <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                </div>
            </div>
        </div>

        <!-- Late Cancel Modal -->
        <div id="lateCancelModal_<?php echo esc_attr($order_id); ?>" class="status-modal">
            <div class="modal-content">
                <div class="modal-header"><h5>Cancel Booking?</h5><span class="close-modal">×</span></div>
                <div class="modal-body">
                    <i class="fas fa-exclamation-triangle" style="font-size:2rem; margin-bottom:1rem;"></i>
                    <p>This is a late cancellation. Your deposit and booking fee won’t be refunded as per our cancellation policy.</p>
                    <p>Are you sure you want to cancel?</p>
                </div>
                <div class="modal-footer">
                    <button class="w-btn us-btn-style_6 w-btn-underlined confirm-late-cancel-order"
                        data-order-id="<?php echo esc_attr($order_id); ?>">Yes, Cancel Anyway</button>
                    <button class="w-btn us-btn-style_6 w-btn-underlined close-modal-text">Keep My Booking</button>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_scripts($order_id)
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $(".modal-trigger").click(function () {
                    $("#" + $(this).data("modal")).fadeIn();
                });

                $(".close-modal, .close-modal-text").click(function () {
                    $(".status-modal").fadeOut();
                });

                $(window).click(function (event) {
                    $(".status-modal").each(function () {
                        if (event.target === this) $(this).fadeOut();
                    });
                });

                $(".confirm-normal-cancel-order").click(function () {
                    var orderId = $(this).data("order-id");
                    $("#normalCancelModal_" + orderId).hide();
                    $("#cancelReasonModal_" + orderId).fadeIn();
                });

                $(".confirm-early-cancel-order").click(function () {
                    var orderId = $(this).data("order-id");
                    $("#earlyCancelModal_" + orderId).hide();
                    $("#cancelReasonModal_" + orderId).fadeIn();
                });

                $(".confirm-late-cancel-order").click(function () {
                    var orderId = $(this).data("order-id");
                    $("#lateCancelModal_" + orderId).hide();
                    $("#cancelReasonModal_" + orderId).fadeIn();
                });
            });
        </script>
        <?php
    }
}
