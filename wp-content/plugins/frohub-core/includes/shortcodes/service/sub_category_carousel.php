<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SubCategoryCarousel {

    public static function init() {
        $self = new self();
        add_shortcode( 'sub_category_carousel', array($self, 'sub_category_carousel_shortcode') );
    }

    public function sub_category_carousel_shortcode() {
        ob_start();

        // Fetch and sanitize URL parameters
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';

        if (!empty($category)) {
            if (self::is_parent_product_category($category)) {
                $subcategories = self::get_subcategories($category);

                echo '<h6>' . esc_html($category) . '</h6>';

                if (!empty($subcategories)) {
                    ?>
                    <div class="container mt-4">
                        <div class="swiper categorySwiper">
                            <div class="swiper-wrapper">
                                <?php
                                foreach ($subcategories as $subcat_id) {
                                    $term = get_term($subcat_id, 'product_cat');
                                    $thumbnail_id = get_term_meta($subcat_id, 'thumbnail_id', true);
                                    $category_image = ($thumbnail_id) ? wp_get_attachment_url($thumbnail_id) : wc_placeholder_img_src();
                                    $category_link = "/book-black-afro-hair-stylist-beauty-appointments/?categories=" . rawurlencode($term->name);
                                    ?>
                                    <div class="swiper-slide">
                                        <div class="category-item text-center">
                                            <a href="<?php echo esc_url($category_link); ?>">
                                                <div class="category-img-wrapper">
                                                    <img src="<?php echo esc_url($category_image); ?>" class="category-img" alt="<?php echo esc_attr($term->name); ?>">
                                                </div>
                                                <span class="mt-2"><?php echo esc_html($term->name); ?></span>
                                            </a>
                                        </div>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
                    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var swiper = new Swiper(".categorySwiper", {
                                slidesPerView: 6.5,
                                spaceBetween: 20,
                                loop: false,
                                grabCursor: true,
                                breakpoints: {
                                    1024: { slidesPerView: 6.5 },
                                    768: { slidesPerView: 3.5 },
                                    480: { slidesPerView: 2.5 }
                                }
                            });
                        });
                    </script>
                    <?php
                }
            } else {
                echo self::get_product_category_breadcrumbs($category);
            }
        }

        return ob_get_clean();
    }

    private static function is_parent_product_category($category) {
        $term = is_numeric($category)
            ? get_term($category, 'product_cat')
            : get_term_by('slug', $category, 'product_cat');

        return ($term && !is_wp_error($term)) ? ($term->parent == 0) : false;
    }

    private static function get_subcategories($parent_category) {
        $term = is_numeric($parent_category)
            ? get_term($parent_category, 'product_cat')
            : get_term_by('slug', $parent_category, 'product_cat');

        if (!$term || is_wp_error($term)) return [];

        $subcategories = get_terms([
            'taxonomy'   => 'product_cat',
            'parent'     => $term->term_id,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);

        return is_array($subcategories) ? $subcategories : [];
    }

    private static function get_product_category_breadcrumbs($category) {
        $term = is_numeric($category)
            ? get_term($category, 'product_cat')
            : get_term_by('slug', $category, 'product_cat');

        if (!$term || is_wp_error($term)) return '';

        $breadcrumbs = [];
        $parent_id = $term->parent;

        while ($parent_id != 0) {
            $parent_term = get_term($parent_id, 'product_cat');
            if ($parent_term && !is_wp_error($parent_term)) {
                $parent_url = "/book-black-afro-hair-stylist-beauty-appointments/?category=" . rawurlencode($parent_term->name);
                $breadcrumbs[] = '<a href="' . esc_url($parent_url) . '">' . esc_html($parent_term->name) . '</a>';
                $parent_id = $parent_term->parent;
            } else {
                break;
            }
        }

        $breadcrumbs = array_reverse($breadcrumbs);
        $breadcrumbs[] = '<span>' . esc_html($term->name) . '</span>';

        return '<nav class="subcategory-breadcrumb">' . implode(' <i class="far fa-chevron-right"></i> ', $breadcrumbs) . '</nav>';
    }
}
