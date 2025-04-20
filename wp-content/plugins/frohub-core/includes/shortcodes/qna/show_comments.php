<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShowComments {

    
    public static function init() {
        $self = new self();
        add_shortcode('show_comments', array($self, 'show_comments_shortcode'));
        add_action('wp_footer', array($self, 'output_scripts'), 99);
        add_filter('pre_comment_approved', array($self, 'auto_approve_comments'), 10, 2);

    }

    public function auto_approve_comments($approved, $commentdata) {
        return 1; // Force approval
    }

    public function show_comments_shortcode() {
        ob_start();

        comment_form(array(
            'title_reply'         => 'Leave a Comment',
            'title_reply_to'      => 'Reply to %s',
            'cancel_reply_link'   => 'Cancel Reply',
            'label_submit'        => 'Comment',
            'comment_notes_after' => '',
            'logged_in_as'        => '',
        ));

        echo '<div class="w-separator size_large"></div>';

        $comments = get_comments(array(
            'post_id' => get_the_ID(),
            'status'  => 'approve',
        ));

        if (!empty($comments)) {
            echo '<h3>Answers from other users</h3>';
            echo '<ul class="w-comments-list">';
            $this->render_comments(get_the_ID(), 0, 1);
            echo '</ul>';
        }

        return ob_get_clean();
    }

    private function render_comments($post_id, $parent_id = 0, $depth = 1) {
        $comments = get_comments(array(
            'post_id' => $post_id,
            'status'  => 'approve',
            'parent'  => $parent_id,
            'order'   => 'DESC',
        ));

        foreach ($comments as $comment) {
            $commentId = $comment->comment_ID;
            $comment_author = esc_html(get_comment_author($commentId));
            $comment_date = esc_html(get_comment_date('', $commentId));
            $comment_content = wpautop(esc_html(get_comment_text($commentId)));
            $avatar = get_avatar($comment->comment_author_email, 50);
            $total_likes = get_field('comment_total_likes', $commentId);

            $user_id = get_current_user_id();
            $liked_comments = get_field('liked_comments', 'user_' . $user_id);
            $is_liked = false;

            if ($liked_comments) {
                foreach ($liked_comments as $liked_comment) {
                    if ($liked_comment['comment_id'] == $commentId) {
                        $is_liked = true;
                        break;
                    }
                }
            }

            echo '<li class="comment w-comments-item" id="comment-' . $commentId . '">';
            echo '<div class="w-comments-item-meta">';
            echo $avatar;
            echo '<div class="w-comments-item-author"><span>' . $comment_author . '</span></div>';
            echo '<a class="w-comments-item-date smooth-scroll" href="#comment-' . $commentId . '">' . $comment_date . '</a>';
            echo '</div>';
            echo '<div class="w-comments-item-text"><p>' . $comment_content . '</p></div>';

            echo '<div class="w-hwrapper valign_middle">';
            echo '<div class="like">';

            if ($is_liked) {
                echo '<button class="dislike-button w-btn us-btn-style_2" data-comment-id="' . $commentId . '" data-likes="' . $total_likes . '">';
                echo '<span class="w-btn-label dislike-btns">' . $total_likes . ' <i class="fas fa-thumbs-up"></i></span>';
                echo '</button>';
            } else {
                echo '<button class="like-button w-btn us-btn-style_2" data-comment-id="' . $commentId . '" data-likes="' . $total_likes . '">';
                echo '<span class="w-btn-label">' . $total_likes . ' <i class="fal fa-thumbs-up"></i></span>';
                echo '</button>';
            }

            echo '</div>';
            echo '<div><button class="toggle-reply-form w-btn us-btn-style_2" data-comment-id="' . $commentId . '">Reply</button></div>';
            echo '</div>';

            echo '<div class="comment-respond" id="respond-' . $commentId . '" style="display:none;">';
            echo '<form action="' . site_url('/wp-comments-post.php') . '" method="post" class="comment-form" novalidate="">';
            echo '<div class="comment-respond-form-container w-hwrapper valign_middle">';
            echo '<input type="text" name="comment" maxlength="65525" required placeholder="Add Comment">';
            echo '<input name="submit" type="submit" class="submit w-btn us-btn-style_2" value="Reply">';
            echo '<input type="hidden" name="comment_post_ID" value="' . $post_id . '">';
            echo '<input type="hidden" name="comment_parent" value="' . $commentId . '">';
            echo '</div>';
            echo '<input type="hidden" name="ak_js" value="' . esc_attr(md5(mt_rand())) . '">';
            echo '</form>';
            echo '</div>';

            echo '<ul class="children">';
            $this->render_comments($post_id, $commentId, $depth + 1);
            echo '</ul>';

            echo '</li>';
        }
    }

    public function output_scripts() {
        $is_user_logged_in = is_user_logged_in() ? 'true' : 'false';
        ?>
        <script>
        jQuery(document).ready(function ($) {
            const isUserLoggedIn = <?php echo $is_user_logged_in; ?>;
    
            $(document).on('click', '.toggle-reply-form', function () {
                var commentId = $(this).data('comment-id');
                var $respondBox = $('#respond-' + commentId);
                if (!isUserLoggedIn) {
                    $respondBox.addClass('reply-login-message');
                    $respondBox.html('<span class="reply-login-message">You must be logged in to reply to a comment.</span>').show();
                    return;
                }
    
                $respondBox.toggle();
            });
    
            $(document).on('click', '.like-button', function () {
                var button = $(this);
                var commentId = button.data('comment-id');
                var totalLikes = parseInt(button.data('likes')) || 0;
    
                $.post('/wp-admin/admin-ajax.php', {
                    action: 'submit_like',
                    commentID: commentId,
                    new_likes: totalLikes + 1
                }, function (data) {
                    if (data.success) {
                        button.closest('.like').html(
                            '<button class="dislike-button w-btn us-btn-style_2" data-comment-id="' + commentId + '" data-likes="' + (totalLikes + 1) + '">' +
                            '<span class="w-btn-label dislike-btns">' + (totalLikes + 1) + ' <i class="fas fa-thumbs-up"></i></span></button>'
                        );
                    } else {
                        alert('Error: ' + data.data);
                    }
                });
            });
    
            $(document).on('click', '.dislike-button', function () {
                var button = $(this);
                var commentId = button.data('comment-id');
                var totalLikes = parseInt(button.data('likes')) || 0;
    
                $.post('/wp-admin/admin-ajax.php', {
                    action: 'submit_dislike',
                    commentID: commentId,
                    new_likes: totalLikes - 1
                }, function (data) {
                    if (data.success) {
                        button.closest('.like').html(
                            '<button class="like-button w-btn us-btn-style_2" data-comment-id="' + commentId + '" data-likes="' + (totalLikes - 1) + '">' +
                            '<span class="w-btn-label">' + (totalLikes - 1) + ' <i class="fal fa-thumbs-up"></i></span></button>'
                        );
                    } else {
                        alert('Error: ' + data.data);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
