<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetProduct {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/get-product/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handles the API request to get a product's full details.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        $product_id = intval($request->get_param('id'));
        $product = wc_get_product($product_id);

        if (!$product) {
            return new \WP_REST_Response([
                'message' => 'Product not found.',
                'product_id' => $product_id
            ], 404);
        }

        $response_data = [
            'product_id'         => $product_id,
            'service_name'       => $product->get_name(),
            'service_price'      => $product->get_price(),
            'service_description'=> $product->get_description(),
            'categories'         => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
            'tags'               => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']),
            'status'             => $product->get_status(),
            'featuredImage'      => get_the_post_thumbnail_url($product_id) ?: '',
        ];

        // Gallery images
        $gallery_image_ids = get_post_meta($product_id, '_product_image_gallery', true);
        $gallery_image_urls = [];

        if (!empty($gallery_image_ids)) {
            $image_ids = explode(',', $gallery_image_ids);
            foreach ($image_ids as $image_id) {
                $url = wp_get_attachment_url($image_id);
                if ($url) {
                    $gallery_image_urls[] = $url;
                }
            }
        }

        $response_data['galleryImages'] = $gallery_image_urls;

        // ACF Fields
        $acf_fields = [
            'partner_id',
            'partner_name',
            'booking_notice',
            'future_booking_scope',
            'availability',
            'duration_hours',
            'duration_minutes'
        ];

        foreach ($acf_fields as $field) {
            $response_data[$field] = get_field($field, $product_id);
        }

        // Attributes: size & length
        $size_terms = wp_get_post_terms($product_id, 'pa_size', ['fields' => 'ids']);
        $length_terms = wp_get_post_terms($product_id, 'pa_length', ['fields' => 'ids']);
        $response_data['size'] = !empty($size_terms) ? $size_terms[0] : null;
        $response_data['length'] = !empty($length_terms) ? $length_terms[0] : null;

        // Add-ons
        $add_on_terms = wp_get_post_terms($product_id, 'pa_add-on', ['fields' => 'ids']);
        $response_data['add_ons'] = !empty($add_on_terms) ? $add_on_terms : [];

        // FAQs
        $faqs = get_field('faqs', $product_id);
        $response_data['faqs'] = !empty($faqs) ? array_column($faqs, 'faq_post') : [];

        // Service Types from variations
        $enabled_service_types = [];
        $variations = wc_get_products([
            'status' => 'publish',
            'parent' => $product_id,
            'type'   => 'variation',
            'return' => 'objects'
        ]);

        foreach ($variations as $variation) {
            $service_type = get_post_meta($variation->get_id(), 'attribute_pa_service-type', true);
            if (!empty($service_type)) {
                $enabled_service_types[] = ucfirst(str_replace('-', ' ', $service_type));
            }
        }

        $response_data['service_types'] = array_unique($enabled_service_types);

        return new \WP_REST_Response($response_data, 200);
    }
}
