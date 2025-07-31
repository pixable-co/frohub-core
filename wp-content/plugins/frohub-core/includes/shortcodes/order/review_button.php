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

        // ✅ Allow review when completed OR cancelled by stylist (ACF)
        $order_status = $order->get_status();
        $cancellation_status = get_field('cancellation_status', $order_id);

        $allowed_wc_statuses = ['completed'];
        $allowed_cancellation_statuses = ['cancelled-by-stylist'];

        if (!in_array($order_status, $allowed_wc_statuses) && !in_array($cancellation_status, $allowed_cancellation_statuses)) {
            return ob_get_clean();
        }

        $review = get_field('review', $order_id);

        if (empty($review)) {
            // Collect product/order meta
            $product_id = null;
            $service_name = $appointment = $service_type = '';
            $partner_title = $partner_link = $partner_address = '';

            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() !== 28990) {
                    $product_id   = $item->get_product_id();
                    $service_name = esc_html($item->get_name());

                    foreach ($item->get_meta_data() as $meta) {
                        switch ($meta->key) {
                            case 'Start Date Time':
                                $appointment = esc_html($meta->value);
                                break;
                            case 'pa_service-type':
                                $service_type = esc_html($meta->value);
                                break;
                        }
                    }

                    $partner_id = get_field('partner_id', $product_id);
                    if ($partner_id) {
                        $partner_title   = get_the_title($partner_id);
                        $partner_link    = get_permalink($partner_id);
                        $partner_address = get_field('partner_address', $partner_id);
                    }
                }
            }

            // Unique wrapper to scope CSS/JS to this instance
            $uid = 'rb_' . wp_generate_uuid4();
            ?>

            <div id="<?php echo esc_attr($uid); ?>" class="rb-wrap">
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
                                <?php if ($service_name): ?>
                                    <p id="productName"><?php echo esc_html($service_name); ?></p>
                                <?php endif; ?>

                                <?php if ($service_type): ?>
                                    <p id="serviceType"><span class="status_text"><?php echo esc_html($service_type); ?></span></p>
                                <?php endif; ?>

                                <?php if ($partner_title): ?>
                                    <p id="partnerTitle"><?php echo esc_html($partner_title); ?></p>
                                <?php endif; ?>

                                <?php if ($appointment): ?>
                                    <p id="bookingDate"><i class="fas fa-calendar-alt"></i>
                                        <span id="selectedDate"><?php echo esc_html($appointment); ?></span></p>
                                <?php endif; ?>

                                <?php if ($partner_address): ?>
                                    <p id="bookingAddress"><i class="fas fa-map-marker-alt"></i>
                                        <span id="partnerAddress"><?php echo esc_html($partner_address); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="feedback-form" style="margin-top: 20px;">
                            <?php echo do_shortcode('[gravityform id="7" title="false" description="false" ajax="true"]'); ?>
                        </div>
                    </div>
                </div>

                <style>
                    /* Utility used by the JS strategy */
                    #<?php echo esc_html($uid); ?> .product-details.is-hidden {
                        display: none !important;
                    }
                </style>

                <script>
                jQuery(function ($) {
                    // We don't use modal open here; we just scope to this instance
                    var $root = $('#<?php echo esc_js($uid); ?>');

                    // === Strategy: toggle helper + ensureObserver + GF hooks ===

                    function toggleProductDetailsVisibility() {
                        var hasConfirmation = $('#gform_confirmation_wrapper_7').length > 0;
                        $root.find('.product-details').toggleClass('is-hidden', hasConfirmation);
                    }

                    function ensureObserver() {
                        var feedback = $root.find('.feedback-form').get(0);
                        if (feedback && !feedback._observerAttached) {
                            var mo = new MutationObserver(toggleProductDetailsVisibility);
                            mo.observe(feedback, { childList: true, subtree: true });
                            feedback._observerAttached = true; // mark so we don't attach again
                        }
                    }

                    // Gravity Forms: after the form is (re)rendered via AJAX
                    $(document).off('gform_post_render._review7_rb').on('gform_post_render._review7_rb', function (event, formId) {
                        if (formId === 7) {
                            // Prefill hidden/readonly values
                            $('#input_7_18').val('<?php echo esc_js($order_id); ?>').prop('readonly', true);
                            $('#input_7_19').val('<?php echo esc_js($product_id); ?>').prop('readonly', true);
                        }
                        // Re-evaluate visibility in case the render replaced markup
                        toggleProductDetailsVisibility();
                        ensureObserver();
                    });

                    // Gravity Forms: when the confirmation is injected via AJAX
                    $(document).off('gform_confirmation_loaded._review7_rb').on('gform_confirmation_loaded._review7_rb', function (event, formId) {
                        if (formId === 7) {
                            toggleProductDetailsVisibility();
                        }
                    });

                    // Initial checks on page load
                    toggleProductDetailsVisibility();
                    ensureObserver();
                });
                </script>
            </div>

            <?php
            echo do_shortcode('[us_separator]');

        } else {
            echo '<span>Thank you for leaving a review – your feedback really matters.</span>';
            echo do_shortcode('[us_separator size="small"]');
        }

        return ob_get_clean();
    }
}
