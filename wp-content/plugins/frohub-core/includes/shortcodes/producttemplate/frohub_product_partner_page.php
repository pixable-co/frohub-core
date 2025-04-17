<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubProductPartnerPage {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_product_partner_page',         [ $self, 'shortcode' ] );
        add_action(   'wp_ajax_frohub_filter_products',       [ $self, 'ajax_filter_products' ] );
        add_action(   'wp_ajax_nopriv_frohub_filter_products', [ $self, 'ajax_filter_products' ] );
        add_action(   'wp_ajax_frohub_get_subcategories',       [ $self, 'ajax_get_subcategories' ] );
        add_action(   'wp_ajax_nopriv_frohub_get_subcategories', [ $self, 'ajax_get_subcategories' ] );
    }

    public function shortcode() {
        ob_start(); ?>
        <div class="frohub-category-filter">
          <ul class="frohub-category-list frohub-parent-list">
            <?php echo $this->render_parent_categories(); ?>
          </ul>
          <ul class="frohub-category-list frohub-child-list"></ul>
        </div>

        <!-- Grid + Spinner wrapper -->
        <div id="frohub-product-results" style="position:relative;">
          <div class="frohub-products-grid">
            <?php echo $this->render_products(); ?>
          </div>
          <div id="frohub-loading-spinner">
            <div class="spinner"></div>
            <div class="spinner-text">Loading…</div>
          </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function(){
          const ajaxUrl      = '<?php echo admin_url("admin-ajax.php"); ?>';
          const partnerId    = <?php echo get_the_ID(); ?>;
          const parentList   = document.querySelector('.frohub-parent-list');
          const childList    = document.querySelector('.frohub-child-list');
          const gridContainer= document.querySelector('.frohub-products-grid');
          const spinner      = document.getElementById('frohub-loading-spinner');
          const initialHTML  = <?php echo wp_json_encode( $this->render_products() ); ?>;

          let selectedChild = null;

          function filterProducts(parentSlug, childSlug) {
            // show spinner overlay
            spinner.style.display = 'flex';
            // fetch new grid HTML
            const data = new URLSearchParams({
              action:     'frohub_filter_products',
              partner_id: partnerId,
            });
            data.append('filter_product_cat[]', parentSlug);
            if (childSlug) data.append('filter_product_cat[]', childSlug);

            fetch( ajaxUrl, { method:'POST', body:data } )
              .then(res => res.text())
              .then(html => {
                gridContainer.innerHTML = html;
                spinner.style.display   = 'none';
              });
          }

          function loadSubcategories(parentEl) {
            fetch( ajaxUrl, {
              method:'POST',
              body:new URLSearchParams({
                action:     'frohub_get_subcategories',
                parent_id:  parentEl.dataset.termId,
                partner_id: partnerId
              })
            })
            .then(res => res.text())
            .then(html => {
              childList.innerHTML = html;
              // bind subcat clicks
              childList.querySelectorAll('[data-type="subcat"]').forEach(li => {
                li.addEventListener('click', () => {
                  if (selectedChild === li) {
                    li.classList.remove('selected');
                    selectedChild = null;
                  } else {
                    childList.querySelectorAll('.selected')
                             .forEach(i=>i.classList.remove('selected'));
                    li.classList.add('selected');
                    selectedChild = li;
                  }
                  filterProducts(parentEl.dataset.slug,
                                 selectedChild?.dataset.slug);
                });
              });
            });
          }

          // bind parent clicks
          parentList.querySelectorAll('[data-type="parent"]').forEach(li=>{
            li.addEventListener('click', ()=>{
              parentList.querySelectorAll('.selected')
                        .forEach(i=>i.classList.remove('selected'));
              childList.innerHTML = '';
              selectedChild      = null;

              li.classList.add('selected');
              filterProducts(li.dataset.slug, null);
              loadSubcategories(li);
            });
          });

          // simulate initial click (pre‑selected or first)
          const initial = parentList.querySelector('.selected[data-type="parent"]')
                        || parentList.querySelector('[data-type="parent"]');
          if (initial) {
            initial.click();
          } else {
            // no parents? just show initial grid
            gridContainer.innerHTML = initialHTML;
          }
        });
        </script>

        <style>
        /* PRODUCTS GRID CONTAINER AS POSITIONING CONTEXT */
        #frohub-product-results {
          position: relative;
        }

        /* OVERLAY SPINNER */
        #frohub-loading-spinner {
          display: none;               /* toggled in JS */
          position: absolute;
          top: 0; left: 0; 
          width: 100%; height: 100%;
          background: rgba(255,255,255,0.7);
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          z-index: 10;
        }
        #frohub-loading-spinner .spinner {
          box-sizing: border-box;
          width: 36px; height: 36px;
          border: 4px solid #f3f3f3;
          border-top: 4px solid #0057e7;
          border-radius: 50%;
          animation: spin 1s linear infinite;
        }
        #frohub-loading-spinner .spinner-text {
          margin-top: 0.5em;
          font-size: 1rem;
          color: #001F54;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* CATEGORY STYLES (unchanged) */
        .frohub-category-list {
          display: flex;
          gap: 1rem;
          list-style: none;
          padding: 0;
          margin: 0 0 1rem;
          flex-wrap: wrap;
        }
        .frohub-category-item {
          cursor: pointer;
          padding: .25rem 0;
          font-weight: 500;
          color: #001F54;
          position: relative;
        }
        .frohub-category-item.selected::after {
          content: "";
          position: absolute;
          bottom: -2px;
          left: 0;
          width: 100%;
          height: 2px;
          background-color: #001F54;
        }
        .frohub-category-item:hover:not(.selected) {
          text-decoration: underline;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    public function ajax_filter_products() {
        $partner_id  = intval( $_POST['partner_id'] ?? 0 );
        $filter_cats = (array) ( $_POST['filter_product_cat'] ?? [] );
        echo $this->render_products( $partner_id, $filter_cats );
        wp_die();
    }

    public function ajax_get_subcategories() {
        $parent_id  = intval( $_POST['parent_id']  ?? 0 );
        $partner_id = intval( $_POST['partner_id'] ?? 0 );
        echo $this->render_subcategories( $parent_id, $partner_id );
        wp_die();
    }

    private function render_parent_categories() {
        $partner_id = get_the_ID();
        $out = '';
        $terms = get_terms([ 'taxonomy'=>'product_cat','hide_empty'=>false,'parent'=>0 ]);
        foreach ( $terms as $t ) {
            $has = ( new \WP_Query([
                'post_type'=>'product',
                'posts_per_page'=>1,
                'fields'=>'ids',
                'meta_query'=>[[ 'key'=>'partner_id','value'=>$partner_id,'compare'=>'=' ]],
                'tax_query'=>[[ 'taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id,'include_children'=>true ]],
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
        $out = '';
        $terms = get_terms([ 'taxonomy'=>'product_cat','hide_empty'=>false,'parent'=>$parent_id ]);
        foreach ( $terms as $t ) {
            $has = ( new \WP_Query([
                'post_type'=>'product',
                'posts_per_page'=>1,
                'fields'=>'ids',
                'meta_query'=>[[ 'key'=>'partner_id','value'=>$partner_id,'compare'=>'=' ]],
                'tax_query'=>[[ 'taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id,'include_children'=>false ]],
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
                if ( ! $term ) continue;
                $tax_query[] = [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => [ $slug ],
                    'include_children' => $term->parent === 0,
                ];
            }
        }
        $args = [
            'post_type' => 'product',
            'fields'    => 'ids',
            'meta_query'=> [[ 'key'=>'partner_id','value'=>$partner_id,'compare'=>'=' ]]
        ];
        if ( ! empty( $tax_query ) ) {
            $args['tax_query'] = $tax_query;
        }
        $q   = new \WP_Query( $args );
        $ids = implode( ',', $q->posts );
        return do_shortcode( sprintf(
          '[us_grid post_type="ids" ids="%s" items_layout="28802" columns="4" el_class="partner_profile_product_grid"]',
          esc_attr( $ids )
        ) );
    }
}
