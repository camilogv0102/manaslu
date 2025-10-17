<?php
/**
 * Plugin Name: Manaslu - Viajes (tipo + fechas + AJAX + cupos)
 * Description: Tipo de producto "viaje" con repetidor de fechas, selección AJAX y control de cupos por fecha. Incluye UI para precios de EXTRAS por FECHA.
 * Author: ProCro
 * Version: 1.5.0
 */

if (!defined('ABSPATH')) exit;

/* =========================
 * Helpers internos
 * ========================= */

// Helper: ¿Extra pertenece a categoría "Individual"?
if (!function_exists('mv_is_individual_extra')){
    function mv_is_individual_extra($extra_id){
        $terms = get_the_terms($extra_id, 'extra_category');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                if ($t->slug === 'individual' || strtolower($t->name) === 'individual') {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('mv_arr')) {
    function mv_arr($v){ return is_array($v) ? $v : (is_string($v) ? (json_decode($v, true) ?: []) : []); }
}
if (!function_exists('mv_get_fechas')){
    function mv_get_fechas($pid){ return mv_arr( get_post_meta($pid, '_viaje_fechas', true) ); }
}
if (!function_exists('mv_get_sales_map')){
    function mv_get_sales_map($pid){ $m = get_post_meta($pid, '_viaje_ventas_map', true); return is_array($m)?$m:[]; }
}
if (!function_exists('mv_set_sales_map')){
    function mv_set_sales_map($pid, $map){ update_post_meta($pid, '_viaje_ventas_map', array_map('intval', (array)$map) ); }
}
if (!function_exists('mv_available_for_idx')){
    function mv_available_for_idx($pid, $idx, $row){
        $total = isset($row['cupo_total']) && $row['cupo_total'] !== '' ? max(0, (int)$row['cupo_total']) : 0;
        if ($total === 0) return 0; // si no definieron total, tratamos como 0 para que no mienta
        $sales = mv_get_sales_map($pid);
        $vend  = isset($sales[$idx]) ? max(0, (int)$sales[$idx]) : 0;

        // restar también lo que el usuario tenga ya en su carrito para esa misma fecha
        $en_carrito = 0;
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $ci) {
                if (!empty($ci['product_id']) && (int)$ci['product_id'] === (int)$pid && isset($ci['viaje_fecha']['idx']) && (int)$ci['viaje_fecha']['idx'] === (int)$idx) {
                    $en_carrito += (int) $ci['quantity'];
                }
            }
        }
        $disp = max(0, $total - $vend - $en_carrito);
        return $disp;
    }
}
if (!function_exists('mv_cart_selected_idx')){
    function mv_cart_selected_idx($pid){
        if (!function_exists('WC') || !WC()->cart) return -1;
        foreach (WC()->cart->get_cart() as $ci) {
            if (!empty($ci['product_id']) && (int)$ci['product_id'] === (int)$pid && isset($ci['viaje_fecha']['idx'])) {
                return (int)$ci['viaje_fecha']['idx'];
            }
        }
        return -1;
    }
}
if (!function_exists('mv_fmt_date')){
    function mv_fmt_date($ymd){
        if (!$ymd) return '';
        $t = strtotime($ymd.' 00:00:00');
        if (!$t) return esc_html($ymd);
        $meses = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $dia = date_i18n('d', $t);
        $mes = $meses[(int)date_i18n('n', $t)];
        return strtoupper(sprintf('%s %s', $dia, $mes));
    }
}

/* Helper opcional para detectar “Seguro de viaje” (si el otro plugin no está cargado) */
if (!function_exists('mv_is_seguro_extra')){
    function mv_is_seguro_extra($extra_id){
        $terms = get_the_terms($extra_id, 'extra_category');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                if ($t->slug === 'seguro-de-viaje' || strtolower($t->name) === 'seguro de viaje') return true;
            }
        }
        return false;
    }
}

if (!function_exists('mv_collect_included_extras_for_date')){
    function mv_collect_included_extras_for_date($product_id, $date_idx){
        $map = [];
        $product_id = (int)$product_id;
        $date_idx = (int)$date_idx;
        if ($product_id <= 0 || $date_idx < 0) return $map;

        $by_idx = get_post_meta($product_id, '_viaje_fechas_extras', true);
        if (is_array($by_idx) && isset($by_idx[$date_idx]) && is_array($by_idx[$date_idx])) {
            foreach ($by_idx[$date_idx] as $eid => $row) {
                $eid = (int)$eid;
                if ($eid <= 0) continue;
                if (empty($row['included'])) continue;
                if (function_exists('mv_is_seguro_extra') && mv_is_seguro_extra($eid)) continue;
                $base = (int)$row['included'];
                if ($base <= 0) $base = 1;
                $map[$eid] = max($map[$eid] ?? 0, $base);
            }
        }

        $assigned = get_post_meta($product_id, 'extras_asignados', true);
        $assigned = is_array($assigned) ? array_map('intval', $assigned) : [];
        $inc_prod = get_post_meta($product_id, 'mv_included_extras', true);
        $inc_prod = is_array($inc_prod) ? array_map('intval', $inc_prod) : [];

        if ($assigned && $inc_prod) {
            foreach ($inc_prod as $eid) {
                $eid = (int)$eid;
                if ($eid <= 0) continue;
                if (!in_array($eid, $assigned, true)) continue;
                if ((function_exists('mv_is_seguro_extra') && mv_is_seguro_extra($eid)) ||
                    (function_exists('mv_is_individual_extra') && mv_is_individual_extra($eid))) {
                    continue;
                }
                $map[$eid] = max($map[$eid] ?? 0, 1);
            }
        }

        return $map;
    }
}

