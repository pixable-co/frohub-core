<?php

namespace FECore;

if (! defined('ABSPATH')) {
    exit;
}

class FrohubProductPartnerPage
{
    public static function init()
    {
        $self = new self();
        add_shortcode('frohub_product_partner_page',         [$self, 'shortcode']);
        add_action('wp_ajax_frohub_filter_products',       [$self, 'ajax_filter_products']);
        add_action('wp_ajax_nopriv_frohub_filter_products', [$self, 'ajax_filter_products']);
        add_action('wp_ajax_frohub_get_subcategories',       [$self, 'ajax_get_subcategories']);
        add_action('wp_ajax_nopriv_frohub_get_subcategories', [$self, 'ajax_get_subcategories']);
    }

    public function shortcode()
    {
        ob_start(); ?>
        <div class="frohub-category-filter">
            <ul class="frohub-category-list frohub-parent-list">
                <?php echo $this->render_parent_categories(); ?>
            </ul>
            <ul class="frohub-category-list frohub-child-list"></ul>
        </div>

        <div id="frohub-product-results" style="position:relative;">
            <?php echo $this->render_products(); ?>
            <div id="frohub-loading-spinner">
                <div class="spinner"></div>
                <div class="spinner-text">Loading…</div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                const partnerId = <?php echo get_the_ID(); ?>;
                const parentList = document.querySelector('.frohub-parent-list');
                const childList = document.querySelector('.frohub-child-list');
                const resultsWrap = document.getElementById('frohub-product-results');
                const spinner = document.getElementById('frohub-loading-spinner');
                let currentPage = 1;
                let selectedParent = null;
                let selectedChild = null;

                function filterProducts() {
                    spinner.style.display = 'flex';

                    const data = new URLSearchParams({
                        action: 'frohub_filter_products',
                        partner_id: partnerId,
                        page: currentPage
                    });

                    if (selectedParent && selectedParent.dataset.slug) {
                        data.append('filter_product_cat[]', selectedParent.dataset.slug);
                        if (selectedChild && selectedChild.dataset.slug) {
                            data.append('filter_product_cat[]', selectedChild.dataset.slug);
                        }
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: data
                    })
                        .then(r => r.text())
                        .then(html => {
                            resultsWrap.innerHTML = html;
                            resultsWrap.appendChild(spinner);
                            spinner.style.display = 'none';

                            resultsWrap.querySelectorAll('.frohub-page-number:not(.current), .frohub-page-prev:not(.disabled), .frohub-page-next:not(.disabled)').forEach(el => {
                                el.addEventListener('click', () => {
                                    const p = parseInt(el.dataset.page, 10);
                                    if (p && p !== currentPage) {
                                        currentPage = p;
                                        filterProducts();
                                    }
                                });
                            });
                        });
                }

