<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrohubProductPartnerPage {

    public static function init() {
        $self = new self();
        add_shortcode( 'frohub_product_partner_page', array($self, 'frohub_product_partner_page_shortcode') );
        add_action( 'wp_ajax_frohub_filter_products', [$self, 'ajax_filter_products'] );
        add_action( 'wp_ajax_nopriv_frohub_filter_products', [$self, 'ajax_filter_products'] );
    }

    public function frohub_product_partner_page_shortcode() {
        ob_start();

        echo '<div class="frohub-category-filter">';
        echo $this->render_category_filters();
        echo '</div>';

        echo '<div id="frohub-product-results">' . $this->render_products() . '</div>';
        echo '<div id="frohub-loading-spinner" style="display:none;text-align:center;padding:20px;">Loading...</div>';

        echo '<script>
            document.addEventListener("DOMContentLoaded", function () {
                const labels = document.querySelectorAll(".frohub-category-list .frohub-category-item");
                const spinner = document.getElementById("frohub-loading-spinner");
                const resultContainer = document.getElementById("frohub-product-results");

                labels.forEach(function(label) {
                    label.addEventListener("click", function () {
                        labels.forEach(el => el.classList.remove("selected"));
                        label.classList.add("selected");

                        const cat = label.dataset.slug;
                        const partnerId = ' . get_the_ID() . ';
                        const data = new URLSearchParams({
                            action: "frohub_filter_products",
                            filter_product_cat: cat,
                            partner_id: partnerId
                        });

                        spinner.style.display = "block";
                        resultContainer.style.opacity = "0.5";

                        fetch("' . admin_url("admin-ajax.php") . '", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: data
                        })
                        .then(res => res.text())
                        .then(html => {
                            resultContainer.innerHTML = html;
                            spinner.style.display = "none";
                            resultContainer.style.opacity = "1";
                        });
                    });
                });
            });
        </script>';

        echo '<style>
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
        </style>';

        return ob_get_clean();
    }

    public function ajax_filter_products() {
        $partner_id = intval($_POST['partner_id']);
        $filter_cat = sanitize_text_field($_POST['filter_product_cat'] ?? '');

        echo $this->render_products($partner_id, $filter_cat);
        wp_die();
    }

    private function render_products($partner_id = null, $filter_cat = '') {
        if (!$partner_id) {
            $partner_id = get_the_ID();
        }

        $tax_query = [];
        if (!empty($filter_cat) && $filter_cat !== 'all') {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $filter_cat,
            );
        }

        $args = array(
            'post_type'  => 'product',
            'fields'     => 'ids',
            'meta_query' => array(
                array(
                    'key'     => 'partner_id',
                    'value'   => $partner_id,
                    'compare' => '='
                )
            ),
            'tax_query' => $tax_query,
        );

        $query = new \WP_Query($args);
        $product_ids = $query->posts;
        $imploded_ids = implode(',', $product_ids);

        $grid = '[us_grid post_type="ids" ids="' . esc_attr($imploded_ids) . '" items_layout="197" columns="4" el_class="partner_profile_product_grid"]';

        return do_shortcode($grid);
    }

    private function render_category_filters() {
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0,
        ));

        if (empty($terms) || is_wp_error($terms)) return '';

        $output = '<ul class="frohub-category-list">';
        $output .= '<li class="frohub-category-item selected" data-slug="all">All</li>';
        foreach ($terms as $term) {
            $output .= '<li class="frohub-category-item" data-slug="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</li>';
        }
        $output .= '</ul>';

        return $output;
    }
}