if (!function_exists('mv_get_comprando_viaje_url')) {
    function mv_get_comprando_viaje_url($product_id) {
        $product_id = (int) $product_id;
        if (!$product_id) return '';

        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'product') return '';

        $slug = 'comprando-viaje';
        $page = get_page_by_path($slug);
        if ($page && function_exists('wpml_object_id')) {
            $translated_id = apply_filters('wpml_object_id', $page->ID, 'page', true);
            if ($translated_id) {
                $page = get_post($translated_id);
            }
        }

        $base = $page ? get_permalink($page->ID) : home_url('/' . $slug . '/');
        $pretty = trailingslashit($base . $product->post_name);

        global $wp_rewrite;
        if (empty($wp_rewrite->permalink_structure)) {
            $sep = strpos($base, '?') === false ? '?' : '&';
            return esc_url_raw($base . $sep . 'pid=' . $product_id);
        }

        return esc_url_raw($pretty);
    }
}

/* =========================
 * 0) Registrar TIPO viaje
 * ========================= */
add_action('init', function () {
    if (function_exists('wc_get_product_types') && !get_term_by('slug', 'viaje', 'product_type')) {
        wp_insert_term('viaje', 'product_type');
    }
});
add_filter('product_type_selector', function ($types) {
    $types['viaje'] = __('Viaje', 'manaslu');
    return $types;
});
add_filter('woocommerce_product_class', function($classname,$type){ if($type==='viaje') $classname='WC_Product_Viaje'; return $classname;},10,2);
if (!class_exists('WC_Product_Viaje') && class_exists('WC_Product_Simple')) {
    class WC_Product_Viaje extends WC_Product_Simple { public function get_type(){ return 'viaje'; } }
}

/* Fijar tipo al guardar (evita volver a simple) */
add_action('save_post_product', function ($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post',$post_id)) return;
    $posted_type = '';
    if (isset($_POST['product-type'])) $posted_type = sanitize_text_field(wp_unslash($_POST['product-type']));
    if (isset($_POST['product_type'])) $posted_type = sanitize_text_field(wp_unslash($_POST['product_type']));
    if (!$posted_type && isset($_POST['viaje_fechas'])) $posted_type='viaje';
    if ($posted_type==='viaje'){
        if (!term_exists('viaje','product_type')) wp_insert_term('viaje','product_type');
        wp_set_object_terms($post_id,'viaje','product_type',false);
    }
},100,3);

/* =========================
 * 1) Pestaña y panel REPETIDOR
 * ========================= */
add_filter('woocommerce_product_data_tabs', function ($tabs) {
    $tabs['viaje_fecha'] = ['label'=>__('Fecha','manaslu'),'target'=>'viaje_fecha_data','priority'=>9];
    foreach (['general','inventory','shipping','linked_product','attribute','advanced'] as $k) {
        if (isset($tabs[$k])) {
            $cls = isset($tabs[$k]['class']) ? (array)$tabs[$k]['class'] : [];
            $cls[]='show_if_viaje'; $tabs[$k]['class']=array_unique($cls);
        }
    }
    return $tabs;
},50);

