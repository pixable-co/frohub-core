<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class ReviewButton
{

    public static function init()
    {
        $self = new self();
        add_shortcode('review_button', array($self, 'review_button_shortcode'));
    }

    public function review_button_shortcode()
    {
        ob_start();
    
        $order_id = isset($GLOBALS['single_order_id']) ? $GLOBALS['single_order_id'] : null;
    
        if (!$order_id) {
            echo '<span>Order ID is missing.</span>';
            return ob_get_clean();
        }
    
        $order = wc_get_order($order_id);
    
        if (!$order) {
            echo '<span>Invalid order.</span>';
            return ob_get_clean();
        }
    
        // ✅ Check order status and ACF cancellation status
        $order_status = $order->get_status(); // WooCommerce status
        $cancellation_status = get_field('cancellation_status', $order_id); // ACF field
    
        $allowed_wc_statuses = ['completed'];
        $allowed_cancellation_statuses = [ 'cancelled-by-stylist'];
    
        if (!in_array($order_status, $allowed_wc_statuses) && !in_array($cancellation_status, $allowed_cancellation_statuses)) {
            // If neither matches, do not show the review form
            //echo '<span>Reviews are available only after your appointment is completed or cancelled.</span>';
            return ob_get_clean();
        }
    
        $review = get_field('review', $order_id);
    
        if (empty($review)) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() !== 28990) {
                    $product_id = $item->get_product_id();
                    $service_name = esc_html($item->get_name());
    
                    $item_meta_data = $item->get_meta_data();
                    if (!empty($item_meta_data)) {
                        foreach ($item_meta_data as $meta) {
                            switch ($meta->key) {
                                case 'Start Date Time':
                                    $appointment = esc_html($meta->value);
                                    break;
                                case 'pa_service-type':
                                    $service_type = esc_html($meta->value);
                                    break;
                            }
                        }
                    }
    
                    $partner_id = get_field('partner_id', $product_id);
                    if ($partner_id) {
                        $partner_title = get_the_title($partner_id);
                        $partner_link = get_permalink($partner_id);
                        $partner_address = get_field('partner_address', $partner_id);
                    }
                }
            }
            ?>
    
            <!-- FIXED HTML STRUCTURE -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Leave a Review</h5>
                </div>
    
                <div class="modal-body">
                    <div class="product-details">
                        <?php if ($product_id): ?>
                            <img class="review-product-img" src="<?php echo esc_url(get_the_post_thumbnail_url($product_id, 'thumbnail')); ?>" alt="">
                        <?php endif; ?>
    
                        <div class="modal-body-right">
                            <?php if (!empty($service_name)): ?>
                                <p id="productName"><?php echo $service_name; ?></p>
                            <?php endif; ?>
    
                            <?php if (!empty($service_type)): ?>
                                <p id="serviceType"><span class="status_text"><?php echo $service_type; ?></span></p>
                            <?php endif; ?>
    
                            <?php if (!empty($partner_title)): ?>
                                <p id="partnerTitle"><?php echo $partner_title; ?></p>
                            <?php endif; ?>
    
                            <?php if (!empty($appointment)): ?>
                                <p id="bookingDate"><i class="fas fa-calendar-alt"></i> <span
                                        id="selectedDate"><?php echo $appointment; ?></span></p>
                            <?php endif; ?>
    
                            <?php if (!empty($partner_address)): ?>
                                <p id="bookingAddress"><i class="fas fa-map-marker-alt"></i> <span
                                        id="partnerAddress"><?php echo $partner_address; ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
    
                    <!-- Feedback form inside modal-body -->
                    <div class="feedback-form" style="margin-top: 20px;">
               
    
                        <?php echo do_shortcode('[gravityform id="7" title="false" description="false" ajax="true"]'); ?>
                    </div>
    
                </div> <!-- End of modal-body -->
            </div> <!-- End of modal-content -->
    

            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    $(document).on('gform_post_render', function (event, formId) {
                        if (formId === 7) {
                            $('#input_7_18').val('<?php echo esc_js($order_id); ?>').prop('readonly', true);
                            $('#input_7_19').val('<?php echo esc_js($product_id); ?>').prop('readonly', true);
                        }
                    });
                });
            </script>
    
            <?php
             echo do_shortcode('[us_separator]');
        } else {
            echo '<span>Thank you for leaving a review – your feedback really matters.</span>';
             echo do_shortcode('[us_separator size="small"]');
        }
    
        return ob_get_clean();
    }
    
}
