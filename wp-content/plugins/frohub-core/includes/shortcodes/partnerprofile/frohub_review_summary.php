<?php

namespace FECore;

if (!defined('ABSPATH')) {
    exit;
}

class FrohubReviewSummary
{

    public static function init()
    {
        $self = new self();
        add_shortcode('frohub_review_summary', [$self, 'frohub_review_summary_shortcode']);
    }

    public function frohub_review_summary_shortcode()
    {
        global $post;
        $partner_id = $post->ID;

        // Fetch all review IDs for this partner
        $q = new \WP_Query([
            'post_type' => 'review',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'partner',
                    'value' => $partner_id,
                    'compare' => '=',
                ]
            ]
        ]);
        $ids = $q->posts;
        $count = count($ids);

        if ($count === 0) {
            return '<p>No reviews yet.</p>';
        }

        // Sum up each ACF rating
        $sum_overall = 0;
        $sum_reliability = 0;
        $sum_skill = 0;
        $sum_professionalism = 0;

        foreach ($ids as $rid) {
            $sum_overall += floatval(get_field('overall_rating', $rid) ?: 0);
            $sum_reliability += floatval(get_field('reliability', $rid) ?: 0);
            $sum_skill += floatval(get_field('skill', $rid) ?: 0);
            $sum_professionalism += floatval(get_field('professionalism', $rid) ?: 0);
        }

        $avg_overall = round($sum_overall / $count, 1);
        $avg_reliability = round($sum_reliability / $count, 1);
        $avg_skill = round($sum_skill / $count, 1);
        $avg_professionalism = round($sum_professionalism / $count, 1);

        ob_start(); ?>
        <div class="frohub-review-summary">
            <div class="overall">
                <div class="label">
                    <strong>Overall Rating</strong> (<?php echo esc_html(number_format($avg_overall, 0)); ?>)
                </div>
                <div class="stars">
                    <?php
                    // show one <i> per whole star only
                    $full = floor($avg_overall);
                    for ($i = 0; $i < $full; $i++) {
                        echo '<i class="fas fa-star"></i>';
                    }
                    ?>
                </div>

                <div class="count"><?php echo esc_html($count); ?> reviews</div>
            </div>

            <div class="metrics">
                <div class="metric">
                    <i class="fas fa-shield-alt fa-2x metric-icon"></i>
                    <div class="meta-label">
                        Reliability
                        <span class="star-rating-container">
                            <i class="fas fa-star"></i>
                            <?php echo esc_html(number_format($avg_reliability, 0)); ?>
                        </span>
                    </div>
                </div>
                <div class="metric">
                    <i class="fas fa-cut fa-2x metric-icon"></i>
                    <div class="meta-label">
                        Skill
                        <span class="star-rating-container">
                            <i class="fas fa-star"></i>
                            <?php echo esc_html(number_format($avg_skill, 0)); ?>
                        </span>
                    </div>
                </div>
                <div class="metric">
                    <i class="fas fa-user-tie fa-2x metric-icon"></i>
                    <div class="meta-label">
                        Professionalism
                        <span class="star-rating-container">
                            <i class="fas fa-star"></i>
                            <?php echo esc_html(number_format($avg_professionalism, 0)); ?>
                        </span>
                    </div>
                </div>
            </div>



            <style>
                .frohub-review-summary {
                    display: flex;
                    flex-direction: row;
                    gap: 4rem;
                    margin: 2rem 0;
                }

                @media only screen and (max-width: 600px) {
                    .frohub-review-summary {
                        flex-direction: column;
                    }
                }

                .frohub-review-summary .overall .label {
                    font-size: 1.125rem;
                    margin-bottom: .25rem;
                }

                .frohub-review-summary .overall .stars {
                    font-size: 1.25rem;
                    color: #333333;
                    line-height: 1;
                    margin-bottom: .25rem;
                }

                .frohub-review-summary .overall .count {
                    color: #666;
                    font-size: .9rem;
                }

                .frohub-review-summary .metrics {
                    display: flex;
                    gap: 2rem;
                    align-items: flex-start;
                    /* keep circles & text top‑aligned */
                }

                .frohub-review-summary .metrics {
                    display: flex;
                    gap: 2rem;
                    align-items: center;
                    /* icons and labels align nicely */
                }

                .frohub-review-summary .metric {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.5rem;
                    flex-direction: column;
                }

                .frohub-review-summary .metric-icon {
                    color: var(--color-alt-content-primary);
                }

                .frohub-review-summary .meta-label {
                    font-size: 0.95rem;
                    color: #333;
                }

                .frohub-review-summary .meta-label i.fas.fa-star {
                    margin-left: 0.25rem;
                    color: #333333;
                    /* gold star */
                }
            </style>
            <?php
            return ob_get_clean();
    }
}