add_action('woocommerce_product_data_panels', function () {
    global $post;
    if (!$post) return;
    $pid   = $post->ID;
    $items = mv_get_fechas($pid);
    if (empty($items)) {
        $items[] = ['inicio'=>'','fin'=>'','precio_normal'=>'','precio_rebajado'=>'','concepto'=>'','tipo_grupo'=>'','cupo_total'=>'','cupo_disp'=>'','estado'=>'disponible'];
    }
    $ventas = mv_get_sales_map($pid);

    // --- SOLO “Individual” (y no seguros) ---
    $assigned_extras  = get_post_meta($pid, 'extras_asignados', true);
    $assigned_extras  = is_array($assigned_extras) ? array_map('intval',$assigned_extras) : [];
    $extras_individuales = array_values(array_filter($assigned_extras, function($eid){
        $is_ind = function_exists('mv_is_individual_extra') ? mv_is_individual_extra($eid) : false;
        $is_seg = function_exists('mv_is_seguro_extra')     ? mv_is_seguro_extra($eid)     : false;
        return $is_ind && !$is_seg;
    }));

    // Cargamos lo guardado por fecha (idx => [extra_id => [regular,sale]])
    $extras_by_idx = get_post_meta($pid, '_viaje_fechas_extras', true);
    $extras_by_idx = is_array($extras_by_idx) ? $extras_by_idx : [];

    // Plantilla para filas nuevas
    $tpl_rows = '';
    if (!empty($extras_individuales)) {
        ob_start(); ?>
        <tr class="viaje-fecha-extras" data-idx="__INDEX__">
            <td colspan="10">
                <details open>
                    <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Precios de extras para esta fecha','manaslu'); ?></summary>
                    <table class="widefat striped" style="margin-top:10px">
                        <thead>
                            <tr>
                                <th style="width:50%"><?php esc_html_e('Extra','manaslu'); ?></th>
                                <th style="width:20%"><?php esc_html_e('Precio regular','manaslu'); ?></th>
                                <th style="width:20%"><?php esc_html_e('Precio rebajado','manaslu'); ?></th>
                                <th style="width:10%"><?php esc_html_e('Incluye','manaslu'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($extras_individuales as $eid): ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($eid)); ?></td>
                                <td><input type="number" step="0.01" min="0" style="width:100%" name="viaje_fechas_extras[__INDEX__][<?php echo esc_attr($eid); ?>][regular]" value=""></td>
                                <td><input type="number" step="0.01" min="0" style="width:100%" name="viaje_fechas_extras[__INDEX__][<?php echo esc_attr($eid); ?>][sale]" value=""></td>
                                <td style="text-align:center;">
                                    <input type="checkbox" class="mv-inc" name="viaje_fechas_extras[__INDEX__][<?php echo esc_attr($eid); ?>][included]" value="1">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            </td>
        </tr>
        <?php
        $tpl_rows = trim(ob_get_clean());
    }
    ?>
    <div id="viaje_fecha_data" class="panel woocommerce_options_panel">
        <div class="options_group">
            <?php wp_nonce_field('viaje_fechas_save','viaje_fechas_nonce'); ?>
            <p><strong><?php esc_html_e('Fechas del viaje','manaslu'); ?></strong></p>
            <table class="widefat striped" id="viaje-fechas-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Inicio','manaslu');?></th>
                        <th><?php esc_html_e('Fin','manaslu');?></th>
                        <th><?php esc_html_e('Precio normal','manaslu');?></th>
                        <th><?php esc_html_e('Precio rebajado','manaslu');?></th>
                        <th><?php esc_html_e('Concepto dcto','manaslu');?></th>
                        <th><?php esc_html_e('Tipo grupo','manaslu');?></th>
                        <th><?php esc_html_e('Cupo total','manaslu');?></th>
                        <th><?php esc_html_e('Cupo disp. (auto)','manaslu');?></th>
                        <th><?php esc_html_e('Estado','manaslu');?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $i=>$it):
                    $vend = isset($ventas[$i]) ? (int)$ventas[$i] : 0;
                    $tot  = isset($it['cupo_total']) && $it['cupo_total'] !== '' ? (int)$it['cupo_total'] : 0;
                    $disp = max(0, $tot - $vend);
                ?>
                    <tr class="viaje-fecha-row" data-idx="<?php echo esc_attr($i); ?>">
                        <td><input type="date" name="viaje_fechas[inicio][]" value="<?php echo esc_attr($it['inicio']);?>"></td>
                        <td><input type="date" name="viaje_fechas[fin][]" value="<?php echo esc_attr($it['fin']);?>"></td>
                        <td><input type="number" step="0.01" min="0" name="viaje_fechas[precio_normal][]" value="<?php echo esc_attr($it['precio_normal']);?>"></td>
                        <td><input type="number" step="0.01" min="0" name="viaje_fechas[precio_rebajado][]" value="<?php echo esc_attr($it['precio_rebajado']);?>"></td>
                        <td><input type="text" name="viaje_fechas[concepto][]" value="<?php echo esc_attr($it['concepto']);?>"></td>
                        <td>
                            <select name="viaje_fechas[tipo_grupo][]">
                                <option value="" <?php selected('',$it['tipo_grupo']??'');?>>—</option>
                                <option value="privado"  <?php selected('privado',$it['tipo_grupo']??'');?>><?php esc_html_e('Privado','manaslu');?></option>
                                <option value="publico"  <?php selected('publico',$it['tipo_grupo']??'');?>><?php esc_html_e('Público','manaslu');?></option>
                            </select>
                        </td>
                        <td><input type="number" step="1" min="0" name="viaje_fechas[cupo_total][]" value="<?php echo esc_attr($it['cupo_total']);?>"></td>
                        <td><input type="number" step="1" min="0" value="<?php echo esc_attr($disp);?>" readonly style="background:#f4f4f4"></td>
                        <td>
                            <select name="viaje_fechas[estado][]">
                                <option value="disponible" <?php selected('disponible',$it['estado']??'disponible');?>><?php esc_html_e('Disponible','manaslu');?></option>
                                <option value="agotado"    <?php selected('agotado',$it['estado']??'');?> ><?php esc_html_e('Agotado','manaslu');?></option>
                                <option value="cerrado"    <?php selected('cerrado',$it['estado']??'');?> ><?php esc_html_e('Cerrado','manaslu');?></option>
                            </select>
                        </td>
                        <td><button type="button" class="button viaje-fecha-remove">×</button></td>
                    </tr>

                    <!-- Bloque de precios de EXTRAS por FECHA (solo Individual) -->
                    <tr class="viaje-fecha-extras" data-idx="<?php echo esc_attr($i); ?>">
                        <td colspan="10">
                            <details>
                                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Precios de extras para esta fecha','manaslu'); ?></summary>
                                <?php if (empty($extras_individuales)): ?>
                                    <p style="margin:8px 0;opacity:.8">
                                        <?php esc_html_e('No hay “extras” de la categoría Individual asignados a este producto. Asigna extras en la caja lateral “Extras asignados”.','manaslu'); ?>
                                    </p>
                                <?php else:
                                    $saved_row = isset($extras_by_idx[$i]) && is_array($extras_by_idx[$i]) ? $extras_by_idx[$i] : [];
                                ?>
                                    <table class="widefat striped" style="margin-top:10px">
                                        <thead>
                                            <tr>
                                                <th style="width:50%"><?php esc_html_e('Extra','manaslu'); ?></th>
                                                <th style="width:20%"><?php esc_html_e('Precio regular','manaslu'); ?></th>
                                                <th style="width:20%"><?php esc_html_e('Precio rebajado','manaslu'); ?></th>
                                                <th style="width:10%"><?php esc_html_e('Incluye','manaslu'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($extras_individuales as $eid):
                                            $title = get_the_title($eid);
                                            $reg   = isset($saved_row[$eid]['regular']) ? $saved_row[$eid]['regular'] : '';
                                            $sal   = isset($saved_row[$eid]['sale'])    ? $saved_row[$eid]['sale']    : '';
                                            $inc   = !empty($saved_row[$eid]['included']) ? 1 : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo esc_html($title); ?></td>
                                                <td><input type="number" step="0.01" min="0" style="width:100%" name="viaje_fechas_extras[<?php echo esc_attr($i); ?>][<?php echo esc_attr($eid); ?>][regular]" value="<?php echo esc_attr($reg); ?>"></td>
                                                <td><input type="number" step="0.01" min="0" style="width:100%" name="viaje_fechas_extras[<?php echo esc_attr($i); ?>][<?php echo esc_attr($eid); ?>][sale]" value="<?php echo esc_attr($sal); ?>"></td>
                                                <td style="text-align:center;">
                                                    <input type="checkbox" class="mv-inc" name="viaje_fechas_extras[<?php echo esc_attr($i); ?>][<?php echo esc_attr($eid); ?>][included]" value="1" <?php checked(1, $inc); ?>>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </details>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            <p><button type="button" class="button button-secondary" id="viaje-fecha-add"><?php esc_html_e('Añadir fecha','manaslu');?></button></p>
            <script>
            jQuery(function($){
                const $tbody = $('#viaje-fechas-table tbody');
                let mvFechaIdx = <?php echo (int)count($items); ?>;
                const mvExtrasTpl = <?php echo json_encode($tpl_rows ?: ''); ?>;

                $('#viaje-fecha-add').on('click', function(){
                    $tbody.append(
`<tr class="viaje-fecha-row" data-idx="${mvFechaIdx}">
<td><input type="date" name="viaje_fechas[inicio][]" value=""></td>
<td><input type="date" name="viaje_fechas[fin][]" value=""></td>
<td><input type="number" step="0.01" min="0" name="viaje_fechas[precio_normal][]" value=""></td>
<td><input type="number" step="0.01" min="0" name="viaje_fechas[precio_rebajado][]" value=""></td>
<td><input type="text" name="viaje_fechas[concepto][]" value=""></td>
<td>
  <select name="viaje_fechas[tipo_grupo][]">
    <option value="">—</option>
    <option value="privado"><?php echo esc_js(__('Privado','manaslu'));?></option>
    <option value="publico"><?php echo esc_js(__('Público','manaslu'));?></option>
  </select>
</td>
<td><input type="number" step="1" min="0" name="viaje_fechas[cupo_total][]" value=""></td>
<td><input type="number" step="1" min="0" value="0" readonly style="background:#f4f4f4"></td>
<td>
  <select name="viaje_fechas[estado][]">
    <option value="disponible"><?php echo esc_js(__('Disponible','manaslu'));?></option>
    <option value="agotado"><?php echo esc_js(__('Agotado','manaslu'));?></option>
    <option value="cerrado"><?php echo esc_js(__('Cerrado','manaslu'));?></option>
  </select>
</td>
<td><button type="button" class="button viaje-fecha-remove">×</button></td>
</tr>`
                    );
                    if (mvExtrasTpl) {
                        const html = mvExtrasTpl.replaceAll('__INDEX__', String(mvFechaIdx));
                        $tbody.append(html);
                    }
                    mvFechaIdx++;
                });

                $tbody.on('click','.viaje-fecha-remove',function(){
                    const $row = $(this).closest('tr');
                    const $next = $row.next('.viaje-fecha-extras');
                    $row.remove();
                    if ($next.length) $next.remove();
                });
            });
            </script>
        </div>
    </div>
    <?php
});

