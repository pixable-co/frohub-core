<?php

namespace FECore;

if (! defined('ABSPATH')) {
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
            'post_type'      => 'review',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'     => 'partner',
                'value'   => $partner_id,
                'compare' => '=',
            ]]
        ]);
        $ids   = $q->posts;
        $count = count($ids);

        if ($count === 0) {
            return '<p>No reviews yet.</p>';
        }

        // Sum up each ACF rating
        $sum_overall        = 0;
        $sum_reliability    = 0;
        $sum_skill          = 0;
        $sum_professionalism = 0;

        foreach ($ids as $rid) {
            $sum_overall         += floatval(get_field('overall_rating',     $rid) ?: 0);
            $sum_reliability     += floatval(get_field('reliability',        $rid) ?: 0);
            $sum_skill           += floatval(get_field('skill',              $rid) ?: 0);
            $sum_professionalism += floatval(get_field('professionalism',    $rid) ?: 0);
        }

        // Compute one‑decimal averages
        $avg_overall        = round($sum_overall        / $count, 1);
        $avg_reliability    = round($sum_reliability    / $count, 1);
        $avg_skill          = round($sum_skill          / $count, 1);
        $avg_professionalism = round($sum_professionalism / $count, 1);

        // Build output
        ob_start(); ?>
        <div class="frohub-review-summary">
            <div class="overall">
                <div class="label">
                    <strong>Overall Rating</strong> (<?php echo esc_html($avg_overall); ?>)
                </div>
                <div class="stars">
                    <?php
                    // render 5 stars with partial logic
                    $full = floor($avg_overall);
                    $half = ($avg_overall - $full) >= .5 ? 1 : 0;
                    for ($i = 0; $i < $full; $i++) echo '★';
                    if ($half) echo '★';
                    for ($i = $full + $half; $i < 5; $i++) echo '☆';
                    ?>
                </div>
                <div class="count"><?php echo esc_html($count); ?> reviews</div>
            </div>

            <div class="metrics">
                <div class="metric">
                    <div class="circle"></div>
                    <div class="meta-label">
                        Reliability <i class="fas fa-star"></i> <?php echo esc_html($avg_reliability); ?>
                    </div>
                </div>
                <div class="metric">
                    <div class="circle"></div>
                    <div class="meta-label">
                        Skill <i class="fas fa-star"></i> <?php echo esc_html($avg_skill); ?>
                    </div>
                </div>
                <div class="metric">
                    <div class="circle"></div>
                    <div class="meta-label">
                        Professionalism <i class="fas fa-star"></i> <?php echo esc_html($avg_professionalism); ?>
                    </div>
                </div>
            </div>


            <style>
                .frohub-review-summary {
                    display: flex;
                    align-items: center;
                    gap: 4rem;
                    font-family: sans-serif;
                    margin: 2rem 0;
                }

                .frohub-review-summary .overall .label {
                    font-size: 1.125rem;
                    margin-bottom: .25rem;
                }

                .frohub-review-summary .overall .stars {
                    font-size: 1.25rem;
                    color: #f5a623;
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

                .frohub-review-summary .metric {
                    display: flex;
                    align-items: center;
                    /* circle + label on baseline */
                    gap: 0.5rem;
                }

                .frohub-review-summary .metric .circle {
                    flex-shrink: 0;
                    width: 40px;
                    height: 40px;
                    background: #ddd;
                    border-radius: 50%;
                }

                .frohub-review-summary .metric .meta-label {
                    font-size: .95rem;
                    color: #333;
                }

                .frohub-review-summary .metric .meta-label i.fas.fa-star {
                    margin-left: .25rem;
                    color: #f5a623;
                    /* gold star color */
                }
            </style>
    <?php
        return ob_get_clean();
    }
}
