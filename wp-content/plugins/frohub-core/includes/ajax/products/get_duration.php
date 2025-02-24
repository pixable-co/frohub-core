<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetDuration {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/get_duration', array($self, 'get_duration'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_frohub/get_duration', array($self, 'get_duration'));
    }

    public function get_duration() {
        check_ajax_referer('frohub_nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID.']);
        }

        // Get duration values from ACF fields
        $duration_hours = get_field('duration_hours', $product_id);
        $duration_minutes = get_field('duration_minutes', $product_id);

        // Ensure values are numeric, default to 0 if not set
        $duration_hours = is_numeric($duration_hours) ? intval($duration_hours) : 0;
        $duration_minutes = is_numeric($duration_minutes) ? intval($duration_minutes) : 0;

        // Return JSON response
        wp_send_json_success([
            'message' => 'Duration fetched successfully.',
            'duration_hours' => $duration_hours,
            'duration_minutes' => $duration_minutes,
        ]);
    }
}