/* Guardado del repetidor (almacenamos como ARRAY) */
add_action('woocommerce_admin_process_product_object', function ($product) {
    $is_viaje = ($product->get_type()==='viaje') || isset($_POST['viaje_fechas']);
    if (!$is_viaje) return;

    if ( empty($_POST['viaje_fechas_nonce']) || !wp_verify_nonce( wp_unslash($_POST['viaje_fechas_nonce']), 'viaje_fechas_save') ) {
        return;
    }

    $items=[];
    if (isset($_POST['viaje_fechas']) && is_array($_POST['viaje_fechas'])) {
        $d = wp_unslash($_POST['viaje_fechas']);
        $cols = ['inicio','fin','precio_normal','precio_rebajado','concepto','tipo_grupo','cupo_total','estado'];
        $norm=[];
        foreach($cols as $c){ $norm[$c]=isset($d[$c]) && is_array($d[$c]) ? array_values($d[$c]) : []; }
        $n=0; foreach($norm as $a){ $n=max($n,count($a)); }
        for($i=0;$i<$n;$i++){
            $inicio  = sanitize_text_field($norm['inicio'][$i] ?? '');
            $fin     = sanitize_text_field($norm['fin'][$i] ?? '');
            $p_norm  = isset($norm['precio_normal'][$i])? wc_format_decimal($norm['precio_normal'][$i]) : '';
            $p_sale  = isset($norm['precio_rebajado'][$i])? wc_format_decimal($norm['precio_rebajado'][$i]) : '';
            $concept = sanitize_text_field($norm['concepto'][$i] ?? '');
            $tipo    = sanitize_text_field($norm['tipo_grupo'][$i] ?? '');
            // BUGFIX: usar $norm para cupo_total (no $d) para no desalinear índices
            $total   = isset($norm['cupo_total'][$i]) && $norm['cupo_total'][$i] !== '' ? absint($norm['cupo_total'][$i]) : '';
            $estado  = sanitize_text_field($norm['estado'][$i] ?? 'disponible');

            if ($inicio==='' && $fin==='' && $p_norm==='' && $p_sale==='' && $concept==='' && $tipo==='' && $total==='') continue;

            $items[] = ['inicio'=>$inicio,'fin'=>$fin,'precio_normal'=>$p_norm,'precio_rebajado'=>$p_sale,'concepto'=>$concept,'tipo_grupo'=>$tipo,'cupo_total'=>$total,'estado'=>$estado];
        }
    }
    if (!empty($items)) {
        update_post_meta($product->get_id(), '_viaje_fechas', $items);
        // ajustar precio base con la primera fila
        $f = $items[0];
        $regular = $f['precio_normal']!=='' ? $f['precio_normal'] : '';
        $sale    = ($f['precio_rebajado']!=='') ? $f['precio_rebajado'] : '';
        $product->set_regular_price($regular);
        $product->set_sale_price(($sale!=='' && ($regular==='' || (float)$sale < (float)$regular)) ? $sale : '');
        $product->set_price($product->get_sale_price()!=='' ? $product->get_sale_price() : $product->get_regular_price());
    } else {
        delete_post_meta($product->get_id(), '_viaje_fechas');
    }

    // ======== NUEVO: guardar precios de EXTRAS por FECHA (solo categoría "Individual") ========
    $by_idx = [];
    if (isset($_POST['viaje_fechas_extras']) && is_array($_POST['viaje_fechas_extras'])) {
        $raw = wp_unslash($_POST['viaje_fechas_extras']);
        foreach ($raw as $idx => $emap) {
            $idx = absint($idx);
            if (!is_array($emap)) continue;

            foreach ($emap as $eid => $pp) {
                $eid = (int)$eid;
                if (!is_array($pp)) continue;

                // Verificar que el extra pertenezca a la categoría "Individual"
                $is_individual = false;
                if (function_exists('mv_is_individual_extra')) {
                    $is_individual = mv_is_individual_extra($eid);
                } else {
                    // Fallback por si no existe el helper
                    $terms = get_the_terms($eid, 'extra_category');
                    if ($terms && !is_wp_error($terms)) {
                        foreach ($terms as $t) {
                            if ($t->slug === 'individual' || strtolower($t->name) === 'individual') {
                                $is_individual = true; break;
                            }
                        }
                    }
                }
                if (!$is_individual) continue;

                // Guardar si hay precio o si se marcó "Incluye"
                $r = isset($pp['regular']) ? wc_format_decimal($pp['regular']) : '';
                $s = isset($pp['sale'])    ? wc_format_decimal($pp['sale'])    : '';
                $inc = (isset($pp['included']) && ($pp['included'] === '1' || $pp['included'] === 'on')) ? 1 : 0;
                if ($r !== '' || $s !== '' || $inc) {
                    $by_idx[$idx][$eid] = ['regular'=>$r,'sale'=>$s,'included'=>$inc];
                }
            }
        }
    }
    update_post_meta($product->get_id(), '_viaje_fechas_extras', $by_idx);

},15);

