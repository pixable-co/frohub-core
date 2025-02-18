<?php
namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class CreateProduct
{
    public static function init()
    {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes()
    {
        register_rest_route('frohub/v1', '/create-product', [
            'methods'  => 'POST',
            'callback' => [$this, 'create_custom_woocommerce_product'],
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce'); // Restrict to WooCommerce managers
            },
        ]);
    }

    /**
     * Handles WooCommerce product creation.
     */
    public function create_custom_woocommerce_product(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        // Extracting values from JSON payload
        $partnerId = isset($params["28"]) ? sanitize_text_field($params["28"]) : '';
        $partnerName = get_the_title($partnerId);
        $serviceName = isset($params["1"]) ? sanitize_text_field($params["1"]) : '';
        $size = isset($params["2"]) ? sanitize_text_field($params["2"]) : '';
        $length = isset($params["3"]) ? sanitize_text_field($params["3"]) : '';
        $categories = isset($params["9"]) ? json_decode($params["9"], true) : [];
        $tags = isset($params["10"]) ? json_decode($params["10"], true) : [];
        $overrideAvailability = isset($params["12"]) ? filter_var($params["12"], FILTER_VALIDATE_BOOLEAN) : false;
        $notice = isset($params["13"]) ? sanitize_text_field($params["13"]) : '';
        $farFuture = isset($params["14"]) ? sanitize_text_field($params["14"]) : '';
        $numOfClients = isset($params["15"]) ? sanitize_text_field($params["15"]) : '';
        $price = isset($params["17"]) ? floatval($params["17"]) : 0;
        $description = isset($params["19"]) ? sanitize_text_field($params["19"]) : '';
        $faq = isset($params["20"]) ? json_decode($params["20"], true) : [];
        $images = isset($params["21"]) ? json_decode($params["21"], true) : [];
        $isPrivateService = isset($params["22"]) ? filter_var($params["22"], FILTER_VALIDATE_BOOLEAN) : false;
        $bookingDuration = isset($params["27"]) ? sanitize_text_field($params["27"]) : '';
        $addOns = isset($params["18"]) ? json_decode($params["18"], true) : [];

        if (!is_array($addOns)) {
            $addOns = [];
        }

        // Split duration into hours and minutes
        $durationParts = explode(':', $bookingDuration);
        $hours = isset($durationParts[0]) ? intval($durationParts[0]) : 0;
        $minutes = isset($durationParts[1]) ? intval($durationParts[1]) : 0;

        // Extract service types dynamically
        $serviceTypes = [];
        foreach (["11.1", "11.2", "11.3"] as $key) {
            if (!empty($params[$key])) {
                $serviceTypes[] = sanitize_text_field($params[$key]);
            }
        }

        // Create WooCommerce product
        $product = new \WC_Product_Simple();
        $product->set_name($serviceName);
        $product->set_regular_price($price);
        $product->set_description($description);
        $product->set_stock_quantity($numOfClients);
        $product->set_manage_stock(true);
        $product->set_catalog_visibility('visible');

        $product_id = $product->save();

        if (!$product_id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Product creation failed.'], 500);
        }

        // Assign categories and tags
        if (!empty($categories)) {
            $product->set_category_ids($categories);
        }
        if (!empty($tags)) {
            $product->set_tag_ids($tags);
        }

        // Set product images
        if (!empty($images)) {
            $product->set_image_id($this->attach_image_from_url($images[0]));
            $galleryImageIds = array_map([$this, 'attach_image_from_url'], array_slice($images, 1));
            $product->set_gallery_image_ids($galleryImageIds);
        }

        // Assign multiple attributes
        $product_attributes = [];
        $attribute_slugs = [];

        if (!empty($addOns)) {
            foreach ($addOns as $attribute_id) {
                $attribute_id = intval($attribute_id);
                $attribute = get_term($attribute_id, 'pa_add-on');

                if ($attribute && !is_wp_error($attribute)) {
                    $taxonomy = 'pa_add-on';
                    $attribute_slug = $attribute->slug;
                    $attribute_name = wc_attribute_label($taxonomy);

                    $attribute_slugs[] = $attribute_slug;

                    if (isset($product_attributes[$taxonomy])) {
                        $product_attributes[$taxonomy]['value'][] = $attribute_slug;
                    } else {
                        $product_attributes[$taxonomy] = [
                            'name'         => $attribute_name,
                            'value'        => [$attribute_slug],
                            'is_visible'   => 1,
                            'is_variation' => 0,
                            'is_taxonomy'  => 1,
                        ];
                    }
                }
            }
        }

        if (!empty($product_attributes)) {
            $product->set_attributes($product_attributes);
        }

        // Save product again after assigning attributes
        $product->save();

        // Assign attributes using wp_set_object_terms
        if (!empty($attribute_slugs)) {
            wp_set_object_terms($product_id, $attribute_slugs, 'pa_add-on', false);
        }

        // Save attributes in post meta
        update_post_meta($product_id, '_product_attributes', $product_attributes);

        // Update FAQs for ACF Repeater
        $faq_data = [];
        if (!empty($faq) && is_array($faq)) {
            foreach ($faq as $faq_id) {
                $faq_data[] = ['faq_post' => intval($faq_id)];
            }
        }
        update_field('field_67978b43caeb0', $faq_data, $product_id);

        // **Populate ACF Fields**
        update_field('field_67853b658c2dd', $partnerId, $product_id);
        update_field('field_678928cad8da7', $partnerName, $product_id);
        update_field('field_6777c8532f7e8', $size, $product_id);
        update_field('field_6777c8692f7e9', $length, $product_id);
        update_field('field_6777c89c2f7ec', $serviceTypes, $product_id);
        update_field('field_67a4a2b3807e7', $overrideAvailability, $product_id);
        update_field('field_6777c8d02f7ee', $availabilityData, $product_id);
        update_field('field_6777c94b7ea36', $notice, $product_id);
        update_field('field_6777c95b7ea37', $farFuture, $product_id);
        update_field('field_6777c9737ea38', $numOfClients, $product_id);
        update_field('field_6777c7762f7e6', $hours, $product_id);
        update_field('field_6777c8252f7e7', $minutes, $product_id);
        update_field('field_6777ca0db882e', $isPrivateService ? "Yes" : "No", $product_id);

        return new \WP_REST_Response([
            'success'    => true,
            'product_id' => $product_id,
            'message'    => 'Product created successfully with ACF fields populated.',
        ], 200);
    }
}
