<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GetUserAvatar {

    public static function init() {
        $self = new self();
        add_shortcode('get_user_avatar', array($self, 'get_user_avatar_shortcode'));
    }

    public function get_user_avatar_shortcode() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return ''; // Return nothing if user not logged in
        }

        return get_avatar($user_id, 105);
    }
}