/* Mostrar paneles nativos con viaje */
add_action('admin_footer', function(){
    if ('product'!==get_post_type()) return; ?>
    <script>
    jQuery(function($){
        $('#general_product_data,#inventory_product_data,#shipping_product_data,#linked_product_data,#attribute_product_data,#advanced_product_data,#viaje_fecha_data').addClass('show_if_viaje');
        function toggle(){ $('.show_if_viaje').toggle($('#product_type').val()==='viaje'); }
        $('#product_type').on('change',toggle); toggle();
    });
    </script>
<?php });

/* ==========================================
 * 2) Shortcode tarjetas + AJAX selección
 * ========================================== */
add_filter('query_vars', function($v){ $v[]='pid'; return $v; });

add_shortcode('viaje_fechas', function($atts){
    $a = shortcode_atts(['id'=>0,'class'=>'','mostrar_estado'=>'1'], $atts,'viaje_fechas');

    $pid = (int)$a['id'];
    if (!$pid) $pid = absint(get_query_var('pid')) ?: 0;
    if (!$pid) { global $product; if ($product instanceof WC_Product) $pid=$product->get_id(); }
    if (!$pid) return '';

    $prod   = wc_get_product($pid);
    if (!$prod) return '';

    $fechas = mv_get_fechas($pid);
    if (empty($fechas)) return '';

    $selected = mv_cart_selected_idx($pid);

    $requested_idx = isset($_REQUEST['viaje_fecha_idx']) ? absint($_REQUEST['viaje_fecha_idx']) : -1;
    if ($requested_idx < 0 || !isset($fechas[$requested_idx])) {
        $requested_idx = -1;
    }

    $initial_idx = $selected >= 0 ? $selected : $requested_idx;

    $mode = 'ajax';
    if (function_exists('is_product') && is_product()) {
        $mode = 'redirect';
    }
    // Si estamos en la página comprando-viaje, forzar modo AJAX
    if (get_query_var('pagename') === 'comprando-viaje' || (function_exists('is_page') && is_page('comprando-viaje'))) {
        $mode = 'ajax';
    }

    $go_base = ($mode === 'redirect') ? mv_get_comprando_viaje_url($pid) : '';

    ob_start(); ?>
    <div class="mv-fechas <?php echo esc_attr($a['class']);?>"
         data-pid="<?php echo esc_attr($pid);?>"
         data-mode="<?php echo esc_attr($mode);?>"
         data-go-base="<?php echo esc_attr($go_base);?>"
         data-initial-idx="<?php echo esc_attr($initial_idx >= 0 ? $initial_idx : ''); ?>">
        <?php foreach ($fechas as $i=>$row):
            $regular = isset($row['precio_normal']) ? (float)$row['precio_normal'] : 0;
            $sale    = isset($row['precio_rebajado']) ? (float)$row['precio_rebajado'] : 0;
            $price_base = $regular > 0 ? $regular : ($sale > 0 ? $sale : 0); // se mostrará abajo
            $price_html = $price_base > 0 ? wc_price($price_base) : '';
            $disp    = mv_available_for_idx($pid, $i, $row);
            $cerrado = (isset($row['estado']) && in_array($row['estado'],['agotado','cerrado'],true)) || $disp<=0;
            $is_sel  = ($selected === $i);
            $included_map = mv_collect_included_extras_for_date($pid, $i);
            $included_ids = array_keys($included_map);
            $data_included_attr = esc_attr(implode(',', $included_ids));
        ?>
        <div class="mv-card <?php echo $is_sel?'is-selected':'';?> <?php echo $cerrado?'is-closed':'';?>"
     data-idx="<?php echo esc_attr($i);?>"
     data-included="<?php echo $data_included_attr; ?>"> 
            <?php if (!empty($row['concepto'])): ?>
              <div class="mv-badge"><?php echo esc_html($row['concepto']); ?></div>
            <?php endif; ?>

            <div class="mv-dates">
                <div class="mv-date">
                    <span class="mv-date-label"><?php esc_html_e('DE:','manaslu');?></span>
                    <span class="mv-date-value"><?php echo esc_html( mv_fmt_date($row['inicio'] ?? '') );?></span>
                </div>
                <div class="mv-date">
                    <span class="mv-date-label"><?php esc_html_e('A:','manaslu');?></span>
                    <span class="mv-date-value"><?php echo esc_html( mv_fmt_date($row['fin'] ?? '') );?></span>
                </div>
            </div>

            <div class="mv-price">
                <?php if ($sale > 0 && ($regular == 0 || $sale < $regular)): ?>
                    <div class="mv-price-sale"><?php echo wc_price($sale); ?></div>  <!-- arriba: precio con descuento -->
                <?php endif; ?>
                <?php if ($price_html): ?>
                    <span class="mv-price-amount"><?php echo wp_kses_post($price_html); ?></span> <!-- abajo: precio base -->
                <?php endif; ?>
            </div>

            <div class="mv-stock">
                <?php
                if ($cerrado) {
                    echo '<span class="mv-nocapacity">'.esc_html__('0 PLAZAS','manaslu').'</span>';
                } else {
                    printf('<span class="mv-capacity">%s</span>', esc_html( sprintf(_n('%d PLAZA DISPONIBLE','%d PLAZAS DISPONIBLES',$disp,'manaslu'), $disp) ));
                }
                ?>
            </div>

            <div class="mv-actions">
                <?php if ($cerrado): ?>
                    <button class="mv-btn mv-btn-gray" type="button" disabled><?php esc_html_e('GRUPO LLENO','manaslu');?></button>
                <?php else: ?>
                    <button class="mv-btn <?php echo $is_sel?'mv-btn-green':'mv-btn-red';?> mv-pick" type="button">
                        <?php echo $is_sel?esc_html__('SELECCIONADA ✓','manaslu'):esc_html__('ESCOGER FECHA','manaslu'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="mv-msg" aria-live="polite"></div>
    </div>
    <?php
    return ob_get_clean();
});

/* AJAX: elegir fecha (nopriv también) */
add_action('wp_ajax_mv_pick_date','mv_ajax_pick_date');
add_action('wp_ajax_nopriv_mv_pick_date','mv_ajax_pick_date');
function mv_ajax_pick_date(){
    if (!function_exists('WC')) wp_send_json_error(['message'=>'WooCommerce no disponible']);
    $pid = isset($_POST['pid']) ? absint($_POST['pid']) : 0;
    $idx = isset($_POST['idx']) ? absint($_POST['idx']) : -1;
    $qty = isset($_POST['qty']) ? max(1, absint($_POST['qty'])) : 1;

    $fechas = mv_get_fechas($pid);
    if (!$pid || !isset($fechas[$idx])) wp_send_json_error(['message'=>__('Fecha inválida','manaslu')]);

    // capacidad
    $disp = mv_available_for_idx($pid, $idx, $fechas[$idx]);
    if ($disp <= 0) wp_send_json_error(['message'=>__('Sin cupo para esa fecha','manaslu')]);

    // limpiar cualquier línea del mismo producto con otra fecha
    foreach (WC()->cart->get_cart() as $key=>$ci) {
        if (!empty($ci['product_id']) && (int)$ci['product_id']===$pid && isset($ci['viaje_fecha']['idx'])) {
            WC()->cart->remove_cart_item($key);
        }
    }
    // añadir
    $data = ['viaje_fecha'=>[
        'idx'=>$idx,
        'inicio'=>$fechas[$idx]['inicio'] ?? '',
        'fin'=>$fechas[$idx]['fin'] ?? '',
        'precio_normal'=>$fechas[$idx]['precio_normal'] ?? '',
        'precio_rebajado'=>$fechas[$idx]['precio_rebajado'] ?? '',
        'concepto'=>$fechas[$idx]['concepto'] ?? '',
    ]];
    $added_key = WC()->cart->add_to_cart($pid, $qty, 0, [], $data);
    if (!$added_key) wp_send_json_error(['message'=>__('No se pudo añadir al carrito','manaslu')]);

    // Construir mapa de extras incluidos (clave => cantidad base)
    $included_map = mv_collect_included_extras_for_date($pid, $idx);
    $included_ids = array_keys($included_map);

    // Sembrar baseline en el carrito para extras incluidos
    $extras_seed = [];
    if (!empty($included_map)) {
        foreach ($included_map as $extra_id => $base_qty) {
            $extra_id = (int)$extra_id;
            $qty_base = max(1, (int)$base_qty);
            $price_live = 0.0;
            if ($idx >= 0 && function_exists('mv_product_extra_price_for_idx')) {
                $price_live = (float) mv_product_extra_price_for_idx($pid, $extra_id, $idx);
            } elseif (function_exists('mv_product_extra_price')) {
                $price_live = (float) mv_product_extra_price($pid, $extra_id, null);
            }

            $extras_seed[$extra_id] = [
                'extra_id' => $extra_id,
                'title'    => get_the_title($extra_id),
                'price'    => $price_live,
                'qty'      => $qty_base,
                'included' => $qty_base,
            ];
        }
    }

    WC()->cart->cart_contents[$added_key]['viaje_extras'] = $extras_seed;
    WC()->cart->set_session();

    $extras_state = [];
    $map_now = isset(WC()->cart->cart_contents[$added_key]['viaje_extras']) && is_array(WC()->cart->cart_contents[$added_key]['viaje_extras'])
        ? WC()->cart->cart_contents[$added_key]['viaje_extras']
        : [];
    foreach ($map_now as $extra_id => $row) {
        $price_raw = isset($row['price']) ? (float)$row['price'] : 0.0;
        $extras_state[(int)$extra_id] = [
            'qty'       => max(0, (int)($row['qty'] ?? 0)),
            'included'  => max(0, (int)($row['included'] ?? 0)),
            'price'     => $price_raw,
            'price_html'=> wc_price($price_raw),
        ];
    }

    WC()->cart->calculate_totals();

    wp_send_json_success([
        'message'      => __('Fecha seleccionada','manaslu'),
        'idx'          => $idx,
        'cart_url'     => wc_get_cart_url(),
        'included'     => $included_ids,
        'included_map' => $included_map,
        'extras_state' => $extras_state,
    ]);
}

/* Metadatos de la línea + fijar precio por fecha */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id){
    // soporte también para petición GET (no AJAX)
    if (isset($_REQUEST['viaje_fecha_idx']) && !isset($cart_item_data['viaje_fecha'])) {
        $idx   = absint($_REQUEST['viaje_fecha_idx']);
        $items = mv_get_fechas($product_id);
        if (isset($items[$idx])) {
            $sel = $items[$idx];
            $cart_item_data['viaje_fecha'] = [
                'idx'=>$idx,
                'inicio'=>$sel['inicio'] ?? '',
                'fin'=>$sel['fin'] ?? '',
                'precio_normal'=>$sel['precio_normal'] ?? '',
                'precio_rebajado'=>$sel['precio_rebajado'] ?? '',
                'concepto'=>$sel['concepto'] ?? '',
            ];
        }
    }
    return $cart_item_data;
},10,2);

