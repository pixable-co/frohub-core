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
            document.addEventListener('DOMContentLoaded', function() {
                const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                const partnerId = <?php echo get_the_ID(); ?>;
                const parentList = document.querySelector('.frohub-parent-list');
                const childList = document.querySelector('.frohub-child-list');
                const resultsWrap = document.getElementById('frohub-product-results');
                const spinner = document.getElementById('frohub-loading-spinner');
                let currentPage = 1;
                let selectedParent = null;
                let selectedChild = null;

                // helper: perform AJAX fetch of products+pagination
                function filterProducts() {
                    if (!selectedParent) return;
                    spinner.style.display = 'flex';

                    const data = new URLSearchParams({
                        action: 'frohub_filter_products',
                        partner_id: partnerId,
                        page: currentPage
                    });
                    data.append('filter_product_cat[]', selectedParent.dataset.slug);
                    if (selectedChild) data.append('filter_product_cat[]', selectedChild.dataset.slug);

                    fetch(ajaxUrl, {
                            method: 'POST',
                            body: data
                        })
                        .then(r => r.text())
                        .then(html => {
                            resultsWrap.innerHTML = html;
                            // re‑append spinner node
                            resultsWrap.appendChild(spinner);
                            spinner.style.display = 'none';

                            // bind pagination clicks
                            resultsWrap.querySelectorAll('.frohub-page-number').forEach(el => {
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

                // load subcategories for a parent
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
                            // bind subcat clicks
                            childList.querySelectorAll('[data-type="subcat"]').forEach(li => {
                                li.addEventListener('click', () => {
                                    if (selectedChild === li) {
                                        li.classList.remove('selected');
                                        selectedChild = null;
                                    } else {
                                        childList.querySelectorAll('.selected')
                                            .forEach(i => i.classList.remove('selected'));
                                        li.classList.add('selected');
                                        selectedChild = li;
                                    }
                                    // reset to page 1 on new filter
                                    currentPage = 1;
                                    filterProducts();
                                });
                            });
                        });
                }

                // bind parent clicks
                parentList.querySelectorAll('[data-type="parent"]').forEach(li => {
                    li.addEventListener('click', () => {
                        parentList.querySelectorAll('.selected')
                            .forEach(i => i.classList.remove('selected'));
                        childList.innerHTML = '';
                        selectedChild = null;

                        li.classList.add('selected');
                        selectedParent = li;
                        currentPage = 1;
                        filterProducts();
                        loadSubcategories(li);
                    });
                });

                // initial: simulate click on pre‑selected or first parent
                const initial = parentList.querySelector('.selected[data-type="parent"]') ||
                    parentList.querySelector('[data-type="parent"]');
                if (initial) {
                    initial.click();
                }
            });
        </script>

        <style>
            /* spinner overlay */
            #frohub-product-results {
                position: relative;
            }

            #frohub-loading-spinner {
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
                justify-content: center;
                z-index: 10;
            }

            #frohub-loading-spinner .spinner {
                box-sizing: border-box;
                width: 36px;
                height: 36px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #0057e7;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            #frohub-loading-spinner .spinner-text {
                margin-top: .5em;
                color: #001F54;
                font-size: 1rem;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            /* pagination */
            .frohub-pagination {
                text-align: center;
                margin: 1rem 0;
                user-select: none;
            }

            .frohub-page-number {
                display: inline-block;
                margin: 0 .25rem;
                padding: .25rem .5rem;
                cursor: pointer;
                color: #0057e7;
            }

            .frohub-page-number.current {
                font-weight: bold;
                text-decoration: underline;
                cursor: default;
            }

            /* category styles (kept simple) */
            .frohub-category-list {
                display: flex;
                gap: 1rem;
                list-style: none;
                padding: 0;
                margin: 0 0 1rem;
            }

            .frohub-category-item {
                cursor: pointer;
                position: relative;
                padding: 0.25rem 0;
                color: #001F54;
            }

            .frohub-category-item.selected::after {
                content: "";
                position: absolute;
                bottom: -2px;
                left: 0;
                width: 100%;
                height: 2px;
                background: #001F54;
            }

            .frohub-category-item:hover:not(.selected) {
                text-decoration: underline;
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
        $out = '';
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

    /**
     * @param int   $partner_id
     * @param array $filter_cats
     * @param int   $paged
     */
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

        // 8 items per page
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 8,
            'paged'          => $paged,
            'fields'         => 'ids',
            'meta_query'     => [[
                'key'     => 'partner_id',
                'value'   => $partner_id,
                'compare' => '=',
            ]],
        ];
        if (! empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $q           = new \WP_Query($args);
        $ids         = implode(',', $q->posts);
        $grid        = do_shortcode(sprintf(
            '[us_grid post_type="ids" ids="%s" items_layout="28802" columns="4" el_class="partner_profile_product_grid"]',
            esc_attr($ids)
        ));
        $total_pages = $q->max_num_pages;

        // build pagination
        // after you've run the WP_Query and built $grid & determined $total_pages…

        $pagination = '';
        if ($total_pages > 1) {
            $pagination .= '<div class="frohub-pagination">';

            // ← Previous
            if ($paged > 1) {
                $pagination .= sprintf(
                    '<span class="frohub-page-prev" data-page="%d">Previous</span>',
                    $paged - 1
                );
            } else {
                $pagination .= '<span class="frohub-page-prev disabled">Previous</span>';
            }

            // ← page numbers (show 1,2,3 … last)
            if ($total_pages <= 5) {
                // small set: list them all
                for ($i = 1; $i <= $total_pages; $i++) {
                    $current = $i === $paged ? ' current' : '';
                    $pagination .= sprintf(
                        '<span class="frohub-page-number%s" data-page="%d">%d</span>',
                        $current,
                        $i,
                        $i
                    );
                }
            } else {
                // large set: first 3
                for ($i = 1; $i <= 3; $i++) {
                    $current = $i === $paged ? ' current' : '';
                    $pagination .= sprintf(
                        '<span class="frohub-page-number%s" data-page="%d">%d</span>',
                        $current,
                        $i,
                        $i
                    );
                }
                $pagination .= '<span class="frohub-page-ellipsis">…</span>';
                // last page
                $current = ($paged === $total_pages) ? ' current' : '';
                $pagination .= sprintf(
                    '<span class="frohub-page-number%s" data-page="%d">%d</span>',
                    $current,
                    $total_pages,
                    $total_pages
                );
            }

            // Next →
            if ($paged < $total_pages) {
                $pagination .= sprintf(
                    '<span class="frohub-page-next" data-page="%d">Next Page</span>',
                    $paged + 1
                );
            } else {
                $pagination .= '<span class="frohub-page-next disabled">Next Page</span>';
            }

            $pagination .= '</div>';
        }

        return $grid . $pagination;
    }
}
