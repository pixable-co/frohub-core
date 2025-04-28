<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewButton {

    public static function init() {
        $self = new self();
        add_shortcode( 'review_button', array($self, 'review_button_shortcode') );
    }

    public function review_button_shortcode() {
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

            // Render the "Leave Review" button
            echo '<button id="leaveReviewButton" class="w-btn us-btn-style_3">Leave Review</button>';

            // Hidden review form
            ?>
            <div id="reviewForm" style="display:none; margin-top: 20px;">
                <div class="review-form-content">
                    <div id="productImg"><?php if ($product_id) { echo '<img src="' . esc_url(get_the_post_thumbnail_url($product_id, 'thumbnail')) . '" alt="">'; } ?></div>
                    <p><strong>Service:</strong> <?php echo $service_name ?? ''; ?></p>
                    <p><strong>Type:</strong> <?php echo $service_type ?? ''; ?></p>
                    <p><strong>Stylist:</strong> <?php echo $partner_title ?? ''; ?></p>
                    <p><strong>Date:</strong> <?php echo $appointment ?? ''; ?></p>
                    <p><strong>Location:</strong> <?php echo $partner_address ?? ''; ?></p>

                    <div class="feedback-form" style="margin-top: 20px;">
                        <p id="feedbackHeading">Let’s See How You Slay (Share Your Photo)</p>
                        <p id="feedbackDesc">Feel free to share a photo of your fabulous look! If you're a bit shy, you can always upload a side shot or one that keeps your face covered. Sharing photos helps other clients see the stylist’s work and decide if they’d like to book the service.</p>

                        <?php echo do_shortcode('[gravityform id="7" title="false" description="false" ajax="true"]'); ?>
                    </div>
                </div>
            </div>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#leaveReviewButton').on('click', function() {
                    $('#reviewForm').slideDown(); // Smooth reveal
                    $(this).hide(); // Hide the button after click
                });

                $(document).on('gform_post_render', function(event, formId) {
                    if (formId === 7) {
                        $('#input_7_18').val('<?php echo esc_js($order_id); ?>').prop('readonly', true);
                        $('#input_7_19').val('<?php echo esc_js($product_id); ?>').prop('readonly', true);
                    }
                });
            });
            </script>
            <?php
        } else {
            echo '<span>Thank you for your review.</span>';
        }

        return ob_get_clean();
    }
}