add_filter('woocommerce_get_item_data', function($item_data,$cart_item){
    if (!empty($cart_item['viaje_fecha'])) {
        $vf=$cart_item['viaje_fecha'];
        if (!empty($vf['inicio']))   $item_data[]=['name'=>__('Fecha inicio','manaslu'),'value'=>wc_clean($vf['inicio'])];
        if (!empty($vf['fin']))      $item_data[]=['name'=>__('Fecha fin','manaslu'),'value'=>wc_clean($vf['fin'])];
        if (!empty($vf['concepto'])) $item_data[]=['name'=>__('Concepto','manaslu'),'value'=>wc_clean($vf['concepto'])];
    }
    return $item_data;
},10,2);

add_action('woocommerce_before_calculate_totals', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $ci) {
        if (!empty($ci['viaje_fecha']) && isset($ci['data']) && is_object($ci['data'])) {
            $vf=$ci['viaje_fecha'];
            $regular= isset($vf['precio_normal']) ? (float)$vf['precio_normal'] : 0;
            $sale   = isset($vf['precio_rebajado']) ? (float)$vf['precio_rebajado'] : 0;
            $price  = ($sale>0 && ($regular==0 || $sale<$regular)) ? $sale : $regular;
            if ($price>0) $ci['data']->set_price($price);
        }
    }
},20);

