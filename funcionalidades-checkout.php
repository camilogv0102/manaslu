<?php
/**
 * Plugin Name: Manaslu - Checkout Rules (MU)
 * Description: Restricciones específicas del flujo de compra: solo un viaje por carrito y limpieza del carrito al abandonar la página dinámica.
 * Version: 1.0.0
 * Author: Manaslu Adventures
 */

if (!defined('ABSPATH')) exit;

/**
 * Detecta si la solicitud actual corresponde a la página dinámica "comprando-viajes".
 *
 * @return bool
 */
function mv_checkout_is_comprando_page(){
    $slugs = ['comprando-viaje', 'comprando-viajes'];
    if (function_exists('is_page')) {
        foreach ($slugs as $slug) {
            if (is_page($slug)) return true;
        }
    }
    if (!empty($_SERVER['REQUEST_URI'])) {
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
        foreach ($slugs as $slug) {
            if (strpos($request_uri, $slug) !== false) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Mantener solo el producto más reciente en el carrito y forzar cantidad 1.
 */
add_action('woocommerce_add_to_cart', function($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data){
    if (!function_exists('WC')) return;
    $cart = WC()->cart;
    if (!$cart) return;

    foreach ($cart->get_cart() as $key => $item) {
        if ($key === $cart_item_key) continue;
        $cart->remove_cart_item($key);
    }

    $cart_item = $cart->get_cart_item($cart_item_key);
    if (is_array($cart_item) && (!isset($cart_item['quantity']) || (int)$cart_item['quantity'] !== 1)) {
        $cart->set_quantity($cart_item_key, 1, false);
    }

    $cart->calculate_totals();
}, 10, 6);

/**
 * Vaciar carrito y limpiar sesión cuando se abandona el flujo de compra dinámico.
 */
add_action('template_redirect', function(){
    if (is_admin() || wp_doing_ajax()) return;
    if (!function_exists('WC')) return;

    if (function_exists('wc_load_cart')) {
        wc_load_cart();
    }

    $wc_session = WC()->session;
    $cart = WC()->cart;
    if (!$wc_session || !$cart) return;

    $session_flag = 'mv_on_comprando_flow';

    if (mv_checkout_is_comprando_page()) {
        $wc_session->set($session_flag, 'yes');
        return;
    }

    if (!$wc_session->get($session_flag)) return;

    if (function_exists('is_cart') && is_cart()) return;
    if (function_exists('is_checkout') && is_checkout()) return;
    if (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received'))) return;

    $wc_session->__unset($session_flag);
    $cart->empty_cart();
    $cart->set_session();
    $cart->calculate_totals();
    if (method_exists($wc_session, 'save_data')) {
        $wc_session->save_data();
    }
});
