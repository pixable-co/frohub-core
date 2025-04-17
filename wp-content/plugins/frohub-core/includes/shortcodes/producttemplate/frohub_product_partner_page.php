<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubProductPartnerPage {

    public static function init() {
        $self = new self();
        // Shortcode & product‐filter AJAX
        add_shortcode( 'frohub_product_partner_page', [ $self, 'frohub_product_partner_page_shortcode' ] );
        add_action( 'wp_ajax_frohub_filter_products',       [ $self, 'ajax_filter_products' ] );
        add_action( 'wp_ajax_nopriv_frohub_filter_products', [ $self, 'ajax_filter_products' ] );

        // Sub‐categories AJAX
        add_action( 'wp_ajax_frohub_get_subcategories',       [ $self, 'ajax_get_subcategories' ] );
        add_action( 'wp_ajax_nopriv_frohub_get_subcategories', [ $self, 'ajax_get_subcategories' ] );

        // “Back to parents” AJAX
        add_action( 'wp_ajax_frohub_get_parent_categories',       [ $self, 'ajax_get_parent_categories' ] );
        add_action( 'wp_ajax_nopriv_frohub_get_parent_categories', [ $self, 'ajax_get_parent_categories' ] );
    }

    public function frohub_product_partner_page_shortcode() {
        ob_start();
        ?>
        <div class="frohub-category-filter">
            <?php echo $this->render_parent_categories(); ?>
        </div>

        <div id="frohub-product-results">
            <?php echo $this->render_products(); ?>
        </div>

        <div id="frohub-loading-spinner" style="display:none;text-align:center;padding:20px;">
            Loading...
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function () {
            const container = document.querySelector(".frohub-category-filter");
            const spinner = document.getElementById("frohub-loading-spinner");
            const resultContainer = document.getElementById("frohub-product-results");
            const partnerId = <?php echo get_the_ID(); ?>;

            // Cache the initial parent markup so "Back" can restore it
            const parentCategoriesHTML = <?php echo wp_json_encode( $this->render_parent_categories() ); ?>;

            function attachCategoryListeners() {
                const items = container.querySelectorAll(".frohub-category-item");
                items.forEach(item => {
                    item.addEventListener("click", () => {
                        const type = item.dataset.type;

                        if (type === "parent") {
                            // Fetch sub‑categories for this parent
                            const data = new URLSearchParams({
                                action:    "frohub_get_subcategories",
                                parent_id: item.dataset.termId
                            });

                            fetch("<?php echo admin_url( 'admin-ajax.php' ); ?>", {
                                method:  "POST",
                                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                body:    data
                            })
                            .then(res => res.text())
                            .then(html => {
                                container.innerHTML = html;
                                attachCategoryListeners();
                            });

                        } else if (type === "subcat") {
                            // Fetch products for this sub‑category
                            items.forEach(el => el.classList.remove("selected"));
                            item.classList.add("selected");

                            const data = new URLSearchParams({
                                action:             "frohub_filter_products",
                                filter_product_cat: item.dataset.slug,
                                partner_id:         partnerId
                            });

                            spinner.style.display = "block";
                            resultContainer.style.opacity = "0.5";

                            fetch("<?php echo admin_url( 'admin-ajax.php' ); ?>", {
                                method:  "POST",
                                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                body:    data
                            })
                            .then(res => res.text())
                            .then(html => {
                                resultContainer.innerHTML = html;
                                spinner.style.display = "none";
                                resultContainer.style.opacity = "1";
                            });

                        } else if (type === "back") {
                            // Go back to parent list
                            container.innerHTML = parentCategoriesHTML;
                            attachCategoryListeners();
                        }
                    });
                });
            }

            attachCategoryListeners();
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
            content: "\2606"; /* Empty star */
            position: absolute;
            left: 0;
            top: 0;
            color: #001F54;
            transition: color 0.3s ease;
        }
        .frohub-category-item.selected {
            font-weight: bold;
            text-decoration: underline;
        }
        .frohub-category-item.selected::before {
            content: "\2605"; /* Filled star */
            color: #001F54;
        }
        .frohub-category-item:hover {
            color: #444;
        }
        .frohub-category-item.back::before {
            content: "\2190"; /* Left arrow */
            font-size: 0.9em;
            color: #001F54;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    // ------------------------------------------------------
    // AJAX: filter products (unchanged)
    public function ajax_filter_products() {
        $partner_id = intval( $_POST['partner_id'] );
        $filter_cat = sanitize_text_field( $_POST['filter_product_cat'] ?? '' );
        echo $this->render_products( $partner_id, $filter_cat );
        wp_die();
    }

    // AJAX: return sub‐category list for a given parent ID
    public function ajax_get_subcategories() {
        $parent_id = intval( $_POST['parent_id'] );
        echo $this->render_subcategories( $parent_id );
        wp_die();
    }

    // AJAX: return the original parent‐category list
    public function ajax_get_parent_categories() {
        echo $this->render_parent_categories();
        wp_die();
    }

    // ------------------------------------------------------
    // Render only the “parent” categories (parent = 1)
    private function render_parent_categories() {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
        ]);
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }

        $output = '<ul class="frohub-category-list">';
        foreach ( $terms as $term ) {
            $output .= sprintf(
                '<li class="frohub-category-item" data-type="parent" data-term-id="%d" data-slug="%s">%s</li>',
                esc_attr( $term->term_id ),
                esc_attr( $term->slug ),
                esc_html( $term->name )
            );
        }
        $output .= '</ul>';
        return $output;
    }

    // Render its immediate children (sub‑categories) plus a Back button
    private function render_subcategories( $parent_id ) {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => $parent_id,
        ]);
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return '';
        }

        $output  = '<ul class="frohub-category-list">';
        $output .= '<li class="frohub-category-item back" data-type="back">Back</li>';
        foreach ( $terms as $term ) {
            $output .= sprintf(
                '<li class="frohub-category-item" data-type="subcat" data-slug="%s">%s</li>',
                esc_attr( $term->slug ),
                esc_html( $term->name )
            );
        }
        $output .= '</ul>';
        return $output;
    }

    // Product‐grid rendering (unchanged)
    private function render_products( $partner_id = null, $filter_cat = '' ) {
        if ( ! $partner_id ) {
            $partner_id = get_the_ID();
        }
        $tax_query = [];
        if ( $filter_cat && $filter_cat !== 'all' ) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $filter_cat,
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
            'tax_query' => $tax_query,
        ];

        $query        = new \WP_Query( $args );
        $product_ids  = $query->posts;
        $imploded_ids = implode( ',', $product_ids );

        $grid = sprintf(
            '[us_grid post_type="ids" ids="%s" items_layout="28802" columns="4" el_class="partner_profile_product_grid"]',
            esc_attr( $imploded_ids )
        );

        return do_shortcode( $grid );
    }
}