/* Guardar meta en items del pedido */
add_action('woocommerce_checkout_create_order_line_item', function($item,$cart_item_key,$values,$order){
    if (!empty($values['viaje_fecha'])) {
        $vf = $values['viaje_fecha'];
        $item->add_meta_data('viaje_fecha_idx', (int)$vf['idx']);
        $item->add_meta_data('Fecha inicio', $vf['inicio'] ?? '');
        $item->add_meta_data('Fecha fin', $vf['fin'] ?? '');
        if (!empty($vf['concepto'])) $item->add_meta_data('Concepto', $vf['concepto']);
    }
},10,4);

/* Actualizar cupos vendidos al cambiar estado del pedido */
add_action('woocommerce_order_status_changed', function($order_id,$old,$new){
    $counted = ['processing','completed'];
    $was = in_array($old,$counted,true);
    $is  = in_array($new,$counted,true);
    if ($was === $is) return; // sin cambio de “contabiliza / no contabiliza”

    $mult = $is ? 1 : -1; // sumar o restar

    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $pid = $item->get_product_id();
        $product = wc_get_product($pid);
        if (!$product || $product->get_type()!=='viaje') continue;

        $idx = (int)$item->get_meta('viaje_fecha_idx');
        if ($idx < 0) continue;

        $qty = (int)$item->get_quantity();

        // actualizar mapa de ventas
        $map = mv_get_sales_map($pid);
        $map[$idx] = max(0, (int)($map[$idx] ?? 0) + $mult*$qty);
        mv_set_sales_map($pid, $map);
    }
},10,3);

/* =========================
 * 3) Estilos + JS de interfaz
 * ========================= */
