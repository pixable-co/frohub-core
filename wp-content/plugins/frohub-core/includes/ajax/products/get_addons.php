<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetAddons {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/get_addons', array($self, 'get_addons'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_frohub/get_addons', array($self, 'get_addons'));
    }

    public function get_addons() {
        check_ajax_referer('frohub_nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $selected_attributes = isset($_POST['selected_attributes']) ? $_POST['selected_attributes'] : [];

        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID.']);
        }

        $product = wc_get_product($product_id);
        $partner = null;
        $matched_add_ons = [];
        $product_price = 0;
        $deposit_amount = 0;

        if ($product) {
            $product_price = get_field('display_price', $product_id);
            $partner_id = get_field('partner_id', $product_id);
            $deposit_amount = $product->get_regular_price();

            if ($partner_id) {
                $partner_post = get_post($partner_id);
                if ($partner_post) {
                    $partner = [
                        'title'   => $partner_post->post_title,
                        'content' => $partner_post->post_content,
                    ];

                    $add_ons = get_field('add_ons', $partner_id);
                    $attributes = $product->get_attributes();

                    if (!empty($attributes) && !empty($add_ons)) {
                        foreach ($attributes as $attribute) {
                            if ($attribute->get_name() === 'pa_add-on') { // ✅ Check only 'add-on' attribute
                                $terms = wc_get_product_terms($product_id, $attribute->get_name(), ['fields' => 'all']);

                                foreach ($terms as $term) {
                                    // ✅ Only add the add-on if it matches selected attributes
                                    if (!empty($selected_attributes) && !in_array($term->term_id, $selected_attributes)) {
                                        continue;
                                    }

                                    foreach ($add_ons as $add_on) {
                                        if ($term->term_id == $add_on['add_on']->term_id) {
                                            $matched_add_ons[] = [
                                                'id' => $term->term_id,
                                                'name' => $term->name,
                                                'price' => $add_on['price'],
                                                'duration_minutes' => $add_on['duration_minutes']
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Send JSON response
        wp_send_json_success([
            'message'        => 'get_addons AJAX handler executed successfully.',
            'product_id'     => $product_id,
            'partner'        => $partner,
            'add_ons'        => $matched_add_ons,
            'product_price'  => $product_price,
            'deposit_amount' => $deposit_amount,
        ]);
    }
}