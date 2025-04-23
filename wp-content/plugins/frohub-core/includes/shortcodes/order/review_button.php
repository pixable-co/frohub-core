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

            $data = json_encode([
                'productImgURL'   => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                'productName'     => $service_name,
                'serviceType'     => $service_type,
                'partnerTitle'    => $partner_title,
                'selectedDate'    => $appointment,
                'partnerAddress'  => $partner_address,
                'orderId'         => $order_id,
                'productId'       => $product_id,
            ]);

            echo '<button class="myBtn w-btn us-btn-style_3" data-info=\'' . esc_attr($data) . '\'>Leave Review</button>';
        } else {
            echo '<span>Thank you for your review</span>';
        }
        ?>

        <!-- Review Modal -->
        <div id="myModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="w-hwrapper valign_middle align_justify" style="width: 100%;">
                        <h5>Leave a Review</h5>
                        <span class="close"><i class="fas fa-times"></i></span>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="w-hwrapper valign_top align_left">
                        <div id="modalproductImg"></div>
                        <div class="modal-body-right">
                            <p id="productName"></p>
                            <p id="serviceType"></p>
                            <p id="partnerTitle"></p>
                            <p><i class="fas fa-calendar-alt"></i> <span id="selectedDate"></span></p>
                            <p><i class="fas fa-map-marker-alt"></i> <span id="partnerAddress"></span></p>
                            <div>
                                <?php echo do_shortcode('[gravityform id="7" title="false" description="false" ajax="true"]'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function ($) {
            window.reviewData = <?php echo json_encode([
                'productImgURL'   => get_the_post_thumbnail_url($product_id, 'thumbnail') ?: '',
                'productName'     => $service_name ?? '',
                'serviceType'     => $service_type ?? '',
                'partnerTitle'    => $partner_title ?? '',
                'selectedDate'    => $appointment ?? '',
                'partnerAddress'  => $partner_address ?? '',
                'orderId'         => $order_id ?? '',
                'productId'       => $product_id ?? '',
            ]); ?>;


            $('#selectedDate').text(window.reviewData.selectedDate);
            $('#partnerAddress').text(window.reviewData.partnerAddress);

            $(document).on('gform_post_render', function (event, formId) {
                if (formId === 7 && window.reviewData) {
                    $('#input_7_18').val(window.reviewData.orderId).prop('readonly', true);
                    $('#input_7_19').val(window.reviewData.productId).prop('readonly', true);
                }
            });


            $(document).on('click', '.myBtn', function () {
                const data = JSON.parse($(this).attr('data-info'));
                $('#modalproductImg').html('<img src="' + data.productImgURL + '">');
                $('#productName').text(data.productName);
                $('#serviceType').html('<span class="status_text">' + data.serviceType + '</span>');
                $('#partnerTitle').text(data.partnerTitle);
                $('#selectedDate').text(data.selectedDate);       // Optional (overwrites value)
                $('#partnerAddress').text(data.partnerAddress);   // Optional (overwrites value)
                $('#myModal').css('display', 'block');
            });

            $('.close').click(function () {
                $('#myModal').css('display', 'none');
            });

            $(window).click(function (event) {
                if (event.target === $('#myModal')[0]) {
                    $('#myModal').css('display', 'none');
                }
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }
}
