<?php

namespace FECore;

if (! defined('ABSPATH')) exit;

class FrohubRenderReviews
{

  public static function init()
  {
    $self = new self();
    add_shortcode('frohub_render_reviews', [$self, 'shortcode']);
    add_action('wp_ajax_frohub_get_reviews',       [$self, 'ajax_get_reviews']);
    add_action('wp_ajax_nopriv_frohub_get_reviews', [$self, 'ajax_get_reviews']);
  }

  public function shortcode()
  {
    ob_start(); ?>
    <div id="frohub-review-results" style="position:relative;">
      <div class="frohub-reviews-grid"></div>
      <div id="frohub-review-spinner">
        <div class="spinner"></div>
        <div class="spinner-text">Loading…</div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
        const partnerId = <?php echo get_the_ID(); ?>;
        const gridWrap = document.querySelector('.frohub-reviews-grid');
        const spinner = document.getElementById('frohub-review-spinner');
        let currentPage = 1;

        function loadReviews() {
          spinner.style.display = 'flex';
          const data = new URLSearchParams({
            action: 'frohub_get_reviews',
            partner_id: partnerId,
            page: currentPage
          });
          fetch(ajaxUrl, {
              method: 'POST',
              body: data
            })
            .then(r => r.text())
            .then(html => {
              gridWrap.innerHTML = html;
              spinner.style.display = 'none';
              // bind new pagination links
              gridWrap.querySelectorAll('.frohub-page-number:not(.current), .frohub-page-prev:not(.disabled), .frohub-page-next:not(.disabled)')
                .forEach(el => {
                  el.addEventListener('click', function() {
                    const p = parseInt(this.dataset.page, 10);
                    if (p && p !== currentPage) {
                      currentPage = p;
                      loadReviews();
                    }
                  });
                });
            });
        }

        loadReviews();
      });
    </script>

    <style>
      /* container for spinner */
      #frohub-review-results {
        position: relative;
        padding: 5rem 0 !important;
      }

      #frohub-review-spinner {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.7);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        z-index: 10;
      }

      #frohub-review-spinner .spinner {
        box-sizing: border-box;
        width: 36px;
        height: 36px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #0057e7;
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }

      #frohub-review-spinner .spinner-text {
        margin-top: .5em;
        color: #001F54;
        font-size: 1rem;
      }

      @keyframes spin {
        to {
          transform: rotate(360deg);
        }
      }
    </style>
<?php
    return ob_get_clean();
  }

  public function ajax_get_reviews()
  {
    $partner_id = intval($_POST['partner_id'] ?? 0);
    $paged      = max(1, intval($_POST['page'] ?? 1));

    // Fetch reviews for this partner
    $args = [
      'post_type'      => 'review',
      'posts_per_page' => 4,
      'paged'          => $paged,
      'fields'         => 'ids',
      'meta_query'     => [[
        'key'     => 'partner',
        'value'   => $partner_id,
        'compare' => '=',
      ]],
    ];
    $q = new \WP_Query($args);

    // Render grid
    $ids  = implode(',', $q->posts);
    $grid = do_shortcode(sprintf(
      '[us_grid post_type="ids" ids="%s" columns="2" items_layout="28803" el_class="partner_review_grid"]',
      esc_attr($ids)
    ));

    // Build pagination
    $total = $q->max_num_pages;
    $html  = $grid;
    if ($total > 1) {
      $html .= '<div class="frohub-pagination">';

      // Previous
      if ($paged > 1) {
        $html .= sprintf(
          '<span class="frohub-page-prev" data-page="%d">Previous</span>',
          $paged - 1
        );
      } else {
        $html .= '<span class="frohub-page-prev disabled">Previous</span>';
      }

      // Page links
      if ($total <= 5) {
        for ($i = 1; $i <= $total; $i++) {
          $cur = $i === $paged ? ' current' : '';
          $html .= sprintf(
            '<span class="frohub-page-number%s" data-page="%d">%d</span>',
            $cur,
            $i,
            $i
          );
        }
      } else {
        // first 3
        for ($i = 1; $i <= 3; $i++) {
          $cur = $i === $paged ? ' current' : '';
          $html .= sprintf(
            '<span class="frohub-page-number%s" data-page="%d">%d</span>',
            $cur,
            $i,
            $i
          );
        }
        $html .= '<span class="frohub-page-ellipsis">…</span>';
        // last
        $cur = $paged === $total ? ' current' : '';
        $html .= sprintf(
          '<span class="frohub-page-number%s" data-page="%d">%d</span>',
          $cur,
          $total,
          $total
        );
      }

      // Next
      if ($paged < $total) {
        $html .= sprintf(
          '<span class="frohub-page-next" data-page="%d">Next Page</span>',
          $paged + 1
        );
      } else {
        $html .= '<span class="frohub-page-next disabled">Next Page</span>';
      }

      $html .= '</div>';
    }

    echo $html;
    wp_die();
  }
}