add_action('wp_head', function(){ ?>
<style>
/* Tipografía solicitada */
@font-face{font-family:"Host Grotesk";font-style:normal;font-weight:400;src:local("Host Grotesk"), local("HostGrotesk");}
.mv-fechas{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;margin:10px 0;}
.mv-card{border:1px solid #e8e8e8;border-radius:10px;padding:16px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05);font-family:"Host Grotesk",system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#000;}
.mv-card.is-selected{border-color:#21a65b;box-shadow:0 0 0 2px #21a65b33;}
.mv-badge{font-size:12px;font-weight:700;color:#e3172d;margin-bottom:10px;text-transform:uppercase;letter-spacing:.3px;}
.mv-dates{display:flex;flex-direction:column;gap:2px;margin-bottom:6px;}
.mv-date-label{font-size:12px;font-weight:700;margin-right:6px;color:#000;}
.mv-date-value{font-size:16px;font-weight:800;letter-spacing:.3px;}
.mv-price{margin:6px 0 10px;}
.mv-price-label{font-size:12px;font-weight:700;margin-right:4px;color:#000;}
.mv-price-amount{font-size:16px;font-weight:800;}
.mv-stock{font-size:13px;margin-bottom:12px;}
.mv-nocapacity{color:#999;}
.mv-actions{text-align:left}
.mv-btn{border:none;border-radius:999px;padding:10px 18px;font-weight:700;cursor:pointer;color:#fff}
.mv-btn-red{background:#e3172d;}
.mv-btn-green{background:#21a65b;}
.mv-btn-gray{background:#bfbfbf;color:#fff;cursor:not-allowed;}
.mv-card.is-closed .mv-btn-red{background:#bfbfbf;cursor:not-allowed;}
.mv-msg{margin-top:10px;color:#21a65b;font-weight:700;display:none}
</style>
<?php });
// Add admin-side CSS to ensure checkboxes are visible in WooCommerce options panel
add_action('admin_head', function(){ ?>
<style>
/* Asegura que los checkboxes 'Incluye' en Fecha > Extras sean visibles, sin hacks de ocultamiento */
.woocommerce_options_panel input.mv-inc[type="checkbox"]{
  width:auto !important;
  height:auto !important;
  opacity:1 !important;
  visibility:visible !important;
  position:static !important;
  clip:auto !important;
  margin:0 0.25em 0 0 !important;
  vertical-align:middle !important;
  appearance:auto !important;
  -webkit-appearance:auto !important;
}
</style>
<?php });

add_action('wp_footer', function(){ ?>
<script>
(function(){
  function ready(fn){document.readyState!=='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  ready(function(){
    document.querySelectorAll('.mv-fechas').forEach(function(grid){
      var pid = grid.getAttribute('data-pid');

      function refreshExtraPrices(){
        var fd = new FormData();
        fd.append('action','viaje_extra_prices');
        fd.append('pid', pid);
        fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {method:'POST', credentials:'same-origin', body:fd})
          .then(function(r){ return r.json(); })
          .then(function(resp){
            if(resp && resp.success && resp.data && resp.data.prices){
              Object.keys(resp.data.prices).forEach(function(eid){
                document.querySelectorAll('.vx-extras-block[data-pid="'+pid+'"] .vx-extra[data-extra="'+eid+'"] .vx-price')
                  .forEach(function(el){ el.innerHTML = ' — ' + resp.data.prices[eid]; });
              });
            }
          })
          .catch(function(){});
      }

      function syncExtraNode(eid, qty, inc){
        var selector = '.vx-extras-root .vx-extras-block[data-pid="'+pid+'"] .vx-extra[data-extra="'+eid+'"]';
        document.querySelectorAll(selector).forEach(function(wrap){
          var q = parseInt(qty,10); if (isNaN(q)) q = 0;
          var i = parseInt(inc,10); if (isNaN(i)) i = 0;
          if (i > 0 && q < i) q = i;

          wrap.setAttribute('data-qty', q);
          wrap.setAttribute('data-inc', i);

          var countEl = wrap.querySelector('.vx-count');
          if (countEl) {
            countEl.setAttribute('data-qty', q);
            countEl.setAttribute('data-inc', i);
            countEl.textContent = String(q);
          }

          var subBtn = wrap.querySelector('[data-vx-act="sub"]');
          if (subBtn) subBtn.disabled = q <= i;
          var addBtn = wrap.querySelector('[data-vx-act="add"]');
          if (addBtn) addBtn.disabled = false;
        });
      }

      function applyExtrasState(payload){
        var state = payload && payload.extras_state ? payload.extras_state : {};
        var includedMap = payload && payload.included_map ? payload.included_map : {};
        var nodes = document.querySelectorAll('.vx-extras-root .vx-extras-block[data-pid="'+pid+'"] .vx-extra');
        nodes.forEach(function(node){
          var eid = node.getAttribute('data-extra');
          if (!eid) return;
          if (state.hasOwnProperty(eid)) {
            var entry = state[eid];
            if (entry && typeof entry === 'object') {
              syncExtraNode(eid, entry.qty, entry.included);
              return;
            }
          }
          if (includedMap.hasOwnProperty(eid)) {
            var baseQty = includedMap[eid];
            syncExtraNode(eid, baseQty, baseQty);
          } else {
            syncExtraNode(eid, 0, 0);
          }
        });
      }

      refreshExtraPrices();

      var mode = grid.getAttribute('data-mode') || 'ajax';
      var goBase = grid.getAttribute('data-go-base') || '';
      var initialIdxAttr = grid.getAttribute('data-initial-idx') || '';

      function ajaxPick(idx, card, btn, onSuccess){
        if (!card) return;
        if (btn) btn.disabled = true;

        var data = new FormData();
        data.append('action','mv_pick_date');
        data.append('pid', pid);
        data.append('idx', idx);
        data.append('qty', 1);

        fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {method:'POST', credentials:'same-origin', body:data})
          .then(r=>r.json())
          .then(function(res){
            if(btn) btn.disabled=false;
            if(res && res.success){
              grid.querySelectorAll('.mv-card').forEach(function(c){
                c.classList.remove('is-selected');
                var b=c.querySelector('.mv-pick');
                if(b){ b.textContent = '<?php echo esc_js(__('ESCOGER FECHA','manaslu'));?>'; b.classList.remove('mv-btn-green'); b.classList.add('mv-btn-red'); }
              });

              card.classList.add('is-selected');
              if(btn){
                btn.textContent = '<?php echo esc_js(__('SELECCIONADA ✓','manaslu'));?>';
                btn.classList.remove('mv-btn-red'); btn.classList.add('mv-btn-green');
              }

              var msg = grid.querySelector('.mv-msg');
              if(msg){ msg.textContent = '<?php echo esc_js(__('Fecha seleccionada','manaslu'));?>'; msg.style.display='block'; setTimeout(()=>{msg.style.display='none';}, 2500); }

              applyExtrasState(res.data || {});
              refreshExtraPrices();

              if (window.jQuery) { jQuery(document.body).trigger('wc_fragment_refresh'); }

              if (typeof onSuccess === 'function') {
                try { onSuccess(res); } catch(e) {}
              }

            } else {
              alert(res && res.data && res.data.message ? res.data.message : 'Error');
            }
          })
          .catch(function(){ if(btn) btn.disabled=false; alert('Error de red'); });
      }

      function redirectToComprando(idx){
        if (!goBase) return;
        var sep = goBase.indexOf('?') === -1 ? '?' : '&';
        var target = goBase + sep + 'viaje_fecha_idx=' + encodeURIComponent(idx);
        window.location.href = target;
      }

      function handleSelection(idx, card, btn){
        if (mode === 'redirect') {
          ajaxPick(idx, card, btn, function(){ redirectToComprando(idx); });
        } else {
          ajaxPick(idx, card, btn);
        }
      }

      grid.addEventListener('click', function(e){
        var btn = e.target.closest('.mv-pick');
        if(!btn) return;
        var card = btn.closest('.mv-card');
        if(!card || card.classList.contains('is-closed')) return;

        var idx = card.getAttribute('data-idx');
        handleSelection(idx, card, btn);
      });

      if (mode === 'ajax' && initialIdxAttr !== '') {
        var existingSelected = grid.querySelector('.mv-card.is-selected');
        if (!existingSelected) {
          var initCard = grid.querySelector('.mv-card[data-idx="'+initialIdxAttr+'"]');
          if (initCard) {
            var initBtn = initCard.querySelector('.mv-pick');
            handleSelection(initialIdxAttr, initCard, initBtn);
          }
        }
      }
    });
  });
})();
</script>
<?php });
