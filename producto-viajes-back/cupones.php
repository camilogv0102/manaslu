<?php
/**
 * Manaslu – Cupones por Viaje
 * - Agrega pestaña "Cupones" al editar productos tipo "viaje".
 * - Lista todos los cupones (shop_coupon) con checkbox por cupón para marcarlo como aplicable a ese viaje.
 * - Guarda selección en el meta `_mv_allowed_coupons` del producto.
 * - Valida en carrito/pago que los cupones solo apliquen si el viaje en el carrito tiene permitido ese cupón.
 */

if (!defined('ABSPATH')) exit;

/**
 * Helper: obtener array de IDs de cupones permitidos para un producto
 */
function mv_get_allowed_coupons_for_product($product_id){
    $ids = get_post_meta($product_id, '_mv_allowed_coupons', true);
    if (!is_array($ids)) $ids = [];
    // Normalizar a int y filtrar vacíos
    $ids = array_values(array_filter(array_map('intval', $ids)));
    return $ids;
}

// Devuelve un único ID de cupón permitido (si existe). Mantiene compatibilidad con el array previo.
function mv_get_allowed_coupon_for_product($product_id){
    $single = (int) get_post_meta($product_id, '_mv_allowed_coupon', true);
    if ($single > 0) return $single;
    // fallback por compatibilidad: si hay array viejo, toma el primero
    $arr = mv_get_allowed_coupons_for_product($product_id);
    return $arr ? (int)$arr[0] : 0;
}

/**
 * 1) Tab "Cupones" (solo para productos tipo viaje)
 */
add_filter('woocommerce_product_data_tabs', function($tabs){
    $tabs['mv_viaje_coupons'] = [
        'label'    => __('Cupones', 'manaslu'),
        'target'   => 'mv_viaje_coupons_panel',
        'class'    => ['show_if_viaje'],
        'priority' => 71,
    ];
    return $tabs;
});

