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
        ob_start();
        ?>
        <div class="frohub-category-filter">
          <ul class="frohub-category-list frohub-parent-list">
            <?php echo $this->render_parent_categories(); ?>
          </ul>
          <ul class="frohub-category-list frohub-child-list"></ul>
        </div>

        <div id="frohub-product-results">
          <?php echo $this->render_products(); ?>
        </div>
        <div id="frohub-loading-spinner"><div class="spinner"></div></div>

        <script>
        document.addEventListener('DOMContentLoaded', function(){
          const ajaxUrl   = '<?php echo admin_url("admin-ajax.php"); ?>';
          const partnerId = <?php echo get_the_ID(); ?>;
          const parentList= document.querySelector('.frohub-parent-list');
          const childList = document.querySelector('.frohub-child-list');
          const results   = document.getElementById('frohub-product-results');
          const spinner   = document.getElementById('frohub-loading-spinner');
          const initialHTML = <?php echo wp_json_encode( $this->render_products() ); ?>;

          let selectedParent = null;
          let selectedChild  = null;

          // helper: fetch & render products
          function filterProducts(){
            spinner.style.display = 'block';
            results.style.opacity = '0.5';
            const data = new URLSearchParams({
              action: 'frohub_filter_products',
              partner_id: partnerId,
            });
            if (selectedParent) data.append('filter_product_cat[]', selectedParent.dataset.slug);
            if (selectedChild)  data.append('filter_product_cat[]', selectedChild.dataset.slug);

            fetch(ajaxUrl, { method:'POST', body:data })
              .then(r => r.text())
              .then(html => {
                results.innerHTML     = html;
                spinner.style.display = 'none';
                results.style.opacity = '1';
              });
          }

          // helper: fetch & show subcategories
          function loadSubcategories(parentEl){
            const data = new URLSearchParams({
              action:    'frohub_get_subcategories',
              parent_id: parentEl.dataset.termId,
              partner_id
            });
            fetch(ajaxUrl, { method:'POST', body:data })
              .then(r => r.text())
              .then(html => {
                childList.innerHTML = html;
                // single‑select on each subcat
                childList.querySelectorAll('.frohub-category-item[data-type="subcat"]').forEach(li => {
                  li.addEventListener('click', () => {
                    if (selectedChild === li) {
                      li.classList.remove('selected');
                      selectedChild = null;
                    } else {
                      childList.querySelectorAll('.selected').forEach(i=>i.classList.remove('selected'));
                      li.classList.add('selected');
                      selectedChild = li;
                    }
                    filterProducts();
                  });
                });
              });
          }

          // bind clicks on all parents
          parentList.querySelectorAll('.frohub-category-item[data-type="parent"]').forEach(li => {
            li.addEventListener('click', () => {
              if (selectedParent === li) return;
              // clear old
              parentList.querySelectorAll('.selected').forEach(i=>i.classList.remove('selected'));
              childList.innerHTML = '';
              selectedChild = null;

              // select new
              li.classList.add('selected');
              selectedParent = li;

              // reload grid & children
              filterProducts();
              loadSubcategories(li);
            });
          });

          // on init: pick either the .selected in markup, or the first parent
          selectedParent = parentList.querySelector('.frohub-category-item.selected[data-type="parent"]');
          if (!selectedParent) {
            selectedParent = parentList.querySelector('.frohub-category-item[data-type="parent"]');
            if (selectedParent) selectedParent.classList.add('selected');
          }

          // if we have a parent at all, load its subs & products
          if (selectedParent) {
            loadSubcategories(selectedParent);
            filterProducts();
          } else {
            // nothing selected → show all
            results.innerHTML = initialHTML;
          }
        });
        </script>

        <style>
        .frohub-category-list{display:flex;gap:.5rem;list-style:none;padding:0;margin:0;flex-wrap:wrap;}
        .frohub-category-item{padding:.4em .8em;border:1px solid #ccc;border-radius:20px;cursor:pointer;transition:.2s;}
        .frohub-category-item:hover{background:#f5f5f5;}
        .frohub-category-item.selected{background:#0057e7;color:#fff;border-color:#0057e7;}
        #frohub-loading-spinner{display:none;position:relative;height:50px;margin-top:1rem;}
        #frohub-loading-spinner .spinner{position:absolute;top:50%;left:50%;width:30px;height:30px;margin:-15px;border:4px solid #f3f3f3;border-top:4px solid #0057e7;border-radius:50%;animation:spin 1s linear infinite;}
        @keyframes spin{to{transform:rotate(360deg);}}
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
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
        ]);
        foreach ( $terms as $t ) {
            // only if partner has any product in this term or its descendants
            $has = ( new \WP_Query([
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [[ 'key'=>'partner_id','value'=>$partner_id,'compare'=>'=' ]],
                'tax_query'      => [[ 'taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id,'include_children'=>true ]],
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
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => $parent_id,
        ]);
        foreach ( $terms as $t ) {
            // only if partner has a product in _that_ term
            $has = ( new \WP_Query([
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [[ 'key'=>'partner_id','value'=>$partner_id,'compare'=>'=' ]],
                'tax_query'      => [[ 'taxonomy'=>'product_cat','field'=>'term_id','terms'=>$t->term_id,'include_children'=>false ]],
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
                    'taxonomy'         => 'product_cat',
                    'field'            => 'slug',
                    'terms'            => [ $slug ],
                    'include_children' => $term->parent === 0,
                ];
            }
        }
        $args = [
            'post_type'  => 'product',
            'fields'     => 'ids',
            'meta_query' => [[ 'key'=>'partner_id','value'=>$partner_id,'compare'=>'=' ]],
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
