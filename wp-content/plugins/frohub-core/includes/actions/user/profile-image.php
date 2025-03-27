<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProfileImage {

    public static function init() {
        $self = new self();

        // Override avatar system
        add_filter('get_avatar', array($self, 'override_avatar'), 10, 5);
    }

    /**
     * Replaces Gravatar with ACF user_image or a default avatar
     */
    public function override_avatar($avatar, $id_or_email, $size, $default, $alt) {
        $user = false;

        // Get user from ID, email, or comment object
        if (is_numeric($id_or_email)) {
            $user = get_user_by('id', $id_or_email);
        } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            $user = get_user_by('id', $id_or_email->user_id);
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
        }

        // Default fallback avatar
        $default_avatar_url = get_template_directory_uri() . '/assets/images/default-avatar.jpg';

        // Use ACF user_image if available
        if ($user) {
            $acf_avatar_url = get_field('profile_picture', 'user_' . $user->ID);

            if (!empty($acf_avatar_url)) {
                $avatar_url = esc_url($acf_avatar_url);
            } else {
                $avatar_url = $default_avatar_url;
            }
        } else {
            $avatar_url = $default_avatar_url;
        }

        // Return final <img> tag
        return "<img alt='" . esc_attr($alt) . "' src='" . $avatar_url . "' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
    }
}
