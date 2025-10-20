<?php
/**
 * Plugin Name: Manaslu - Descuentos por Personas (MU)
 * Description: Repetidor de rangos (Desde/Hasta/% desc) en pestaña "Descuentos" del producto Viaje. Aplica un descuento sobre el total de extras en categoría "Personas" según la cantidad (Adultos + Niños).
 * Version: 1.0.0
 * Author: Manaslu Adventures
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
 * Helpers
 * ============================================================ */
if (!function_exists('mv_is_personas_extra')){
    function mv_is_personas_extra($extra_id){
        $terms = get_the_terms($extra_id, 'extra_category');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                if ($t->slug === 'personas' || strtolower($t->name) === 'personas') {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('mv_pax_debug_log')){
    function mv_pax_debug_log($label, $data = null){
        if (!defined('MV_DEBUG_PAX') || !MV_DEBUG_PAX) return;
        $message = $data;
        if (is_object($message) || is_array($message)) {
            $message = wp_json_encode($message);
        } elseif (!is_scalar($message)) {
            $message = print_r($message, true);
        }
        $line = '[mv_pax] '.$label.($message !== null ? ': '.$message : '');
        if (function_exists('wc_get_logger')) {
            try {
                wc_get_logger()->info($line, ['source' => 'mv_pax']);
            } catch (Exception $e) {
                // fall back to error_log below
            }
        }
        error_log($line);
    }
}

if (!function_exists('mv_is_viaje_product')){
    function mv_is_viaje_product($product){
        if (!$product || !is_object($product)) return false;
        if (method_exists($product, 'get_type')) return $product->get_type() === 'viaje';
        return false;
    }
}

/* ============================================================
 * 1) Pestaña "Descuentos" en el editor de producto
 * ============================================================ */
add_filter('woocommerce_product_data_tabs', function($tabs){
    $tabs['mv_descuentos'] = [
        'label'    => __('Descuentos', 'manaslu'),
        'target'   => 'mv_descuentos_data',
        'class'    => ['show_if_viaje'],
        'priority' => 24,
    ];
    return $tabs;
}, 30);

add_action('woocommerce_product_data_panels', function(){
    global $post;
    if (!$post) return;

    $pid = $post->ID;
    $rangos = get_post_meta($pid, '_mv_descuentos_personas', true);
    $rangos = is_array($rangos) ? $rangos : [];

    if (empty($rangos)) {
        $rangos[] = ['desde'=>'', 'hasta'=>'', 'pct'=>''];
    }
    ?>
    <div id="mv_descuentos_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <?php wp_nonce_field('mv_descuentos_save','mv_descuentos_nonce'); ?>
            <p><strong><?php esc_html_e('Descuentos por cantidad de personas', 'manaslu'); ?></strong></p>
            <p class="description" style="margin-top:-4px;">
                <?php esc_html_e('Define rangos por número total de personas (Adultos + Niños). El descuento se aplica al subtotal de extras en la categoría "Personas".', 'manaslu'); ?>
            </p>

            <table class="widefat striped" id="mv-desc-table">
                <thead>
                    <tr>
                        <th style="width:140px;"><?php esc_html_e('Desde (PAX)','manaslu');?></th>
                        <th style="width:140px;"><?php esc_html_e('Hasta (PAX)','manaslu');?></th>
                        <th style="width:180px;"><?php esc_html_e('% Descuento','manaslu');?></th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rangos as $i=>$r): ?>
                    <tr class="mv-desc-row">
                        <td><input type="number" step="1" min="1" name="mv_desc[desde][]" value="<?php echo esc_attr($r['desde']);?>" placeholder="4"></td>
                        <td><input type="number" step="1" min="1" name="mv_desc[hasta][]" value="<?php echo esc_attr($r['hasta']);?>" placeholder="5"></td>
                        <td><input type="number" step="0.01" min="0" max="100" name="mv_desc[pct][]" value="<?php echo esc_attr($r['pct']);?>" placeholder="10"></td>
                        <td><button type="button" class="button mv-desc-remove">×</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin:8px 0 0 0;">
                <button type="button" class="button button-secondary" id="mv-desc-add"><?php esc_html_e('Añadir rango','manaslu'); ?></button>
            </p>
            <p class="description" style="margin-top:8px;">
                <?php esc_html_e('Deja "Hasta" vacío para rango abierto (p.ej., desde 10 en adelante). Los rangos se evalúan de menor a mayor y se usa el primero que coincida.', 'manaslu'); ?>
            </p>
        </div>
    </div>
    <script>
    jQuery(function($){
        const $tbody = $('#mv-desc-table tbody');
        $('#mv-desc-add').on('click', function(){
            $tbody.append(`
            <tr class="mv-desc-row">
                <td><input type="number" step="1" min="1" name="mv_desc[desde][]" value="" placeholder="4"></td>
                <td><input type="number" step="1" min="1" name="mv_desc[hasta][]" value="" placeholder="5"></td>
                <td><input type="number" step="0.01" min="0" max="100" name="mv_desc[pct][]" value="" placeholder="10"></td>
                <td><button type="button" class="button mv-desc-remove">×</button></td>
            </tr>`);
        });
        $tbody.on('click', '.mv-desc-remove', function(){ $(this).closest('tr').remove(); });
    });
    </script>
    <?php
});

add_action('woocommerce_admin_process_product_object', function($product){
    // Permitir guardado si el panel envió datos, aunque Woo aún no refleje el tipo "viaje"
    $has_post = (!empty($_POST['mv_desc']) && is_array($_POST['mv_desc']));
    $is_viaje = (function_exists('mv_is_viaje_product') && mv_is_viaje_product($product));
    if (!$is_viaje) {
        // Fallback por POST (según selector de tipo de producto en el formulario)
        $is_viaje = (isset($_POST['product-type']) && $_POST['product-type'] === 'viaje')
                 || (isset($_POST['product_type']) && $_POST['product_type'] === 'viaje');
    }
    if (!$is_viaje && !$has_post) return;

    $nonce_ok = (!empty($_POST['mv_descuentos_nonce']) && wp_verify_nonce(wp_unslash($_POST['mv_descuentos_nonce']), 'mv_descuentos_save'));
    if (!$nonce_ok && !$has_post) return;

    $rangos = [];
    if (!empty($_POST['mv_desc']) && is_array($_POST['mv_desc'])){
        $raw = wp_unslash($_POST['mv_desc']);
        $D = isset($raw['desde']) ? (array)$raw['desde'] : [];
        $H = isset($raw['hasta']) ? (array)$raw['hasta'] : [];
        $P = isset($raw['pct'])   ? (array)$raw['pct']   : [];
        $n = max(count($D), count($H), count($P));
        for ($i=0;$i<$n;$i++){
            $desde = isset($D[$i]) && $D[$i] !== '' ? max(1, absint($D[$i])) : '';
            $hasta = isset($H[$i]) && $H[$i] !== '' ? max(1, absint($H[$i])) : '';
            $pct   = isset($P[$i]) && $P[$i] !== '' ? floatval($P[$i]) : '';
            if ($desde === '' && $hasta === '' && $pct === '') continue;
            $rangos[] = ['desde'=>$desde, 'hasta'=>$hasta, 'pct'=>$pct];
        }
    }

    // Ordenar por "desde" asc
    if (!empty($rangos)){
        usort($rangos, function($a,$b){
            $ad = $a['desde'] === '' ? PHP_INT_MAX : (int)$a['desde'];
            $bd = $b['desde'] === '' ? PHP_INT_MAX : (int)$b['desde'];
            return $ad <=> $bd;
        });
        update_post_meta($product->get_id(), '_mv_descuentos_personas', $rangos);
    } else {
        delete_post_meta($product->get_id(), '_mv_descuentos_personas');
    }
}, 999);

/* ============================================================
 * 2) Aplicar descuento en cálculo de totales
 * ============================================================ */
// Aplicar descuento por Personas ANTES de cupones, ajustando el precio de la línea
add_action('woocommerce_before_calculate_totals', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!$cart || !is_object($cart)) return;

    foreach ($cart->get_cart() as $key => $item){
        if (empty($item['data']) || !is_object($item['data'])) continue;
        $product = $item['data'];

        $cart->cart_contents[$key]['mv_pax_discount'] = null;
        unset($cart->cart_contents[$key]['mv_pax_debug']);

        $is_viaje = false;
        if (function_exists('mv_is_viaje_product') && mv_is_viaje_product($product)){
            $is_viaje = true;
        } else {
            $ptype = method_exists($product,'get_type') ? $product->get_type() : '';
            $is_viaje = ($ptype === 'viaje');
        }
        if (!$is_viaje) {
            if (!empty($item['viaje_fecha']) || !empty($item['viaje_extras']) || !empty($item['manaslu_extras']) || !empty($item['viaje_personas'])) {
                $is_viaje = true;
            }
        }
        if (!$is_viaje) continue;

        $pid = (int)$item['product_id'];
        $rangos = get_post_meta($pid, '_mv_descuentos_personas', true);
        if (!is_array($rangos) || empty($rangos)) continue;

        $pax_count  = 0;
        $personas_amount = 0.0;
        $cart_item_ref = $cart->cart_contents[$key] ?? $item;
        mv_pax_debug_log('cart_item_raw', [
            'key'        => $key,
            'product_id' => $cart_item_ref['product_id'] ?? null,
            'quantity'   => $cart_item_ref['quantity'] ?? null,
            'manaslu_extras' => $cart_item_ref['manaslu_extras'] ?? null,
            'viaje_extras'   => $cart_item_ref['viaje_extras'] ?? null,
        ]);
        $cart_product_id = isset($cart_item_ref['product_id']) ? (int)$cart_item_ref['product_id'] : 0;
        $cart_date_idx   = isset($cart_item_ref['viaje_fecha']['idx']) ? (int)$cart_item_ref['viaje_fecha']['idx'] : -1;
        $sources = [];
        if (!empty($cart_item_ref['manaslu_extras']) && is_array($cart_item_ref['manaslu_extras'])) {
            $sources[] = $cart_item_ref['manaslu_extras'];
        }
        if (!empty($cart_item_ref['viaje_extras']) && is_array($cart_item_ref['viaje_extras'])) {
            $sources[] = $cart_item_ref['viaje_extras'];
        }
        mv_pax_debug_log('sources_count', ['sources' => count($sources)]);

        $seen_person_rows = [];
        foreach ($sources as $arr){
            foreach ($arr as $x){
                $eid = 0;
                if (isset($x['extra_id'])) $eid = (int)$x['extra_id'];
                elseif (isset($x['id']))   $eid = (int)$x['id'];

                $cat_hint = isset($x['cat']) ? (string)$x['cat'] : '';
                $is_persona = ($cat_hint === 'personas');
                if (!$is_persona && $eid > 0 && function_exists('mv_is_personas_extra')) {
                    $is_persona = mv_is_personas_extra($eid);
                }
                if (!$is_persona) continue;

                $unique_token = $eid > 0 ? 'id_'.$eid : 'row_'.md5(serialize($x));
                if (isset($seen_person_rows[$unique_token])) continue;
                $seen_person_rows[$unique_token] = true;

                $qty_raw = isset($x['qty']) ? (int)$x['qty'] : 0;
                $included_raw = isset($x['included']) ? (int)$x['included'] : 0;
                $count_units = max($qty_raw, $included_raw);
                $charge_units = max(0, $qty_raw - max(0, $included_raw));

                $unit = isset($x['price']) ? (float)$x['price'] : 0.0;

                if ($unit <= 0 && function_exists('mv_product_extra_price')){
                    if ($cart_date_idx >= 0 && function_exists('mv_product_extra_price_for_idx')){
                        $unit = (float) mv_product_extra_price_for_idx($cart_product_id, $eid, $cart_date_idx);
                    } else {
                        $unit = (float) mv_product_extra_price($cart_product_id, $eid);
                    }
                }

                if ($count_units > 0) {
                    $pax_count += $count_units;
                    $personas_amount += ($unit * $count_units);
                }
            }
        }
        mv_pax_debug_log('after_sources', [
            'product_id'       => $pid,
            'pax_count'        => $pax_count,
            'personas_amount'  => $personas_amount,
            'cart_quantity'    => $item['quantity'] ?? null,
            'ranges'           => $rangos,
        ]);

        if ($pax_count <= 0) {
            $candidates = ['viaje_personas','personas','pax','mv_personas'];
            foreach ($candidates as $ck){
                if (!empty($item[$ck]) && is_array($item[$ck])){
                    $agg = $item[$ck];
                    $a_keys = ['adultos','adult','adults'];
                    $k_keys = ['niños','ninos','children','kids','child','menores'];
                    foreach ($a_keys as $ak){ if (isset($agg[$ak])) $pax_count += (int)$agg[$ak]; }
                    foreach ($k_keys as $kk){ if (isset($agg[$kk])) $pax_count += (int)$agg[$kk]; }
                }
            }
        }

        if ($pax_count <= 0) continue;

        $pct = 0.0;
        foreach ($rangos as $r){
            $desde = ($r['desde'] !== '' ? (int)$r['desde'] : 1);
            $hasta = ($r['hasta'] !== '' ? (int)$r['hasta'] : PHP_INT_MAX);
            if ($pax_count >= $desde && $pax_count <= $hasta){
                $pct = isset($r['pct']) ? (float)$r['pct'] : 0.0;
                break;
            }
        }
        if ($pct <= 0) continue;

        $qty = isset($item['quantity']) ? max(1,(int)$item['quantity']) : 1;
        $base_line = ((float)$product->get_price() * $qty);
        $base_for_discount = ($personas_amount > 0) ? $personas_amount : $base_line;
        $discount = $base_for_discount * ($pct / 100.0);

        $cart->cart_contents[$key]['mv_pax_discount'] = [
            'count'          => $pax_count,
            'percent'        => $pct,
            'amount'         => $discount,
            'unit_reduction' => $qty > 0 ? ($discount / $qty) : 0,
        ];
        $cart->cart_contents[$key]['mv_pax_debug'] = [
            'price_before'    => (float)$product->get_price(),
            'personas_amount' => $personas_amount,
            'discount'        => $discount,
            'new_unit_price'  => max(0.0, (float)$product->get_price() - ($qty > 0 ? ($discount / $qty) : 0)),
        ];

        mv_pax_debug_log('after_set_price', [
            'new_price'      => $product->get_price(),
            'discount_amount'=> $discount,
            'cart_totals'    => method_exists($cart, 'get_totals') ? $cart->get_totals() : null,
        ]);
    }
}, 60);

