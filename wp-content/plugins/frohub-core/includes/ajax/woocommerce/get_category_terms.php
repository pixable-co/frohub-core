<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetCategoryTerms {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_get_category_terms', array($self, 'get_category_terms'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_get_category_terms', array($self, 'get_category_terms'));
    }

    /**
     * Handles AJAX search for product categories.
     */
    public function get_category_terms() {

        if (!isset($_POST['term'])) {
            wp_send_json([]); // Empty response if term not provided
            wp_die();
        }

        $search_term = sanitize_text_field($_POST['term']);

        $args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'search'     => $search_term,
            'number'     => 10,
        );

        $terms = get_terms($args);

        // Optional: Debug log
        error_log("Found categories: " . print_r($terms, true));

        if (!empty($terms) && !is_wp_error($terms)) {
            $results = array_map(function($term) {
                return [
                    'label' => htmlspecialchars_decode($term->name),
                    'value' => htmlspecialchars_decode($term->name)
                ];
            }, $terms);

            wp_send_json($results);
        } else {
            wp_send_json([]);
        }

        wp_die();
    }
}
