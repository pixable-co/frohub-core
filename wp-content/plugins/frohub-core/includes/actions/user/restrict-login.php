<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestrictLogin {

    public static function init() {
        $self = new self();
        add_filter( 'authenticate', array( $self, 'check_user_activation' ), 30, 3 );
        add_action( 'user_register', array( $self, 'set_user_inactive' ) );
    }

    /**
     * Block login if user is not activated.
     */
    public function check_user_activation( $user, $username, $password ) {
        if ( is_wp_error( $user ) || ! $user ) {
            return $user;
        }

        $is_active = get_field( 'is_active', 'user_' . $user->ID );

        if ( ! $is_active ) {
            return new \WP_Error(
                'not_active',
                __( 'You need to activate your account first.', 'fecore' )
            );
        }

        return $user;
    }

    /**
     * Mark new users as inactive on registration.
     */
    public function set_user_inactive( $user_id ) {
        update_field( 'is_active', 0, 'user_' . $user_id );
    }
}