add_action('woocommerce_product_data_panels', function(){
    global $post;
    if (!$post) return;

    // Solo pintar el panel; la clase show_if_viaje lo oculta en otros tipos
    $product_id = (int)$post->ID;

    // Traer todos los cupones
    $args = [
        'post_type'      => 'shop_coupon',
        'post_status'    => ['publish'],
        'posts_per_page' => 200, // suficiente para tu caso; subir si necesitas
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ];
    $coupon_ids = get_posts($args);

    $selected_single = mv_get_allowed_coupon_for_product($product_id);
    ?>
    <div id="mv_viaje_coupons_panel" class="panel woocommerce_options_panel hidden">
        <?php wp_nonce_field('mv_viaje_coupons_save', 'mv_viaje_coupons_nonce'); ?>
        <div class="options_group">
            <p class="form-field" style="margin:0 0 8px 0;">
                <strong><?php _e('Asocia cupones a este viaje', 'manaslu'); ?></strong><br/>
                <span class="description"><?php _e('Marca los cupones que serán aplicables específicamente a este viaje. Si no marcas ninguno, ningún cupón será válido para este viaje.', 'manaslu'); ?></span>
            </p>
            <style>
                #mv_viaje_coupons_table th, #mv_viaje_coupons_table td { padding:6px 8px; }
                #mv_viaje_coupons_wrap { max-height: 340px; overflow:auto; border:1px solid #e6e6e6; border-radius:4px; }
                #mv_viaje_coupons_table { width:100%; border-collapse:collapse; }
                #mv_viaje_coupons_table tr:nth-child(odd){ background:#fafafa; }
                #mv_viaje_coupons_table th { text-align:left; background:#f0f0f0; position:sticky; top:0; }
                .mv-coupon-code { font-family: monospace; font-size: 12px; }
            </style>

            <div id="mv_viaje_coupons_wrap">
                <table id="mv_viaje_coupons_table">
                    <thead>
                        <tr>
                            <th style="width:42px; text-align:center;">✔</th>
                            <th><?php _e('Código', 'manaslu'); ?></th>
                            <th><?php _e('Tipo', 'manaslu'); ?></th>
                            <th><?php _e('Importe', 'manaslu'); ?></th>
                            <th><?php _e('Descripción', 'manaslu'); ?></th>
                            <th style="width:150px;">ID</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($coupon_ids){
                        // Opción "Ninguno"
                        echo '<tr>';
                        echo '<td style="text-align:center">'
                            .'<input type="radio" name="mv_allowed_coupon" value="0" '.( $selected_single ? '' : 'checked' ).'>'
                            .'</td>';
                        echo '<td class="mv-coupon-code">—</td>';
                        echo '<td colspan="3">'.esc_html__('Ninguno (no aplicar cupón por defecto)', 'manaslu').'</td>';
                        echo '<td>#0</td>';
                        echo '</tr>';
                        foreach ($coupon_ids as $cid){
                            $c = new WC_Coupon($cid);
                            $code = $c->get_code();
                            $type = $c->get_discount_type();
                            $amount = $c->get_amount();
                            $desc = get_post_field('post_excerpt', $cid);
                            $checked = ((int)$cid === (int)$selected_single) ? 'checked' : '';
                            echo '<tr>';
                            echo '<td style="text-align:center">'
                                .'<input type="radio" name="mv_allowed_coupon" value="'.(int)$cid.'" '.$checked.'>'
                                .'</td>';
                            echo '<td class="mv-coupon-code">'.esc_html($code).'</td>';
                            echo '<td>'.esc_html($type).'</td>';
                            echo '<td>'.esc_html($amount).'</td>';
                            echo '<td>'.esc_html($desc).'</td>';
                            echo '<td>#'.(int)$cid.'</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="6">'.esc_html__('No hay cupones creados aún (Marketing → Cupones).', 'manaslu').'</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
});

/**
 * 2) Guardar selección de cupones por producto (alta prioridad para evitar sobreescrituras)
 */
add_action('woocommerce_admin_process_product_object', function($product){
    if (!current_user_can('edit_post', $product->get_id())) return;

    if (!isset($_POST['mv_viaje_coupons_nonce']) || !wp_verify_nonce(wp_unslash($_POST['mv_viaje_coupons_nonce']), 'mv_viaje_coupons_save')){
        // Si no viene el nonce, no tocar meta (tal vez no es la pantalla)
        return;
    }

    $pid = (int)$product->get_id();
    $selected_single = isset($_POST['mv_allowed_coupon']) ? (int)$_POST['mv_allowed_coupon'] : 0;
    if ($selected_single > 0){
        update_post_meta($pid, '_mv_allowed_coupon', $selected_single);
    } else {
        delete_post_meta($pid, '_mv_allowed_coupon');
    }
    // limpieza de meta antiguo si existía
    delete_post_meta($pid, '_mv_allowed_coupons');
}, 999);

/**
 * 3) Validación: permitir cupones solo si el viaje en el carrito lo tiene habilitado.
 *    - Si el carrito no tiene productos tipo "viaje", no intervenimos.
 *    - Si hay "viaje", el cupón solo es válido si al menos uno de esos viajes tiene el cupón marcado.
 */
add_filter('woocommerce_coupon_is_valid', function($valid, $coupon){
    if (!$valid) return $valid; // si ya es inválido, respetar
    if (!WC()->cart) return $valid;

    $cart = WC()->cart;
    $coupon_id = method_exists($coupon,'get_id') ? (int)$coupon->get_id() : 0;

    $has_viaje = false;
    $allowed_found = false;

    foreach ($cart->get_cart() as $item){
        if (empty($item['data']) || !($item['data'] instanceof WC_Product)) continue;
        /** @var WC_Product $prod */
        $prod = $item['data'];
        if ($prod->get_type() === 'viaje'){
            $has_viaje = true;
            $allowed_id = mv_get_allowed_coupon_for_product($prod->get_id());
            if ($allowed_id > 0 && $coupon_id === (int)$allowed_id){
                $allowed_found = true;
            }
        }
    }

    // Si hay viaje en el carro pero ninguno permite este cupón, invalídalo
    if ($has_viaje && !$allowed_found){
        return false;
    }
    return $valid;
}, 10, 2);

/**
 * 4) Validación por producto (por si algún flujo chequea a nivel de línea)
 */
add_filter('woocommerce_coupon_is_valid_for_product', function($valid, $product, $coupon, $values){
    if (!$valid) return $valid;
    if (!($product instanceof WC_Product)) return $valid;
    if ($product->get_type() !== 'viaje') return $valid;

    $coupon_id = method_exists($coupon,'get_id') ? (int)$coupon->get_id() : 0;
    $allowed_id = mv_get_allowed_coupon_for_product($product->get_id());
    if ($allowed_id <= 0) return false; // ninguno permitido
    return ((int)$coupon_id === (int)$allowed_id);
}, 10, 4);

/**
 * Shortcode: [mv_cupon_viaje pid="123"]
 * - Muestra el cupón seleccionado para ese viaje (o el del producto actual si no se pasa pid)
 * - Botón "Agregar" que aplica el cupón por AJAX y refresca los fragments/resumen
 */
add_shortcode('mv_cupon_viaje', function($atts){
    $atts = shortcode_atts(['pid'=>0, 'class'=>''], $atts, 'mv_cupon_viaje');
    $pid  = absint($atts['pid']);

    // 0) Integración directa con el router /comprando-viaje/
    if (!$pid && function_exists('cv_resolve_product_id_from_request')){
        $pid = (int) cv_resolve_product_id_from_request();
    }
    // 0.1) Leer query vars si el helper no está
    if (!$pid){
        $qpid = function_exists('get_query_var') ? absint(get_query_var('pid')) : 0;
        if ($qpid) $pid = $qpid;
    }
    if (!$pid){
        $pname = function_exists('get_query_var') ? get_query_var('pname') : '';
        if ($pname){
            $p = get_page_by_path( sanitize_title_for_query($pname), OBJECT, 'product');
            if ($p) $pid = (int) $p->ID;
        }
    }

    // 1) Atributo pid tiene prioridad (ya aplicado arriba); si aún no hay, usar fallbacks Woo
    if (!$pid && function_exists('wc_get_product')){
        // 2) Global $product (constructor de plantillas / Elementor / Woo)
        global $product;
        if ($product instanceof WC_Product && $product->get_type() === 'viaje'){
            $pid = (int) $product->get_id();
        }
    }
    if (!$pid && function_exists('wc_get_product')){
        // 3) Objeto consultado
        $qid  = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        if ($qid){
            $prodQ = wc_get_product($qid);
            if ($prodQ && $prodQ->get_type() === 'viaje'){
                $pid = (int) $prodQ->get_id();
            }
        }
    }
    if (!$pid && function_exists('wc_get_product')){
        // 4) Fallback desde el loop clásico
        $prod = wc_get_product(get_the_ID());
        if ($prod && $prod->get_type() === 'viaje'){
            $pid = (int) $prod->get_id();
        }
    }
    if (!$pid && function_exists('WC') && WC()->cart){
        // 5) Último recurso: si el carrito tiene exactamente un viaje, usar ese
        $ids = [];
        foreach (WC()->cart->get_cart() as $it){
            if (!empty($it['data']) && $it['data'] instanceof WC_Product && $it['data']->get_type() === 'viaje'){
                $ids[] = (int) $it['data']->get_id();
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) === 1){
            $pid = (int) $ids[0];
        }
    }
    if (!$pid) return '';

    $cid = mv_get_allowed_coupon_for_product($pid);
    if ($cid <= 0) return '';

    $c = new WC_Coupon($cid);
    if (!$c || !$c->get_id()) return '';

    $code = $c->get_code();
    $desc = get_post_field('post_excerpt', $cid);
    $text = $desc ? $desc : strtoupper($code);

    $nonce = wp_create_nonce('mv_apply_coupon');

    ob_start(); ?>
    <div class="mv-coupon-box <?php echo esc_attr($atts['class']); ?>" data-code="<?php echo esc_attr($code); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
        <div class="mv-coupon-text"><?php echo esc_html($text); ?></div>
        <button type="button" class="button mv-apply-coupon"><?php echo esc_html__('Usar Cupón', 'manaslu'); ?></button>
    </div>
    <script>(function($){
        var $box = $('.mv-coupon-box').last();
        if(!$box.length) return;
        var code  = $box.data('code');
        var nonce = $box.data('nonce');
        $box.on('click', '.mv-apply-coupon', function(){
            var $btn = $(this); $btn.prop('disabled', true);
            $.ajax({
                url: (window.ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>'),
                method: 'POST', dataType: 'json', cache:false,
                data: { action:'mv_apply_coupon', code: code, nonce: nonce }
            }).done(function(res){
                var ok = res && res.success;
                if (ok) {
                    $btn.text('Cupón agregado');
                    $btn.prop('disabled', true).addClass('added');
                    $(document.body).trigger('wc_fragment_refresh');
                    if (window.manasluPing) window.manasluPing();
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'No se pudo aplicar el cupón';
                    window.alert(msg);
                }
            }).always(function(){ $btn.prop('disabled', false); });
        });
    })(jQuery);</script>
    <style>.mv-coupon-box{display:flex;gap:10px;align-items:center}.mv-coupon-text{font-weight:600} .mv-apply-coupon.added{opacity:.75;cursor:default}</style>
    <?php
    return ob_get_clean();
});

add_action('wp_ajax_mv_apply_coupon', 'mv_ajax_apply_coupon');
add_action('wp_ajax_nopriv_mv_apply_coupon', 'mv_ajax_apply_coupon');
function mv_ajax_apply_coupon(){
    nocache_headers();
    if (!check_ajax_referer('mv_apply_coupon', 'nonce', false)){
        wp_send_json_error(['message'=>__('Token inválido. Recarga la página.', 'manaslu')]);
    }
    if (!class_exists('WooCommerce') || !WC()->cart){
        wp_send_json_error(['message'=>__('Carrito no disponible', 'manaslu')]);
    }
    $code = isset($_REQUEST['code']) ? wc_format_coupon_code( sanitize_text_field( wp_unslash($_REQUEST['code']) ) ) : '';
    if (!$code){ wp_send_json_error(['message'=>__('Código vacío', 'manaslu')]); }

    $res = WC()->cart->apply_coupon($code);
    if (is_wp_error($res)){
        wp_send_json_error(['message'=>$res->get_error_message()]);
    }
    WC()->cart->calculate_totals();
    $totals = WC()->cart->get_totals();
    $disc   = isset($totals['discount_total']) ? (float)$totals['discount_total'] : 0.0;
    wp_send_json_success(['message'=>__('Cupón aplicado', 'manaslu'), 'discount'=>wc_price(-$disc)]);
}
?>
