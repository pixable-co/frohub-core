<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Upsert {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/upsert', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('frohub/v1', '/faqs/upsert', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'upsert_faq'),
            'permission_callback' => '__return_true', // Adjust for security if needed
        ));
    }

    /**
     * Handles the /upsert API request.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request) {
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'upsert API endpoint reached',
        ), 200);
    }

    /**
     * Creates or updates an FAQ post.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function upsert_faq(\WP_REST_Request $request) {
        $params = $request->get_json_params();

        // Required fields
        $faq_id = isset($params['id']) ? intval($params['id']) : 0;
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $content = isset($params['content']) ? wp_kses_post($params['content']) : '';
        $partner_id = isset($params['partner_id']) ? intval($params['partner_id']) : 0;

        // Validate required fields
        if (empty($title) || empty($content) || empty($partner_id)) {
            return new \WP_Error('missing_fields', 'Title, content, and partner_id are required', array('status' => 400));
        }

        // Prepare post data
        $faq_data = array(
            'post_type'    => 'faq',  // Assuming 'faq' is the correct post type
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish'
        );

        // If an ID is provided, update the existing FAQ
        if ($faq_id > 0 && get_post_type($faq_id) === 'faq') {
            $faq_data['ID'] = $faq_id;
            $faq_id = wp_update_post($faq_data);
        } else {
            // Otherwise, create a new FAQ post
            $faq_id = wp_insert_post($faq_data);
        }

        if (is_wp_error($faq_id)) {
            return new WP_Error('post_error', 'Error saving FAQ', array('status' => 500));
        }

        // Update ACF field with partner_id
        update_field('partner_id', $partner_id, $faq_id);

        // Return response
        return rest_ensure_response(array(
            'message'   => $faq_id > 0 ? 'FAQ saved successfully' : 'Failed to save FAQ',
            'faq_id'    => $faq_id,
            'title'     => get_the_title($faq_id),
            'content'   => get_post_field('post_content', $faq_id),
            'partner_id'=> get_field('partner_id', $faq_id),
            'permalink' => get_permalink($faq_id)
        ));
    }
}


