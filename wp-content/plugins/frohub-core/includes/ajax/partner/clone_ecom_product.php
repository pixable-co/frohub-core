<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloneEcomProduct {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/clone_ecom_product', array($self, 'clone_ecom_product'));
    }

    public function clone_ecom_product() {
    	check_ajax_referer('frohub_nonce');

    	$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    	if (! $product_id) {
    		wp_send_json_error(['message' => 'Invalid product ID.']);
    	}

    	$product = wc_get_product($product_id);
    	if (! $product) {
    		wp_send_json_error(['message' => 'Product not found.']);
    	}

    	// ✅ 1. ACF Repeater: availability
    	$availability = [];
    	if (have_rows('availability', $product_id)) {
    		while (have_rows('availability', $product_id)) {
    			the_row();
    			$availability[] = [
    				'day'          => get_sub_field('day'),
    				'start'        => get_sub_field('from'),
    				'end'          => get_sub_field('to'),
    				'extra_charge' => get_sub_field('extra_charge') ?: '',
    			];
    		}
    	}

    	// ✅ 2. Extract service types from variation attribute terms
    	$service_types = [];
    	if ($product->is_type('variable')) {
    		$children = $product->get_children();
    		foreach ($children as $variation_id) {
    			$variation = wc_get_product($variation_id);

    			if ($variation && $variation->get_catalog_visibility() !== 'hidden') {
    				$attributes = $variation->get_attributes();

    				foreach ($attributes as $taxonomy => $term_slug) {
    					if (taxonomy_exists($taxonomy)) {
    						$term = get_term_by('slug', $term_slug, $taxonomy);
    						if ($term && !in_array($term->name, $service_types)) {
    							$service_types[] = $term->name;
    						}
    					}
    				}
    			}
    		}
    	}

    	// ✅ 3. Full dynamic payload
    	$payload = [
    		'service_name'         => $product->get_name(),
    		'service_description'  => $product->get_description(),
    		'service_price'        => get_post_meta($product_id, 'service_price', true) ?: '0',
    		'service_status'       => $product->get_status(),
    		'booking_notice'       => get_post_meta($product_id, 'booking_notice', true) ?: '2',
    		'future_booking_scope' => get_post_meta($product_id, 'future_booking_scope', true) ?: '30',
    		'duration_hours'       => get_post_meta($product_id, 'duration_hours', true) ?: 2,
    		'duration_minutes'     => get_post_meta($product_id, 'duration_minutes', true) ?: 0,
    		'availability'         => $availability,
    		'service_types'        => $service_types,
    		'size'                 => get_post_meta($product_id, 'size', true),
    		'length'               => get_post_meta($product_id, 'length', true),
			'override_availability' => get_post_meta($product_id, 'override_availability', true) ? 'yes' : 'no',
    		'is_private'           => get_post_meta($product_id, 'is_private', true) ?: 'no',
    		'categories'           => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']),
    		'tags'                 => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']),
    		'addons'               => get_post_meta($product_id, 'addons', true) ?: [],
    		'faqs'                 => get_post_meta($product_id, 'faqs', true) ?: [],
    		'partner_id'           => get_post_meta($product_id, 'partner_id', true),
    		'featured_image_url'   => get_the_post_thumbnail_url($product_id, 'full') ?: '',
    		'gallery_image_urls'   => $this->get_gallery_images($product_id),
    		'original_product_id'  => $product_id,
    	];

    	// ✅ 4. Send to external API
    	$response = wp_remote_post(FHCORE_PARTNER_BASE_API_URL . '/wp-json/fpserver/v1/clone-ecom-product', [
    		'headers' => [
    			'Content-Type' => 'application/json',
    		],
    		'body'    => wp_json_encode($payload),
    		'timeout' => 60,
    	]);

    	if (is_wp_error($response)) {
    		wp_send_json_error([
    			'message' => 'API call failed.',
    			'error'   => $response->get_error_message(),
    		]);
    	}

    	$body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['success']) && $body['success'] && !empty($body['post_id'])) {
            update_post_meta($product_id, 'portal_product_post_id', intval($body['post_id']));
        }

    	// ✅ 5. Return API’s actual response
    	wp_send_json_success($body);
    }

    private function get_gallery_images($product_id) {
    	$gallery = [];

    	$image_ids = get_post_meta($product_id, '_product_image_gallery', true);
    	if (! empty($image_ids)) {
    		$ids = explode(',', $image_ids);
    		foreach ($ids as $id) {
    			$url = wp_get_attachment_url($id);
    			if ($url) {
    				$gallery[] = $url;
    			}
    		}
    	}

    	return $gallery;
    }
}