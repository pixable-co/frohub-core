<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubmitDislike {

    public static function init() {
        $self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_submit_dislike', array($self, 'submit_dislike'));
        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_submit_dislike', array($self, 'submit_dislike'));
    }

    /**
     * Handles the dislike (unlike) of a comment.
     */
    public function submit_dislike() {

        $commentID = isset($_POST['commentID']) ? intval($_POST['commentID']) : 0;
        $new_likes = isset($_POST['new_likes']) ? intval($_POST['new_likes']) : 0;

        if (!$commentID) {
            wp_send_json_error(['message' => 'Missing comment ID.']);
        }

        update_field('comment_total_likes', $new_likes, $commentID);

        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(['message' => 'User not authenticated.']);
        }

        $liked_comments = get_field('liked_comments', 'user_' . $user_id);

        if (!empty($liked_comments)) {
            foreach ($liked_comments as $key => $liked_comment) {
                if ($liked_comment['comment_id'] == $commentID) {
                    unset($liked_comments[$key]);
                    break;
                }
            }
            $liked_comments = array_values($liked_comments); // Reindex the array
        }

        update_field('liked_comments', $liked_comments, 'user_' . $user_id);

        wp_send_json_success([
            'commentID' => $commentID,
            'new_likes' => $new_likes,
        ]);
    }
}
