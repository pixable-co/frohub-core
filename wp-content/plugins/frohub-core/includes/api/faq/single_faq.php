<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SingleFaq {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/single-faq', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_faq_by_post_id'),
            'permission_callback' => '__return_true', // Adjust for security if needed
        ));
    }

    /**
     * Retrieves an FAQ post by ID.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_faq_by_post_id(\WP_REST_Request $request) {
        $post_id = $request->get_param('post_id');

        if (!$post_id) {
            return new \WP_Error('missing_post_id', 'Post ID is required', array('status' => 400));
        }

        $faq = get_post($post_id);

        // Validate if post exists and is of type 'faq'
        if (!$faq || get_post_type($post_id) !== 'faq') {
            return new \WP_Error('invalid_post', 'FAQ post not found', array('status' => 404));
        }

        // Get ACF partner_id
        $partner_id = get_field('partner_id', $post_id);

        // Prepare response
        $faq_data = array(
            'id'        => $faq->ID,
            'title'     => $faq->post_title,
            'content'   => apply_filters('the_content', $faq->post_content),
            'partner_id'=> $partner_id,
            'permalink' => get_permalink($faq->ID),
        );

        return rest_ensure_response($faq_data);
    }
}


