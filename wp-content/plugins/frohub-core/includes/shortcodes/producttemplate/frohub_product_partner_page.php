<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubProductPartnerPage {

    public static function init() {
        $self = new self();

        // Shortcode & AJAX hooks
        add_shortcode( 'frohub_product_partner_page',       [ $self, 'frohub_product_partner_page_shortcode' ] );
        add_action(   'wp_ajax_frohub_filter_products',     [ $self, 'ajax_filter_products' ] );
        add_action(   'wp_ajax_nopriv_frohub_filter_products', [ $self, 'ajax_filter_products' ] );

        add_action(   'wp_ajax_frohub_get_subcategories',       [ $self, 'ajax_get_subcategories' ] );
        add_action(   'wp_ajax_nopriv_frohub_get_subcategories', [ $self, 'ajax_get_subcategories' ] );
    }

    public function frohub_product_partner_page_shortcode() {
        ob_start(); ?>
        <div class="frohub-category-filter">
          <ul class="frohub-category-list frohub-parent-list">
            <?php echo $this->render_parent_categories(); ?>
          </ul>
          <ul class="frohub-category-list frohub-child-list"></ul>
        </div>

        <div id="frohub-active-filters" style="margin:1rem 0;"></div>

        <div id="frohub-product-results">
          <?php echo $this->render_products(); ?>
        </div>

        <div id="frohub-loading-spinner" style="display:none;">
          <div class="spinner"></div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
          const adminAjax      = '<?php echo admin_url("admin-ajax.php"); ?>';
          const partnerId      = <?php echo get_the_ID(); ?>;
          const parentList     = document.querySelector('.frohub-parent-list');
          const childList      = document.querySelector('.frohub-child-list');
          const spinner        = document.getElementById('frohub-loading-spinner');
          const results        = document.getElementById('frohub-product-results');
          const activeFilters  = document.getElementById('frohub-active-filters');
          const initialHTML    = <?php echo wp_json_encode( $this->render_products() ); ?>;

          // holds at most one parent slug + one sub‑slug
          const selectedSlugs = new Set();

          function renderActiveFilters() {
            if (selectedSlugs.size === 0) {
              activeFilters.innerHTML = '';
              return;
            }
            let html = 'Filters: ';
            selectedSlugs.forEach(slug => {
              const el   = document.querySelector(`.frohub-category-item[data-slug="${slug}"]`);
              const name = el ? el.textContent.trim() : slug;
              html += `<span class="filter-pill" data-slug="${slug}">${name} &times;</span> `;
            });
            html += '<button id="frohub-clear-filters">Clear</button>';
            activeFilters.innerHTML = html;

            // remove individual
            activeFilters.querySelectorAll('.filter-pill').forEach(p => {
              p.addEventListener('click', () => {
                const slug = p.dataset.slug;
                selectedSlugs.delete(slug);
                // un‑highlight in lists
                const cat = document.querySelector(`.frohub-category-item[data-slug="${slug}"]`);
                if (cat) {
                  cat.classList.remove('selected');
                  if (cat.dataset.type === 'parent') {
                    childList.innerHTML = '';
                  }
                }
                filterProducts();
              });
            });

            // clear all
            document.getElementById('frohub-clear-filters')
              .addEventListener('click', () => {
                selectedSlugs.clear();
                document.querySelectorAll('.frohub-category-item.selected')
                        .forEach(i => i.classList.remove('selected'));
                childList.innerHTML = '';
                filterProducts();
              });
          }

          function filterProducts() {
            renderActiveFilters();

            // no filters → initial
            if (selectedSlugs.size === 0) {
              results.innerHTML = initialHTML;
              return;
            }

            spinner.style.display  = 'block';
            results.style.opacity  = 0.5;

            const data = new URLSearchParams();
            data.append('action', 'frohub_filter_products');
            data.append('partner_id', partnerId);
            selectedSlugs.forEach(s => data.append('filter_product_cat[]', s));

            fetch(adminAjax, { method:'POST', body:data })
              .then(r => r.text())
              .then(html => {
                results.innerHTML     = html;
                spinner.style.display = 'none';
                results.style.opacity = 1;
              });
          }

          // Parent click: single‑select + load children
          parentList.addEventListener('click', e => {
            const el = e.target.closest('.frohub-category-item');
            if (! el || el.dataset.type !== 'parent') return;

            // if already selected, deselect all
            if (el.classList.contains('selected')) {
              selectedSlugs.clear();
              parentList.querySelectorAll('.selected').forEach(i => i.classList.remove('selected'));
              childList.innerHTML = '';
              filterProducts();
              return;
            }

            // clear previous
            parentList.querySelectorAll('.selected').forEach(i => i.classList.remove('selected'));
            selectedSlugs.clear();
            childList.innerHTML = '';

            // select new parent
            el.classList.add('selected');
            selectedSlugs.add(el.dataset.slug);
            filterProducts();

            // fetch its subcategories
            const subData = new URLSearchParams({
              action:    'frohub_get_subcategories',
              parent_id: el.dataset.termId,
              partner_id
            });
            fetch(adminAjax, { method:'POST', body:subData })
              .then(r => r.text())
              .then(html => {
                childList.innerHTML = html;
                // single‑select subs
                childList.querySelectorAll('.frohub-category-item').forEach(sub => {
                  sub.addEventListener('click', () => {
                    // toggle
                    if (sub.classList.contains('selected')) {
                      subListDeselect();
                    } else {
                      subListDeselect();
                      sub.classList.add('selected');
                      selectedSlugs.add(sub.dataset.slug);
                    }
                    filterProducts();
                  });
                });
              });
          });

          function subListDeselect() {
            childList.querySelectorAll('.frohub-category-item.selected')
                     .forEach(i => {
                       i.classList.remove('selected');
                       selectedSlugs.delete(i.dataset.slug);
                     });
          }

          // initial render
          filterProducts();
        });
        </script>

        <style>
        .frohub-category-list {
          display: flex;
          gap: .5rem;
          list-style: none;
          padding: 0;
          margin: 0;
          flex-wrap: wrap;
        }
        .frohub-category-item {
          padding: .4em .8em;
          border-radius: 20px;
          border: 1px solid #ccc;
          cursor: pointer;
          user-select: none;
          transition: background-color .2s, border-color .2s;
        }
        .frohub-category-item:hover {
          background-color: #f5f5f5;
        }
        .frohub-category-item.selected {
          background-color: #0057e7;
          border-color: #0057e7;
          color: #fff;
        }
        .frohub-child-list {
          margin-top: .5rem;
        }
        #frohub-loading-spinner {
          position: relative;
          height: 50px;
          margin-top: 1rem;
        }
        #frohub-loading-spinner .spinner {
          box-sizing: border-box;
          position: absolute;
          top: 50%; left: 50%;
          width: 30px; height: 30px;
          margin: -15px 0 0 -15px;
          border: 4px solid #f3f3f3;
          border-top: 4px solid #0057e7;
          border-radius: 50%;
          animation: spin 1s linear infinite;
        }
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        .filter-pill {
          display: inline-block;
          background: #eee;
          border-radius: 12px;
          padding: 2px 8px;
          margin-right: 4px;
          cursor: pointer;
        }
        #frohub-clear-filters {
          background: none;
          border: none;
          color: #0057e7;
          cursor: pointer;
          text-decoration: underline;
          margin-left: .5rem;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    public function ajax_filter_products() {
        $partner_id  = intval( $_POST['partner_id'] ?? 0 );
        $filter_cats = $_POST['filter_product_cat'] ?? [];
        if ( ! is_array( $filter_cats ) ) {
            $filter_cats = [ $filter_cats ];
        }
        echo $this->render_products( $partner_id, $filter_cats );
        wp_die();
    }

    public function ajax_get_subcategories() {
        $parent_id  = intval( $_POST['parent_id'] ?? 0 );
        $partner_id = intval( $_POST['partner_id'] ?? 0 );
        echo $this->render_subcategories( $parent_id, $partner_id );
        wp_die();
    }

    private function render_parent_categories() {
        $partner_id = get_the_ID();
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
        ]);
        if ( ! $terms || is_wp_error( $terms ) ) {
            return '';
        }
        $out = '';
        foreach ( $terms as $t ) {
            // only if partner has at least one product in this term or its descendants
            $has = ( new \WP_Query([
                'post_type'      => 'product',
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'meta_query'     => [[
                    'key'     => 'partner_id',
                    'value'   => $partner_id,
                    'compare' => '=',
                ]],
                'tax_query'      => [[
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $t->term_id,
                    'include_children' => true,
                ]],
            ]) )->have_posts();

            if ( $has ) {
                $out .= sprintf(
                    '<li class="frohub-category-item" data-type="parent" data-term-id="%d" data-slug="%s">%s</li>',
                    esc_attr( $t->term_id ),
                    esc_attr( $t->slug ),
                    esc_html( $t->name )
                );
            }
        }
        return $out;
    }

    private function render_subcategories( $parent_id, $partner_id ) {
        if ( ! $partner_id ) {
            $partner_id = get_the_ID();
        }
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => $parent_id,
        ]);
        if ( ! $terms || is_wp_error( $terms ) ) {
            return '';
        }
        $out = '';
        foreach ( $terms as $t ) {
            // only if partner has ≥1 product in this exact term
            $has = ( new \WP_Query([
                'post_type'      => 'product',
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'meta_query'     => [[
                    'key'     => 'partner_id',
                    'value'   => $partner_id,
                    'compare' => '=',
                ]],
                'tax_query'      => [[
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $t->term_id,
                    'include_children' => false,
                ]],
            ]) )->have_posts();

            if ( $has ) {
                $out .= sprintf(
                    '<li class="frohub-category-item" data-type="subcat" data-slug="%s">%s</li>',
                    esc_attr( $t->slug ),
                    esc_html( $t->name )
                );
            }
        }
        return $out;
    }

    private function render_products( $partner_id = null, $filter_cats = [] ) {
        if ( ! $partner_id ) {
            $partner_id = get_the_ID();
        }

        $tax_query = [];
        if ( ! empty( $filter_cats ) ) {
            $tax_query['relation'] = 'AND';
            foreach ( $filter_cats as $slug ) {
                $term = get_term_by( 'slug', $slug, 'product_cat' );
                if ( ! $term ) {
                    continue;
                }
                $tax_query[] = [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'slug',
                    'terms'            => [ $slug ],
                    'include_children' => ( $term->parent === 0 ),
                ];
            }
        }

        $args = [
            'post_type'   => 'product',
            'fields'      => 'ids',
            'meta_query'  => [[
                'key'     => 'partner_id',
                'value'   => $partner_id,
                'compare' => '=',
            ]],
        ];
        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }

        $q  = new \WP_Query( $args );
        $ids = implode( ',', $q->posts );
        $grid = sprintf(
            '[us_grid post_type="ids" ids="%s" items_layout="28802" columns="4" el_class="partner_profile_product_grid"]',
            esc_attr( $ids )
        );
        return do_shortcode( $grid );
    }
}
