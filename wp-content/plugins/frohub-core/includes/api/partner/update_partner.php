<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdatePartner {

    public static function init() {
        $self = new self();
        add_action('rest_api_init', array($self, 'register_rest_routes'));
    }

    /**
     * Registers the REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('frohub/v1', '/update-partner', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'update_partner'),
            'permission_callback' => '__return_true', // Consider authentication checks
        ));
    }

    /**
     * Handles updating partner details.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_partner(\WP_REST_Request $request) {
        $data = $request->get_json_params();

        if (empty($data['partnerPostId'])) {
            return new \WP_REST_Response(['message' => 'Missing partnerPostId'], 400);
        }

        $partnerPostId = intval($data['partnerPostId']);
        $post = get_post($partnerPostId);

        if (!$post || $post->post_type !== 'partner') {
            return new \WP_REST_Response(['message' => 'Invalid partnerPostId'], 404);
        }

        // Update post title and content (Bio)
        if (!empty($data['title'])) {
            wp_update_post([
                'ID'           => $partnerPostId,
                'post_title'   => sanitize_text_field($data['title']),
                'post_content' => sanitize_textarea_field($data['bio'])
            ]);
        }

        // Update ACF fields
        $acf_fields = [
            'booking_notice'       => intval($data['bookingNotice']),
            'booking_scope'        => intval($data['bookingScope']),
            'bufferPeriodMin'      => intval($data['bufferPeriodMin']),
            'bufferPeriodHour'     => intval($data['bufferPeriodHour']),
            'service_types'        => $data['serviceTypes'],
            'street_address'       => sanitize_text_field($data['addressLine1']),
            'city'                 => sanitize_text_field($data['city']),
            'county_district'      => sanitize_text_field($data['county']),
            'postcode'             => sanitize_text_field($data['postcode']),
            'terms_and_conditions' => sanitize_textarea_field($data['terms']),
            'late_fees'            => sanitize_textarea_field($data['lateFees']),
            'payments'             => sanitize_textarea_field($data['payments']),
            'frohub_refund_policy' => sanitize_textarea_field($data['frohubRefundPolicy']),
            'auto_message'       => intval($data['auto_message']),
            'auto_message_text' => sanitize_textarea_field($data['auto_message_text']),
        ];

        foreach ($acf_fields as $key => $value) {
            update_field($key, $value, $partnerPostId);
        }

        // Update buffer period
        if (!empty($data['bufferPeriodMin'])) {
            update_field('buffer_period_minutes', intval($data['bufferPeriodMin']), $partnerPostId);
        }

        if (!empty($data['bufferPeriodHour'])) {
           update_field('buffer_period_hours', intval($data['bufferPeriodHour']), $partnerPostId);
        }

        // ➕ Update Latitude & Longitude
        update_geolocation(
            $partnerPostId,
            sanitize_text_field($data['addressLine1']),
            sanitize_text_field($data['postcode']),
            sanitize_text_field($data['city']),
            sanitize_text_field($data['county'])
        );

        // Update availability (Repeater field)
        if (!empty($data['availability'])) {
            $availability_data = array_map(function ($slot) {
                return [
                    'day'          => sanitize_text_field($slot['day']),
                    'from'         => sanitize_text_field($slot['from']),
                    'to'           => sanitize_text_field($slot['to']),
                    'extra_charge' => sanitize_text_field($slot['extra_charge'])
                ];
            }, $data['availability']);
            update_field('availability', $availability_data, $partnerPostId);
        }


        // Handle image uploads
        if (!empty($data['profileImage'])) {
            $profile_image_id = $this->upload_image($data['profileImage'], $partnerPostId);
            if ($profile_image_id) {
                set_post_thumbnail($partnerPostId, $profile_image_id);
            }
        }

        if (!empty($data['bannerImage'])) {
            $banner_image_id = $this->upload_image($data['bannerImage'], $partnerPostId);
            if ($banner_image_id) {
                update_field('hero_image', $banner_image_id, $partnerPostId);
            }
        }

        return new \WP_REST_Response([
            'message'        => 'Partner details updated successfully',
            'partnerPostId'  => $partnerPostId
        ], 200);
    }


    /**
     * Handles image uploads and attaches to a post.
     *
     * @param string $image_url
     * @param int    $post_id
     * @return int|false Attachment ID on success, false on failure.
     */
    private function upload_image($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }

        $file_array = [
            'name'     => basename($image_url),
            'tmp_name' => $tmp
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return false;
        }

        return $attachment_id;
    }
}