add_action('woocommerce_cart_calculate_fees', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!$cart || !is_object($cart)) return;

    $total_discount = 0.0;
    foreach ($cart->get_cart() as $item){
        if (!empty($item['mv_pax_discount']) && is_array($item['mv_pax_discount'])){
            $amount = isset($item['mv_pax_discount']['amount']) ? (float)$item['mv_pax_discount']['amount'] : 0.0;
            if ($amount > 0) $total_discount += $amount;
        }
    }

    $fees_api = $cart->fees_api();
    foreach ($fees_api->get_fees() as $fee_key => $fee_obj) {
        if (method_exists($fee_obj, 'get_id') && $fee_obj->get_id() === 'mv_pax_discount_fee') {
            unset($fees_api->fees[$fee_key]);
        } elseif (isset($fee_obj->name) && $fee_obj->name === __('Descuento por Personas', 'manaslu')) {
            unset($fees_api->fees[$fee_key]);
        }
    }

    if ($total_discount > 0) {
        if (class_exists('WC_Cart_Fee')) {
            $fee = new WC_Cart_Fee();
            $fee->set_id('mv_pax_discount_fee');
            $fee->set_name(__('Descuento por Personas', 'manaslu'));
            $fee->set_amount(-$total_discount);
            $fee->set_taxable(false);
            $fees_api->add_fee($fee);
        } else {
            // Compatibilidad con versiones antiguas
            $cart->add_fee(__('Descuento por Personas', 'manaslu'), -$total_discount, false);
        }
    }
}, 20);

// Mostrar detalle en el carrito bajo la línea del viaje
add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    if (!empty($cart_item['mv_pax_discount']) && is_array($cart_item['mv_pax_discount'])){
        $d = $cart_item['mv_pax_discount'];
        $label = sprintf(__('Descuento por Personas (%d pax, %s%%)', 'manaslu'), (int)$d['count'], wc_clean($d['percent']));
        $value = wc_price( (float)$d['amount'] * -1 ); // mostrar como negativo
        $item_data[] = ['name' => $label, 'value' => $value];
    }
    return $item_data;
}, 10, 2);

// Guardar meta en el pedido
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order){
    if (!empty($values['mv_pax_discount'])){
        $d = $values['mv_pax_discount'];
        $item->add_meta_data('Grupo pax', (int)$d['count']);
        $item->add_meta_data('Descuento grupo %', (float)$d['percent']);
        $item->add_meta_data('Descuento grupo monto', wc_format_decimal((float)$d['amount']));
    }
}, 10, 4);
?>
