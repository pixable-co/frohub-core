<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MyQna {

    public static function init() {
        $self = new self();
        add_shortcode( 'my_qna', array($self, 'my_qna_shortcode') );
    }

    public function my_qna_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<p>You need to be logged in to view your Q&A.</p>';
        }

        ob_start();
        ?>
        <style>
        .qna-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .qna-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out;
            padding-bottom: 6rem;
            position: relative;
        }

        .qna-card:hover {
            transform: translateY(-3px);
        }

        .qna-card h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .qna-card h3 a {
            text-decoration: none;
            color: #0073aa;
        }

        .qna-card .qna-date {
            font-size: 14px;
            color: #666;
        }

        .qna-card .qna-excerpt {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
        }

        .qna-button {
            display: inline-block;
            background: #0073aa;
            color: #fff;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .qna-button:hover {
            background: #005f87;
        }

        .qna-comments {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .qna-comments i {
            margin-right: 5px;
            color: var(--color-content-primary);
        }

        .qna-card-footer {
            position: absolute;
            bottom: 1rem;
        }
        </style>
        <?php

        $current_user_id = get_current_user_id();

        $args = array(
            'post_type'      => 'q-a',
            'author'         => $current_user_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $qna_query = new \WP_Query($args);

        if ($qna_query->have_posts()) {
            echo '<div class="qna-container">';

            while ($qna_query->have_posts()) {
                $qna_query->the_post();
                $comment_count = get_comments_number();

                echo '<div class="qna-card">';
                echo '<h5><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h5>';
                echo '<p class="qna-date">' . esc_html(get_the_date()) . '</p>';
                echo '<p class="qna-excerpt">' . esc_html(get_the_excerpt()) . '</p>';

                echo '<div class="qna-card-footer">';
                echo '<p class="qna-comments"><i class="fas fa-comments"></i> ' . esc_html($comment_count) . ' Comments</p>';
                echo '<a href="' . esc_url(get_permalink()) . '" class="w-btn us-btn-style_1">View Details</a>';
                echo '</div>';

                echo '</div>';
            }

            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p>No questions found.</p>';
        }

        return ob_get_clean();
    }
}