                function loadSubcategories(parentEl) {
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'frohub_get_subcategories',
                            parent_id: parentEl.dataset.termId,
                            partner_id: partnerId
                        })
                    })
                        .then(r => r.text())
                        .then(html => {
                            childList.innerHTML = html;
                            childList.querySelectorAll('[data-type="subcat"]').forEach(li => {
                                li.addEventListener('click', () => {
                                    if (selectedChild === li) {
                                        li.classList.remove('selected');
                                        selectedChild = null;
                                    } else {
                                        childList.querySelectorAll('.selected').forEach(i => i.classList.remove('selected'));
                                        li.classList.add('selected');
                                        selectedChild = li;
                                    }
                                    currentPage = 1;
                                    filterProducts();
                                });
                            });
                        });
                }

                parentList.querySelectorAll('.frohub-category-item').forEach(li => {
                    li.addEventListener('click', () => {
                        parentList.querySelectorAll('.selected').forEach(i => i.classList.remove('selected'));
                        childList.innerHTML = '';
                        selectedChild = null;

                        li.classList.add('selected');

                        if (li.dataset.type === 'all') {
                            selectedParent = null;
                        } else {
                            selectedParent = li;
                            loadSubcategories(li);
                        }

                        currentPage = 1;
                        filterProducts();
                    });
                });

                const initial = parentList.querySelector('.selected') || parentList.querySelector('[data-type="all"]');
                if (initial) {
                    initial.click();
                }
            });
        </script>

        <style>
           /* Shared: Style for selected category item */
           .frohub-category-item.selected {
               font-weight: bold;
               border-bottom: 2px solid #0e2a5c;
               color: #0e2a5c;
           }

           /* Base styles for parent and child lists */
           .frohub-category-list {
               list-style: none;
               margin: 0;
               padding: 0;
           }

           /* Parent category list - horizontal scroll */
           .frohub-parent-list {
               display: flex;
               flex-wrap: nowrap;
               overflow-x: auto;
               white-space: nowrap;
               gap: 1rem;
               padding: 0.5rem 1rem;
               scroll-snap-type: x mandatory;
               -webkit-overflow-scrolling: touch;
               margin-bottom: 1rem;
           }

           .frohub-parent-list::-webkit-scrollbar {
               display: none;
           }

           .frohub-parent-list .frohub-category-item {
               display: inline-block;
               padding: 0.5rem 1rem;
               font-weight: 600;
               color: #0e2a5c;
               cursor: pointer;
               scroll-snap-align: start;
               white-space: nowrap;
               border-bottom: 2px solid transparent;
               transition: border-color 0.3s, color 0.3s;
           }

           /* Child category list - horizontal scroll */
           .frohub-child-list {
               display: flex;
               flex-wrap: nowrap;
               overflow-x: auto;
               white-space: nowrap;
               gap: 1rem;
               padding: 0.5rem 1rem;
               scroll-snap-type: x mandatory;
               -webkit-overflow-scrolling: touch;
               margin-bottom: 1.5rem;
           }

           .frohub-child-list::-webkit-scrollbar {
               display: none;
           }

           .frohub-child-list .frohub-category-item {
               display: inline-block;
               padding: 0.5rem 1rem;
               font-weight: 500;
               color: #444;
               cursor: pointer;
               scroll-snap-align: start;
               white-space: nowrap;
               border-bottom: 2px solid transparent;
               transition: border-color 0.3s, color 0.3s;
           }

           /* .selected is shared above — used by both lists */

           /* Desktop layout improvements */
           @media (min-width: 769px) {
               .frohub-parent-list,
               .frohub-child-list {
                   justify-content: center;
                   overflow-x: visible;
                   flex-wrap: wrap;
               }

               .frohub-parent-list .frohub-category-item,
               .frohub-child-list .frohub-category-item {
                   margin: 0.5rem;
               }
           }

        </style>
        <?php
        return ob_get_clean();
    }

    public function ajax_filter_products()
    {
        $partner_id  = intval($_POST['partner_id'] ?? 0);
        $page        = max(1, intval($_POST['page'] ?? 1));
        $filter_cats = (array) ($_POST['filter_product_cat'] ?? []);
        echo $this->render_products($partner_id, $filter_cats, $page);
        wp_die();
    }

    public function ajax_get_subcategories()
    {
        $parent_id  = intval($_POST['parent_id']  ?? 0);
        $partner_id = intval($_POST['partner_id'] ?? 0);
        echo $this->render_subcategories($parent_id, $partner_id);
        wp_die();
    }

    private function render_parent_categories()
    {
        $partner_id = get_the_ID();
        $out = '<li class="frohub-category-item selected" data-type="all" data-slug="">All</li>';

        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0]);
        foreach ($terms as $t) {
            $has = (new \WP_Query([
                'post_type' => 'product',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [['key' => 'partner_id', 'value' => $partner_id, 'compare' => '=']],
                'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $t->term_id, 'include_children' => true]],
            ]))->have_posts();
            if ($has) {
                $out .= sprintf(
                    '<li class="frohub-category-item" data-type="parent" data-term-id="%d" data-slug="%s">%s</li>',
                    esc_attr($t->term_id),
                    esc_attr($t->slug),
                    esc_html($t->name)
                );
            }
        }
        return $out;
    }

    private function render_subcategories($parent_id, $partner_id)
    {
        $out   = '';
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent_id]);
        foreach ($terms as $t) {
            $has = (new \WP_Query([
                'post_type' => 'product',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [['key' => 'partner_id', 'value' => $partner_id, 'compare' => '=']],
                'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $t->term_id, 'include_children' => false]],
            ]))->have_posts();
            if ($has) {
                $out .= sprintf(
                    '<li class="frohub-category-item" data-type="subcat" data-slug="%s">%s</li>',
                    esc_attr($t->slug),
                    esc_html($t->name)
                );
            }
        }
        return $out;
    }

    private function render_products($partner_id = null, $filter_cats = [], $paged = 1)
    {
        if (! $partner_id) {
            $partner_id = get_the_ID();
        }

        $tax_query = [];
        if (! empty($filter_cats)) {
            $tax_query['relation'] = 'AND';
            foreach ($filter_cats as $slug) {
                $term = get_term_by('slug', $slug, 'product_cat');
                if (! $term) continue;
                $tax_query[] = [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'slug',
                    'terms'            => [$slug],
                    'include_children' => $term->parent === 0,
                ];
            }
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 8,
            'paged'          => $paged,
            'fields'         => 'ids',
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'partner_id',
                    'value'   => $partner_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'is_private',
                    'value'   => '0', // false is stored as string '0' in ACF/DB
                    'compare' => '=',
                ],
            ],
        ];
        if (! empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $q = new \WP_Query($args);
        $ids = implode(',', $q->posts);
        $grid = do_shortcode(sprintf(
            '[us_grid post_type="ids" ids="%s" items_layout="28802" columns="4" el_class="partner_profile_product_grid"]',
            esc_attr($ids)
        ));
        $total_pages = $q->max_num_pages;

        $pagination = '';
        if ($total_pages > 1) {
            $pagination .= '<div class="frohub-pagination">';
            $pagination .= $paged > 1
                ? sprintf('<span class="frohub-page-prev" data-page="%d">Previous</span>', $paged - 1)
                : '<span class="frohub-page-prev disabled">Previous</span>';

            if ($total_pages <= 5) {
                for ($i = 1; $i <= $total_pages; $i++) {
                    $current = $i === $paged ? ' current' : '';
                    $pagination .= sprintf('<span class="frohub-page-number%s" data-page="%d">%d</span>', $current, $i, $i);
                }
            } else {
                for ($i = 1; $i <= 3; $i++) {
                    $current = $i === $paged ? ' current' : '';
                    $pagination .= sprintf('<span class="frohub-page-number%s" data-page="%d">%d</span>', $current, $i, $i);
                }
                $pagination .= '<span class="frohub-page-ellipsis">…</span>';
                $current = ($paged === $total_pages) ? ' current' : '';
                $pagination .= sprintf('<span class="frohub-page-number%s" data-page="%d">%d</span>', $current, $total_pages, $total_pages);
            }

            $pagination .= $paged < $total_pages
                ? sprintf('<span class="frohub-page-next" data-page="%d">Next Page</span>', $paged + 1)
                : '<span class="frohub-page-next disabled">Next Page</span>';
            $pagination .= '</div>';
        }

        return $grid . $pagination;
    }
}
