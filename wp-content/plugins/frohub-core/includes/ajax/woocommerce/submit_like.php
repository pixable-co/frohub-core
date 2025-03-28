<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubmitLike {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_submit_like', array($self, 'submit_like'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_submit_like', array($self, 'submit_like'));
    }

    /**
     * Handles liking a comment via AJAX.
     */
    public function submit_like() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error('User not logged in.');
        }

        if ( ! isset($_POST['commentID']) || ! isset($_POST['new_likes']) ) {
            wp_send_json_error('Missing parameters');
        }

        $commentID = intval($_POST['commentID']);
        $new_likes = intval($_POST['new_likes']);

        // Update the total likes on the comment
        update_field('comment_total_likes', $new_likes, $commentID);

        // Verify updated value
        $updated_likes = get_field('comment_total_likes', $commentID);

        $user_id = get_current_user_id();
        $liked_comments = get_field('liked_comments', 'user_' . $user_id);

        // Ensure it's an array
        if ( empty($liked_comments) || ! is_array($liked_comments) ) {
            $liked_comments = array();
        }

        // Check if comment is already liked to avoid duplicates
        $already_liked = false;
        foreach ( $liked_comments as $liked_comment ) {
            if ( isset($liked_comment['comment_id']) && $liked_comment['comment_id'] == $commentID ) {
                $already_liked = true;
                break;
            }
        }

        if ( ! $already_liked ) {
            $liked_comments[] = array( 'comment_id' => $commentID );
            update_field('liked_comments', $liked_comments, 'user_' . $user_id);
        }

        wp_send_json_success(array(
            'commentID'      => $commentID,
            'new_likes'      => $new_likes,
            'updated_likes'  => $updated_likes
        ));
    }
}
