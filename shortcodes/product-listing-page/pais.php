<?php
/**
 * Shortcode: [manaslu_trip_countries]
 *
 * Renderiza las subcategorías del término "pais" para destacarlas en un slider
 * visual similar a los shortcodes de tipos y estilos de viaje.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Genera el HTML del shortcode que lista las categorías hijas del término
 * "pais".
 *
 * @param array $atts Atributos del shortcode.
 * @return string HTML listo para renderizar o cadena vacía si no aplica.
 */
function manaslu_render_trip_countries_shortcode($atts = [])
{
    $atts = shortcode_atts(
        [
            'parent_slug' => 'pais',
            'limit'       => 0,
            'class'       => '',
            'hide_empty'  => false,
        ],
        $atts,
        'manaslu_trip_countries'
    );

    $parent_term = get_term_by('slug', $atts['parent_slug'], 'product_cat');
    if (!$parent_term || is_wp_error($parent_term)) {
        return '';
    }

    $terms = get_terms(
        [
            'taxonomy'   => 'product_cat',
            'parent'     => $parent_term->term_id,
            'hide_empty' => filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN),
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    usort(
        $terms,
        static function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        }
    );

    $limit = (int) $atts['limit'];
    if ($limit > 0) {
        $terms = array_slice($terms, 0, $limit);
    }

    if (empty($terms)) {
        return '';
    }

    $classes = ['manaslu-trip-countries'];
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
.manaslu-trip-countries{position:relative;--manaslu-trip-gap:24px}
.manaslu-trip-countries__viewport{overflow-x:auto;overflow-y:visible;scrollbar-width:none;-ms-overflow-style:none}
.manaslu-trip-countries__viewport::-webkit-scrollbar{display:none}
.manaslu-trip-countries__track{display:flex;gap:10px;scroll-behavior:smooth}
.manaslu-trip-countries__item{flex:0 0 calc((100% - (var(--manaslu-trip-gap) * 3))/4);position:relative;display:flex;align-items:flex-end;justify-content:flex-start;padding:24px;border-radius:28px;overflow:hidden;min-height:320px;aspect-ratio:1/1;color:#fff;text-decoration:none;background-color:#111;background-position:center;background-size:cover;transition:transform .3s ease}
.manaslu-trip-countries__item::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(8,11,15,0) 45%,rgba(8,11,15,.85) 100%);transition:opacity .3s ease}
.manaslu-trip-countries__item::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 50% 20%,rgba(255,255,255,.1),rgba(255,255,255,0));opacity:0;transition:opacity .3s ease}
.manaslu-trip-countries__item:hover{transform:translateY(-6px);box-shadow:0 22px 40px rgba(8,11,15,.25)}
.manaslu-trip-countries__item:hover::after{opacity:1}
.manaslu-trip-countries__content{position:relative;z-index:1;display:flex;flex-direction:column;gap:8px}
.manaslu-trip-countries__title{font-size:1.125rem;font-weight:600;line-height:1.3;margin:0}
.manaslu-trip-countries__excerpt{font-size:.9375rem;line-height:1.45;opacity:.9;margin:0}
.manaslu-trip-countries__item:focus-visible{outline:3px solid rgba(255,255,255,.65);outline-offset:4px}
.manaslu-trip-countries__nav{position:absolute;inset:0;display:flex;justify-content:space-between;align-items:center;padding:0 12px;pointer-events:none;z-index:2}
.manaslu-trip-countries__nav button{pointer-events:auto;border:0;background:#FB2240;color:#fff;border-radius:999px;width:44px;height:44px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .3s ease,transform .3s ease;font-size:1.75rem;line-height:1}
.manaslu-trip-countries__nav-icon{display:block;line-height:1}
.manaslu-trip-countries__nav button:hover{background:#d91d35;transform:scale(1.05)}
.manaslu-trip-countries__nav button:focus-visible{outline:3px solid rgba(255,255,255,.65);outline-offset:2px}
.manaslu-trip-countries__nav button[disabled]{opacity:.5;cursor:not-allowed}
.manaslu-trip-countries__viewport{padding:8px 0 12px}
.manaslu-trip-countries__dots{display:none;justify-content:center;gap:8px;margin-top:16px}
.manaslu-trip-countries__dot{width:10px;height:10px;border-radius:50%;background:#ddd;opacity:.6;transition:opacity .3s ease,transform .3s ease}
.manaslu-trip-countries__dot[aria-current="true"]{opacity:1;transform:scale(1.2);background:#FB2240}
@media (max-width:1200px){
.manaslu-trip-countries{--manaslu-trip-gap:20px}
.manaslu-trip-countries__item{padding:22px;min-height:280px}
}
@media (max-width:992px){
.manaslu-trip-countries{--manaslu-trip-gap:16px}
.manaslu-trip-countries__item{padding:20px;min-height:260px}
}
@media (max-width:768px){
.manaslu-trip-countries{--manaslu-trip-gap:14px}
.manaslu-trip-countries__item{flex:0 0 calc((100% - (var(--manaslu-trip-gap) * 2))/3);min-height:240px}
.manaslu-trip-countries__nav{display:none}
.manaslu-trip-countries__dots{display:flex}
}
@media (max-width:576px){
.manaslu-trip-countries{--manaslu-trip-gap:12px}
.manaslu-trip-countries__item{flex:0 0 calc((100% - var(--manaslu-trip-gap))/2);min-height:220px;padding:18px}
.manaslu-trip-countries__title{font-size:1rem}
.manaslu-trip-countries__excerpt{font-size:.875rem}
}
@media (max-width:420px){
.manaslu-trip-countries{--manaslu-trip-gap:10px}
.manaslu-trip-countries__item{flex:0 0 100%;min-height:200px}
}
</style>';
    }

    if (!$script_printed) {
        $script_printed = true;
        $script_block   = '<script>
(function(){
    function initTripCountrySliders(){
        var sliders = document.querySelectorAll(".manaslu-trip-countries");
        if (!sliders.length) {
            return;
        }

        sliders.forEach(function(slider){
            if (slider.dataset.manasluSliderInit === "1") {
                return;
            }
            slider.dataset.manasluSliderInit = "1";

            var viewport = slider.querySelector(".manaslu-trip-countries__viewport");
            var track = slider.querySelector(".manaslu-trip-countries__track");
            var items = slider.querySelectorAll(".manaslu-trip-countries__item");
            var prevBtn = slider.querySelector(".manaslu-trip-countries__nav-btn--prev");
            var nextBtn = slider.querySelector(".manaslu-trip-countries__nav-btn--next");
            var dotsContainer = slider.querySelector(".manaslu-trip-countries__dots");
            var sliderId = slider.getAttribute("data-slider-id");

            if (!viewport || !track || !items.length || !sliderId) {
                return;
            }

            function updateDots(){
                if (!dotsContainer) {
                    return;
                }
                dotsContainer.innerHTML = "";
                var itemWidth = items[0].getBoundingClientRect().width;
                var gap = parseFloat(getComputedStyle(track).gap) || 0;
                var viewportWidth = viewport.clientWidth;
                var effectiveWidth = itemWidth + gap;
                var visibleCount = Math.max(1, Math.round((viewportWidth + gap) / effectiveWidth));
                var dotCount = Math.max(1, Math.ceil(items.length / visibleCount));

                for (var i = 0; i < dotCount; i++) {
                    var dot = document.createElement("button");
                    dot.type = "button";
                    dot.className = "manaslu-trip-countries__dot";
                    dot.setAttribute("aria-label", "Ir al grupo " + (i + 1));
                    dot.dataset.index = i.toString();
                    dotsContainer.appendChild(dot);
                }
            }

            function setActiveDot(){
                if (!dotsContainer) {
                    return;
                }
                var dots = dotsContainer.querySelectorAll(".manaslu-trip-countries__dot");
                if (!dots.length) {
                    return;
                }

                var scrollLeft = viewport.scrollLeft;
                var itemWidth = items[0].getBoundingClientRect().width;
                var gap = parseFloat(getComputedStyle(track).gap) || 0;
                var viewportWidth = viewport.clientWidth;
                var effectiveWidth = itemWidth + gap;
                var visibleCount = Math.max(1, Math.round((viewportWidth + gap) / effectiveWidth));
                var groupIndex = Math.round(scrollLeft / (effectiveWidth * visibleCount));

                dots.forEach(function(dot, idx){
                    var isActive = idx === groupIndex;
                    dot.setAttribute("aria-current", isActive ? "true" : "false");
                });
            }

            function handleDotClick(event){
                if (!dotsContainer) {
                    return;
                }
                var target = event.target;
                if (!target || !target.classList.contains("manaslu-trip-countries__dot")) {
                    return;
                }
                var index = parseInt(target.dataset.index, 10);
                if (isNaN(index)) {
                    return;
                }

                var itemWidth = items[0].getBoundingClientRect().width;
                var gap = parseFloat(getComputedStyle(track).gap) || 0;
                var viewportWidth = viewport.clientWidth;
                var effectiveWidth = itemWidth + gap;
                var visibleCount = Math.max(1, Math.round((viewportWidth + gap) / effectiveWidth));

                var scrollTarget = index * visibleCount * effectiveWidth;
                viewport.scrollTo({ left: scrollTarget, behavior: "smooth" });
            }

            if (dotsContainer) {
                dotsContainer.addEventListener("click", handleDotClick);
            }

            function getStep(){
                if (!items.length) {
                    return 0;
                }
                var viewportWidth = viewport.clientWidth;
                var itemWidth = items[0].getBoundingClientRect().width;
                var gap = parseFloat(getComputedStyle(track).gap) || 0;
                if (itemWidth <= 0) {
                    return 0;
                }

                var totalWidth = itemWidth + gap;
                var visibleCount = Math.max(1, Math.round((viewportWidth + gap) / totalWidth));
                var effectiveCount = Math.max(1, Math.min(visibleCount, items.length));
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
                window.requestAnimationFrame(setActiveDot);
            });

            window.addEventListener("resize", function(){
                window.requestAnimationFrame(function(){
                    updateDots();
                    setActiveDot();
                    updateNavState();
                });
            });

            updateDots();
            setActiveDot();
            updateNavState();
        });
    }

    if (document.readyState !== "loading") {
        initTripCountrySliders();
    } else {
        document.addEventListener("DOMContentLoaded", initTripCountrySliders);
    }

    document.addEventListener("manaslu-trip-countries:init", initTripCountrySliders);
})();
</script>';
    }

    static $instance_counter = 0;
    $instance_counter++;
    $slider_id = 'manaslu-trip-countries-' . $instance_counter;

    $show_nav = count($terms) > 1;

    $html  = $style_block . $script_block;
    $html .= '<div class="' . esc_attr(implode(' ', array_unique($classes))) . '" data-slider-id="' . esc_attr($slider_id) . '">';

    if ($show_nav) {
        $html .= '<div class="manaslu-trip-countries__nav" aria-hidden="true">';
        $html .= '<button type="button" class="manaslu-trip-countries__nav-btn manaslu-trip-countries__nav-btn--prev" data-dir="prev"><span class="manaslu-trip-countries__nav-icon" aria-hidden="true">&lsaquo;</span><span class="screen-reader-text">' . esc_html__('Anterior', 'manaslu') . '</span></button>';
        $html .= '<button type="button" class="manaslu-trip-countries__nav-btn manaslu-trip-countries__nav-btn--next" data-dir="next"><span class="manaslu-trip-countries__nav-icon" aria-hidden="true">&rsaquo;</span><span class="screen-reader-text">' . esc_html__('Siguiente', 'manaslu') . '</span></button>';
        $html .= '</div>';
    }

    $html .= '<div class="manaslu-trip-countries__viewport"><div class="manaslu-trip-countries__track">';

    foreach ($terms as $term) {
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

        $html .= '<a class="manaslu-trip-countries__item" href="' . esc_url($term_link) . '"' . $style_attr . '>';
        $html .= '<span class="manaslu-trip-countries__content">';
        $html .= '<span class="manaslu-trip-countries__title">' . esc_html($term->name) . '</span>';

        if (!empty($description)) {
            $html .= '<span class="manaslu-trip-countries__excerpt">' . esc_html($description) . '</span>';
        }

        $html .= '</span>';
        $html .= '</a>';
    }

    $html .= '</div></div>';

    if (count($terms) > 1) {
        $html .= '<div class="manaslu-trip-countries__dots" role="tablist"></div>';
    }

    $html .= '</div>';

    return $html;
}

add_shortcode('manaslu_trip_countries', 'manaslu_render_trip_countries_shortcode');
