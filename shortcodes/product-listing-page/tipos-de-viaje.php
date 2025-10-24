<?php
/**
 * Shortcode: [manaslu_trip_types]
 *
 * Renderiza las categorías de tipo "tipos-de-viaje" asociadas a los productos
 * presentes en la categoría de producto que se está navegando. Permite resaltar
 * dinámicamente los estilos de viaje disponibles dentro de la selección actual.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Genera el HTML del shortcode que lista las categorías hijas del término
 * "tipos-de-viaje" presentes en la consulta actual de productos.
 *
 * @param array $atts Atributos del shortcode.
 * @return string HTML listo para renderizar o cadena vacía si no aplica.
 */
function manaslu_render_trip_types_shortcode($atts = [])
{
    if (!is_tax('product_cat')) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'parent_slug' => 'tipos-de-viaje',
            'limit'       => 0,
            'class'       => '',
        ],
        $atts,
        'manaslu_trip_types'
    );

    $parent_term = get_term_by('slug', $atts['parent_slug'], 'product_cat');
    if (!$parent_term || is_wp_error($parent_term)) {
        return '';
    }

    global $wp_query;

    if (empty($wp_query) || empty($wp_query->posts)) {
        return '';
    }

    $product_ids = wp_list_pluck($wp_query->posts, 'ID');
    if (empty($product_ids)) {
        return '';
    }

    $terms = wp_get_object_terms(
        $product_ids,
        'product_cat',
        [
            'orderby'     => 'name',
            'hide_empty'  => false,
            'fields'      => 'all',
            'parent'      => $parent_term->term_id,
        ]
    );

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    // Garantiza que cada término solo aparezca una vez.
    $trip_terms = [];
    foreach ($terms as $term) {
        if ((int) $term->parent !== (int) $parent_term->term_id) {
            continue;
        }
        $trip_terms[$term->term_id] = $term;
    }

    if (empty($trip_terms)) {
        return '';
    }

    $trip_terms = array_values($trip_terms);

    usort(
        $trip_terms,
        static function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        }
    );

    $limit = (int) $atts['limit'];
    if ($limit > 0) {
        $trip_terms = array_slice($trip_terms, 0, $limit);
    }

    $classes = ['manaslu-trip-types'];
    if (!empty($atts['class'])) {
        $extra_classes = preg_split('/\s+/', $atts['class']);
        $extra_classes = array_filter(array_map('sanitize_html_class', (array) $extra_classes));
        $classes = array_merge($classes, $extra_classes);
    }

    static $style_printed = false;
    static $script_printed = false;
    $style_block = '';
    $script_block = '';

    if (!$style_printed) {
        $style_printed = true;
        $style_block   = '<style>
.manaslu-trip-types{position:relative;--manaslu-trip-gap:24px}
.manaslu-trip-types__viewport{overflow-x:auto;overflow-y:visible;scrollbar-width:none;-ms-overflow-style:none}
.manaslu-trip-types__viewport::-webkit-scrollbar{display:none}
.manaslu-trip-types__track{display:flex;gap:10px;scroll-behavior:smooth}
.manaslu-trip-types__item{flex:0 0 calc((100% - (var(--manaslu-trip-gap) * 3))/4);position:relative;display:flex;align-items:flex-end;justify-content:flex-start;padding:24px;border-radius:28px;overflow:hidden;min-height:320px;aspect-ratio:1/1;color:#fff;text-decoration:none;background-color:#111;background-position:center;background-size:cover;transition:transform .3s ease}
.manaslu-trip-types__item::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(8,11,15,0) 45%,rgba(8,11,15,.85) 100%);transition:opacity .3s ease}
.manaslu-trip-types__item::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 50% 20%,rgba(255,255,255,.1),rgba(255,255,255,0));opacity:0;transition:opacity .3s ease}
.manaslu-trip-types__item:hover{transform:translateY(-6px);box-shadow:0 22px 40px rgba(8,11,15,.25)}
.manaslu-trip-types__item:hover::after{opacity:1}
.manaslu-trip-types__content{position:relative;z-index:1;display:flex;flex-direction:column;gap:8px}
.manaslu-trip-types__title{font-size:1.125rem;font-weight:600;line-height:1.3;margin:0}
.manaslu-trip-types__excerpt{font-size:.9375rem;line-height:1.45;opacity:.9;margin:0}
.manaslu-trip-types__item:focus-visible{outline:3px solid rgba(255,255,255,.65);outline-offset:4px}
.manaslu-trip-types__nav{position:absolute;inset:0;display:flex;justify-content:space-between;align-items:center;padding:0 12px;pointer-events:none;z-index:2}
.manaslu-trip-types__nav button{pointer-events:auto;border:0;background:#FB2240;color:#fff;border-radius:999px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .3s ease,transform .3s ease;font-size:1.75rem;line-height:1}
.manaslu-trip-types__nav-icon{display:block;line-height:1}
.manaslu-trip-types__nav button:hover{background:#d91d35;transform:scale(1.05)}
.manaslu-trip-types__nav button:focus-visible{outline:3px solid rgba(255,255,255,.65);outline-offset:2px}
.manaslu-trip-types__nav button[disabled]{opacity:.45;cursor:default;transform:none}
.manaslu-trip-types__nav button[disabled]:hover{background:#FB2240;transform:none}
@media (hover:hover){.manaslu-trip-types__nav button{opacity:.85}.manaslu-trip-types__nav button:hover{opacity:1}}
@media (max-width:1200px){.manaslu-trip-types__item{flex:0 0 calc((100% - (var(--manaslu-trip-gap) * 2))/3)}}
@media (max-width:960px){.manaslu-trip-types__item{flex:0 0 calc((100% - var(--manaslu-trip-gap))/2)}}
@media (max-width:768px){.manaslu-trip-types{--manaslu-trip-gap:16px}.manaslu-trip-types__viewport{scroll-snap-type:x mandatory;padding-bottom:12px}.manaslu-trip-types__track{gap:var(--manaslu-trip-gap)}.manaslu-trip-types__item{flex:0 0 80%;min-height:260px;padding:20px;border-radius:22px;scroll-snap-align:start}.manaslu-trip-types__nav{display:none}}
</style>';
    }

    if (!$script_printed) {
        $script_printed = true;
        $script_block   = '<script>
(function(){
    function resolveVisibleCount(){
        if (window.matchMedia("(max-width: 768px)").matches) {
            return 1;
        }
        if (window.matchMedia("(max-width: 960px)").matches) {
            return 2;
        }
        if (window.matchMedia("(max-width: 1200px)").matches) {
            return 3;
        }
        return 4;
    }

    function computeGap(track){
        if (!track) {
            return 0;
        }
        var styles = window.getComputedStyle(track);
        var gapValue = styles.gap || styles.columnGap || styles.gridColumnGap || "0";
        var gap = parseFloat(gapValue);
        if (isNaN(gap)) {
            gap = 0;
        }
        return gap;
    }

    function initTripSliders(){
        document.querySelectorAll(".manaslu-trip-types[data-slider-id]").forEach(function(slider){
            if (slider.dataset.sliderInited === "1") {
                return;
            }

            var viewport = slider.querySelector(".manaslu-trip-types__viewport");
            var track = slider.querySelector(".manaslu-trip-types__track");
            if (!viewport || !track) {
                return;
            }

            slider.dataset.sliderInited = "1";
            var prevBtn = slider.querySelector("[data-dir=\'prev\']");
            var nextBtn = slider.querySelector("[data-dir=\'next\']");

            function getItems(){
                return Array.prototype.slice.call(track.children || []);
            }

            function getStep(){
                var items = getItems();
                var visibleCount = resolveVisibleCount();
                if (!items.length) {
                    return viewport.clientWidth || 0;
                }

                var itemWidth = items[0].getBoundingClientRect().width;
                if (!itemWidth) {
                    return viewport.clientWidth || 0;
                }

                var gap = computeGap(track);
                var effectiveCount = Math.min(visibleCount, items.length);
                return (itemWidth * effectiveCount) + (gap * Math.max(0, effectiveCount - 1));
            }

            function handleNav(direction){
                var step = getStep();
                var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
                if (step <= 0 || maxScroll <= 0) {
                    return;
                }

                var target = viewport.scrollLeft + (step * direction);
                if (target < 0) {
                    target = 0;
                } else if (target > maxScroll) {
                    target = maxScroll;
                }

                viewport.scrollTo({ left: target, behavior: "smooth" });
                window.requestAnimationFrame(updateNavState);
            }

            function updateNavState(){
                if (!prevBtn || !nextBtn) {
                    return;
                }
                var maxScroll = Math.max(0, viewport.scrollWidth - viewport.clientWidth);
                var atStart = viewport.scrollLeft <= 1;
                var atEnd = viewport.scrollLeft >= (maxScroll - 1);
                prevBtn.disabled = atStart;
                nextBtn.disabled = atEnd;
            }

            if (prevBtn) {
                prevBtn.addEventListener("click", function(){
                    handleNav(-1);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener("click", function(){
                    handleNav(1);
                });
            }

            viewport.addEventListener("scroll", function(){
                window.requestAnimationFrame(updateNavState);
            });

            window.addEventListener("resize", function(){
                window.requestAnimationFrame(updateNavState);
            });

            updateNavState();
        });
    }

    if (document.readyState !== "loading") {
        initTripSliders();
    } else {
        document.addEventListener("DOMContentLoaded", initTripSliders);
    }

    document.addEventListener("manaslu-trip-types:init", initTripSliders);
})();
</script>';
    }

    static $instance_counter = 0;
    $instance_counter++;
    $slider_id = 'manaslu-trip-types-' . $instance_counter;

    $show_nav = count($trip_terms) > 1;

    $html  = $style_block . $script_block;
    $html .= '<div class="' . esc_attr(implode(' ', array_unique($classes))) . '" data-slider-id="' . esc_attr($slider_id) . '">';

    if ($show_nav) {
        $html .= '<div class="manaslu-trip-types__nav" aria-hidden="true">';
        $html .= '<button type="button" class="manaslu-trip-types__nav-btn manaslu-trip-types__nav-btn--prev" data-dir="prev"><span class="manaslu-trip-types__nav-icon" aria-hidden="true">&lsaquo;</span><span class="screen-reader-text">' . esc_html__('Anterior', 'manaslu') . '</span></button>';
        $html .= '<button type="button" class="manaslu-trip-types__nav-btn manaslu-trip-types__nav-btn--next" data-dir="next"><span class="manaslu-trip-types__nav-icon" aria-hidden="true">&rsaquo;</span><span class="screen-reader-text">' . esc_html__('Siguiente', 'manaslu') . '</span></button>';
        $html .= '</div>';
    }

    $html .= '<div class="manaslu-trip-types__viewport"><div class="manaslu-trip-types__track">';

    foreach ($trip_terms as $term) {
        $term_link = get_term_link($term, 'product_cat');
        if (is_wp_error($term_link)) {
            continue;
        }

        $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'large') : '';

        if (!$thumbnail_url) {
            if (function_exists('wc_placeholder_img_src')) {
                $thumbnail_url = wc_placeholder_img_src('woocommerce_single');
            } else {
                $thumbnail_url = '';
            }
        }

        $description = '';
        if (!empty($term->description)) {
            $description = trim(wp_strip_all_tags($term->description));
        }

        $style_attr = $thumbnail_url ? ' style="background-image:url(' . esc_url($thumbnail_url) . ');"' : '';

        $html .= '<a class="manaslu-trip-types__item" href="' . esc_url($term_link) . '"' . $style_attr . '>';
        $html .= '<span class="manaslu-trip-types__content">';
        $html .= '<span class="manaslu-trip-types__title">' . esc_html($term->name) . '</span>';

        if (!empty($description)) {
            $html .= '<span class="manaslu-trip-types__excerpt">' . esc_html($description) . '</span>';
        }

        $html .= '</span>';
        $html .= '</a>';
    }

    $html .= '</div></div>';
    $html .= '</div>';

    return $html;
}

add_shortcode('manaslu_trip_types', 'manaslu_render_trip_types_shortcode');
