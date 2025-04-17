<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubProductPartnerPage {

    public static function init() {
        $self = new self();

        // Shortcode & product‐filter AJAX
        add_shortcode( 'frohub_product_partner_page',       [ $self, 'frohub_product_partner_page_shortcode' ] );
        add_action(   'wp_ajax_frohub_filter_products',     [ $self, 'ajax_filter_products' ] );
        add_action(   'wp_ajax_nopriv_frohub_filter_products', [ $self, 'ajax_filter_products' ] );

        // Sub‐categories AJAX
        add_action(   'wp_ajax_frohub_get_subcategories',       [ $self, 'ajax_get_subcategories' ] );
        add_action(   'wp_ajax_nopriv_frohub_get_subcategories', [ $self, 'ajax_get_subcategories' ] );
    }

    public function frohub_product_partner_page_shortcode() {
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

        <div id="frohub-loading-spinner" style="display:none;text-align:center;padding:20px;">
            Loading...
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const adminAjax = '<?php echo admin_url("admin-ajax.php"); ?>';
            const partnerId = <?php echo get_the_ID(); ?>;
            const parentList = document.querySelector('.frohub-parent-list');
            const childList  = document.querySelector('.frohub-child-list');
            const spinner    = document.getElementById('frohub-loading-spinner');
            const results    = document.getElementById('frohub-product-results');
            const initialHTML = <?php echo wp_json_encode( $this->render_products() ); ?>;
            const selectedSlugs = new Set();

            function filterProducts() {
                if (selectedSlugs.size === 0) {
                    results.innerHTML = initialHTML;
                    return;
                }
                spinner.style.display = 'block';
                results.style.opacity = 0.5;

                const data = new URLSearchParams();
                data.append('action', 'frohub_filter_products');
                data.append('partner_id', partnerId);
                selectedSlugs.forEach(slug => data.append('filter_product_cat[]', slug));

                fetch(adminAjax, { method: 'POST', body: data })
                  .then(r => r.text())
                  .then(html => {
                    results.innerHTML    = html;
                    spinner.style.display = 'none';
                    results.style.opacity = 1;
                  });
            }

            parentList.addEventListener('click', function(e) {
                const el = e.target.closest('.frohub-category-item');
                if (!el || el.dataset.type !== 'parent') return;

                // 1) Deselect any other parent, clear all slugs
                parentList.querySelectorAll('.frohub-category-item').forEach(i => {
                    i.classList.remove('selected');
                });
                selectedSlugs.clear();
                // 2) Clear out old children
                childList.innerHTML = '';

                // 3) Select this parent
                el.classList.add('selected');
                selectedSlugs.add(el.dataset.slug);

                // 4) Fetch and render products + children
                filterProducts();

                const subData = new URLSearchParams();
                subData.append('action', 'frohub_get_subcategories');
                subData.append('parent_id', el.dataset.termId);
                subData.append('partner_id', partnerId);

                fetch(adminAjax, { method: 'POST', body: subData })
                  .then(r => r.text())
                  .then(html => {
                    childList.innerHTML = html;
                    // Attach single‑select behavior to new children
                    childList.querySelectorAll('.frohub-category-item').forEach(item => {
                        item.addEventListener('click', function() {
                            childList.querySelectorAll('.frohub-category-item')
                                     .forEach(i => {
                                        i.classList.remove('selected');
                                        selectedSlugs.delete(i.dataset.slug);
                                     });
                            this.classList.add('selected');
                            selectedSlugs.add(this.dataset.slug);
                            filterProducts();
                        });
                    });
                  });
            });

            // Initial load
            filterProducts();
        });
        </script>

        <style>
        .frohub-category-list {
            display: flex;
            gap: 40px;
            list-style: none;
            padding: 0;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        .frohub-category-item {
            cursor: pointer;
            font-weight: 500;
            color: #1a1a1a;
            position: relative;
            padding-left: 1.5rem;
            transition: all 0.2s ease-in-out;
        }
        .frohub-category-item::before {
            content: "\2606";
            position: absolute;
            left: 0; top: 0;
            color: #001F54;
            transition: color 0.3s ease;
        }
        .frohub-category-item.selected {
            font-weight: bold;
            text-decoration: underline;
        }
        .frohub-category-item.selected::before {
            content: "\2605";
            color: #001F54;
        }
        .frohub-category-item:hover {
            color: #444;
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
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }
        $out = '';
        foreach ( $terms as $term ) {
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
                    'terms'            => $term->term_id,
                    'include_children' => true,
                ]],
            ]) )->have_posts();

            if ( $has ) {
                $out .= sprintf(
                    '<li class="frohub-category-item" data-type="parent" data-term-id="%d" data-slug="%s">%s</li>',
                    esc_attr( $term->term_id ),
                    esc_attr( $term->slug ),
                    esc_html( $term->name )
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
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }
        $out = '';
        foreach ( $terms as $term ) {
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
                    'terms'            => $term->term_id,
                    'include_children' => false,
                ]],
            ]) )->have_posts();

            if ( $has ) {
                $out .= sprintf(
                    '<li class="frohub-category-item" data-type="subcat" data-slug="%s">%s</li>',
                    esc_attr( $term->slug ),
                    esc_html( $term->name )
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
            $tax_query[] = [
                'taxonomy'         => 'product_cat',
                'field'            => 'slug',
                'terms'            => $filter_cats,
                'include_children' => true,
            ];
        }
        $args = [
            'post_type'  => 'product',
            'fields'     => 'ids',
            'meta_query' => [[
                'key'     => 'partner_id',
                'value'   => $partner_id,
                'compare' => '=',
            ]],
            'tax_query'  => $tax_query,
        ];
        $q = new \WP_Query( $args );
        $ids = implode( ',', $q->posts );
        $grid = sprintf(
            '[us_grid post_type="ids" ids="%s" items_layout="28802" columns="4" el_class="partner_profile_product_grid"]',
            esc_attr( $ids )
        );
        return do_shortcode( $grid );
    }
}

