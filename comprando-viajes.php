<?php
/**
 * Plugin Name: Manaslu - Comprando Viaje Router
 * Description: Página única /comprando-viaje/ renderizada con contexto de producto (por ID o slug) para usar Elementor + ACF del producto.
 * Author: Manaslu Adventures
 * Version: 1.0.3
 */

/* ============================================================
 *  1) Query vars y reglas de reescritura (ID y SLUG)
 * ============================================================ */
add_filter('query_vars', function($vars){
    foreach (['pid','pname'] as $v) {
        if (!in_array($v, $vars, true)) $vars[] = $v;
    }
    return $vars;
});

add_action('init', function(){
    // /comprando-viaje/{ID}/
    add_rewrite_rule('^comprando-viaje/([0-9]+)/?$', 'index.php?pagename=comprando-viaje&pid=$matches[1]', 'top');
    // /comprando-viaje/{slug-producto}/
    add_rewrite_rule('^comprando-viaje/([^/]+)/?$', 'index.php?pagename=comprando-viaje&pname=$matches[1]', 'top');
});

/* ============================================================
 *  2) Resolver ID de producto desde la URL
 * ============================================================ */
function cv_resolve_product_id_from_request() {
    // ?pid= o regla por ID
    $pid = absint( get_query_var('pid') );
    if ($pid) return $pid;

    // regla por slug del producto
    $pname = get_query_var('pname');
    if ($pname) {
        $p = get_page_by_path( sanitize_title_for_query( $pname ), OBJECT, 'product' );
        if ($p) return (int) $p->ID;
    }

    // respaldo: query param legacy
    if (isset($_GET['pid'])) return absint($_GET['pid']);

    // respaldo final: si estamos en single de producto
    if (is_singular('product')) return get_the_ID();

    return 0;
}

/* ============================================================
 *  3) Evitar redirecciones y 404 en /comprando-viaje/*
 * ============================================================ */
// Bloquear redirect canónico (WP/SEO) solo en esta ruta
add_filter('redirect_canonical', function($redirect_url, $requested_url){
    if (is_string($requested_url) && strpos($requested_url, '/comprando-viaje/') !== false) {
        return false;
    }
    return $redirect_url;
}, 10, 2);

// Prevenir 404 antes de tiempo cuando pasamos pid/pname
add_filter('pre_handle_404', function($preempt, $wp_query){
    $is_target = !empty($wp_query->query['pagename']) && $wp_query->query['pagename'] === 'comprando-viaje';
    if ($is_target && (get_query_var('pid') || get_query_var('pname'))) {
        $wp_query->is_404 = false;
        status_header(200);
        return true;
    }
    return $preempt;
}, 10, 2);

/* ============================================================
 *  4) Renderizar la PÁGINA "comprando-viaje" con CONTEXTO de PRODUCTO
 *     y forzar ACF a leer SIEMPRE del PRODUCTO (no de la página)
 * ============================================================ */
add_filter('the_content', function($content) {
    if (!is_page()) return $content;

    // La página debe llamarse exactamente "comprando-viaje"
    $page_obj = get_page_by_path('comprando-viaje');
    if (!$page_obj || get_queried_object_id() !== (int)$page_obj->ID) return $content;

    // Resolver producto
    $product_id = cv_resolve_product_id_from_request();
    if (!$product_id) {
        return '<p style="opacity:.8">'.esc_html__('No se encontró el producto.', 'your-textdomain').'</p>';
    }
    $product_post = get_post($product_id);
    if (!$product_post || $product_post->post_type !== 'product') {
        return '<p style="opacity:.8">'.esc_html__('Producto inválido.', 'your-textdomain').'</p>';
    }

    // Guardar contexto
    global $post, $product;
    $orig_post    = $post;
    $orig_product = isset($product) ? $product : null;

    // Cambiar contexto a PRODUCTO (para que Elementor/ACF lean del producto)
    $post = $product_post;
    setup_postdata($post);
    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;

    $page_id = $page_obj->ID;

    // --- FIX ACF: forzar que cualquier get_field() en esta página apunte al PRODUCTO ---
    $acf_override = function($post_id) use ($product_id, $page_id) {
        // Si ACF intenta leer de la página, o no trae un ID claro, damos el del producto.
        if ($post_id === $page_id || (is_numeric($post_id) && (int)$post_id === (int)$page_id) || $post_id === 0 || $post_id === null || $post_id === '') {
            return $product_id;
        }
        // No tocar otras fuentes (ej. 'option', terms, users, etc.)
        return $post_id;
    };
    if (function_exists('acf')) {
        add_filter('acf/pre_load_post_id', $acf_override, 10, 1);
    }

    // Render preferente con Elementor (lee ACF/Meta del producto por el override)
    $output = '';
    if (defined('ELEMENTOR_VERSION') && class_exists('\Elementor\Plugin')) {
        $output = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($page_id);
    }

    // Si no hay Elementor o no devolvió nada, render crudo sin re-aplicar the_content (evitar recursión)
    if ($output === '' || $output === null) {
        $raw = get_post_field('post_content', $page_id);
        if (function_exists('do_blocks')) {
            $output = do_blocks($raw);
        } else {
            $output = $raw;
        }
        $output = wpautop($output);
    }

    // Quitar override de ACF y restaurar contexto
    if (function_exists('acf')) {
        remove_filter('acf/pre_load_post_id', $acf_override, 10);
    }
    wp_reset_postdata();
    $post    = $orig_post;
    $product = $orig_product;

    return $output;
}, 20);

/* ============================================================
 *  5) Shortcode del enlace a "Comprando viaje"
 *     - Prefiere URL bonita /comprando-viaje/{slug-producto}/
 *     - Fallback a ?pid={ID} si no hay estructura de enlaces bonitos
 * ============================================================ */
if (shortcode_exists('comprando_viaje_url')) {
    remove_shortcode('comprando_viaje_url');
}

add_shortcode('comprando_viaje_url', function ($atts = []) {
    $atts = shortcode_atts(['id' => ''], $atts, 'comprando_viaje_url');

    // Resolver producto
    $product_id = $atts['id'] !== '' ? absint($atts['id']) : (is_singular('product') ? get_the_ID() : 0);
    if (!$product_id) return '#';
    $product = get_post($product_id);
    if (!$product || $product->post_type !== 'product') return '#';

    // Localizar la página "comprando-viaje" (opcional: mapear idioma si usas WPML)
    $slug = 'comprando-viaje';
    $page = get_page_by_path($slug);
    if ($page && function_exists('wpml_object_id')) {
        $translated_id = apply_filters('wpml_object_id', $page->ID, 'page', true);
        if ($translated_id) $page = get_post($translated_id);
    }
    $base = $page ? get_permalink($page->ID) : home_url('/' . $slug . '/');

    // Preferir pretty URL por SLUG de producto
    $pretty = trailingslashit($base . $product->post_name);

    // Si los enlaces permanentes están en "simple", usar ?pid
    global $wp_rewrite;
    if (empty($wp_rewrite->permalink_structure)) {
        $sep = (strpos($base, '?') === false) ? '?' : '&';
        return esc_url($base . $sep . 'pid=' . $product_id);
    }

    return esc_url($pretty);
});