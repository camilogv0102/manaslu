<?php
/**
 * Plugin Name: Manaslu - Extras para Viajes (MU)
 * Description: Extras (CPT) con asignación a productos y precios por producto. UI front con +/- que suma bajo la línea del viaje (no crea ítems separados). Incluye tipo especial "Seguro de Viaje". Ahora soporta precio de extras por FECHA (lee _viaje_fechas_extras del producto viaje).
 * Version: 2.2.0
 * Author: Manaslu Adventures
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
 * 0) Helpers generales
 * ============================================================ */

if (!function_exists('mv_is_seguro_extra')){
    function mv_is_seguro_extra($extra_id){
        $terms = get_the_terms($extra_id, 'extra_category');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                if ($t->slug === 'seguro-de-viaje' || strtolower($t->name) === 'seguro de viaje') {
                    return true;
                }
            }
        }
        return false;
    }
}

// ¿Extra pertenece a categoría "Individual"?
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
// ¿Extra pertenece a categoría "Personas"?
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

// Localizar el extra "Adultos" asignado a un producto
if (!function_exists('mv_find_adult_extra_for_product')){
    function mv_find_adult_extra_for_product($product_id){
        $assigned = get_post_meta($product_id, 'extras_asignados', true);
        if (!is_array($assigned) || empty($assigned)) return 0;
        foreach ($assigned as $eid) {
            if (function_exists('mv_is_seguro_extra') && mv_is_seguro_extra($eid)) continue;
            $title = strtolower(trim(get_the_title($eid)));
            $terms = get_the_terms($eid, 'extra_category');
            $in_personas = false;
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $t) {
                    if ($t->slug === 'personas' || strtolower($t->name) === 'personas') { $in_personas = true; break; }
                }
            }
            if ($in_personas && ( $title === 'adultos' || $title === 'adulto' || strpos($title, 'adult') !== false )) {
                return (int)$eid;
            }
        }
        return 0;
    }
}

// Índice de fecha seleccionado en el carrito para un producto dado
if (!function_exists('mv_cart_selected_idx_local')){
    function mv_cart_selected_idx_local($product_id){
        if (!function_exists('WC') || !WC()->cart) return -1;
        foreach (WC()->cart->get_cart() as $ci) {
            if (!empty($ci['product_id']) && (int)$ci['product_id'] === (int)$product_id && isset($ci['viaje_fecha']['idx'])) {
                return (int)$ci['viaje_fecha']['idx'];
            }
        }
        return -1;
    }
}


// Precio base de la FECHA seleccionada (sin extras)
if (!function_exists('mv_trip_base_price_for_selected_date')){
    function mv_trip_base_price_for_selected_date($product_id){
        if (!function_exists('mv_get_fechas')) return 0.0;
        $idx = mv_cart_selected_idx_local($product_id);
        if ($idx < 0) return 0.0;
        $rows = mv_get_fechas($product_id);
        if (!isset($rows[$idx])) return 0.0;
        $r = isset($rows[$idx]['precio_normal'])   ? (float)$rows[$idx]['precio_normal']   : 0.0;
        $s = isset($rows[$idx]['precio_rebajado']) ? (float)$rows[$idx]['precio_rebajado'] : 0.0;
        return ($s > 0 && ($r == 0 || $s < $r)) ? $s : $r;
    }
}

if (!function_exists('mv_resolve_product_id')) {
    function mv_resolve_product_id($fallback_to_current_post = true) {
        if (function_exists('cv_resolve_product_id_from_request')) {
            $pid = (int) cv_resolve_product_id_from_request();
            if ($pid) return $pid;
        }
        if (isset($_GET['pid'])) {
            $pid = absint($_GET['pid']);
            if ($pid) return $pid;
        }
        if (function_exists('wc_get_product')) {
            global $product;
            if ($product instanceof WC_Product) return $product->get_id();
        }
        if ($fallback_to_current_post && is_singular('product')) return get_the_ID();
        return 0;
    }
}

// Extras incluidos por defecto en el producto
if (!function_exists('mv_get_included_extras')){
    function mv_get_included_extras($product_id){
        $arr = get_post_meta($product_id, 'mv_included_extras', true);
        return is_array($arr) ? array_map('intval', $arr) : [];
    }
}

if (!function_exists('mv_extra_should_be_included')){
    function mv_extra_should_be_included($product_id, $extra_id, $date_idx = null){
        $product_id = (int)$product_id;
        $extra_id   = (int)$extra_id;
        if (!$product_id || !$extra_id) return 0;

        $idx = is_null($date_idx) ? -1 : (int)$date_idx;

        if ($idx >= 0) {
            $by_idx = get_post_meta($product_id, '_viaje_fechas_extras', true);
            if (is_array($by_idx)
                && isset($by_idx[$idx])
                && isset($by_idx[$idx][$extra_id])
                && !empty($by_idx[$idx][$extra_id]['included'])) {
                $included = (int)$by_idx[$idx][$extra_id]['included'];
                return $included > 0 ? $included : 1;
            }
        }

        $assigned = get_post_meta($product_id, 'extras_asignados', true);
        $assigned = is_array($assigned) ? array_map('intval', $assigned) : [];
        if (!in_array($extra_id, $assigned, true)) return 0;

        $inc_prod = get_post_meta($product_id, 'mv_included_extras', true);
        $inc_prod = is_array($inc_prod) ? array_map('intval', $inc_prod) : [];
        if (!in_array($extra_id, $inc_prod, true)) return 0;

        $is_seg = function_exists('mv_is_seguro_extra') ? mv_is_seguro_extra($extra_id) : false;
        $is_ind = function_exists('mv_is_individual_extra') ? mv_is_individual_extra($extra_id) : false;
        if ($is_seg || $is_ind) return 0;

        return 1;
    }
}

// Obtener si un extra tiene una unidad base incluida para el producto/fecha
if (!function_exists('mv_extra_base_included')){
    function mv_extra_base_included($product_id, $extra_id, $date_idx = null){
        $product_id = (int)$product_id;
        $extra_id   = (int)$extra_id;
        if (!$product_id || !$extra_id) return 0;

        // Si ya hay carrito, priorizar el flag que vive en la línea
        if (function_exists('WC') && WC()->cart) {
            $key = mv_find_target_cart_item_key($product_id);
            if ($key) {
                $cart = WC()->cart->get_cart();
                if (isset($cart[$key]['viaje_extras'][$extra_id]['included'])) {
                    return !empty($cart[$key]['viaje_extras'][$extra_id]['included']) ? 1 : 0;
                }
            }
        }

        $idx = is_null($date_idx)
            ? (function_exists('mv_cart_selected_idx_local') ? mv_cart_selected_idx_local($product_id) : -1)
            : (int)$date_idx;

        $should = mv_extra_should_be_included($product_id, $extra_id, $idx);
        return $should > 0 ? 1 : 0;
    }
}

/* ============================================================
 * 1) CPT EXTRA + taxonomía (SIN precios en el CPT)
 * ============================================================ */
add_action('init', function () {
    register_post_type('extra', [
        'labels' => [
            'name'               => __('Extras', 'manaslu'),
            'singular_name'      => __('Extra', 'manaslu'),
            'menu_name'          => __('Extras', 'manaslu'),
            'add_new'            => __('Añadir nuevo', 'manaslu'),
            'add_new_item'       => __('Añadir nuevo extra', 'manaslu'),
            'edit_item'          => __('Editar extra', 'manaslu'),
            'view_item'          => __('Ver extra', 'manaslu'),
            'all_items'          => __('Todos los extras', 'manaslu'),
            'search_items'       => __('Buscar extras', 'manaslu'),
            'not_found'          => __('No se encontraron extras', 'manaslu'),
        ],
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 27,
        'menu_icon'          => 'dashicons-cart',
        'supports'           => ['title','editor','thumbnail','excerpt'],
        'show_in_rest'       => false,
    ]);

    register_taxonomy('extra_category', ['extra'], [
        'hierarchical'      => true,
        'labels'            => [
            'name'          => __('Categorías de Extras', 'manaslu'),
            'singular_name' => __('Categoría de Extra', 'manaslu'),
        ],
        'show_ui'            => true,
        'show_admin_column'  => true,
        'query_var'          => true,
        'rewrite'            => ['slug' => 'extra-category'],
        'show_in_rest'       => true,
    ]);

    // >>> Seguro de Viaje: crea la categoría si no existe
    if (!term_exists('seguro-de-viaje', 'extra_category')) {
        wp_insert_term(__('Seguro de Viaje', 'manaslu'), 'extra_category', ['slug' => 'seguro-de-viaje']);
    }
});

// Desactivar Gutenberg para extra
add_filter('use_block_editor_for_post_type', function ($use, $type) {
    return $type === 'extra' ? false : $use;
}, 10, 2);

/* ============================================================
 * 2) Metabox en PRODUCTO: asignar extras (checklist lateral)
 * ============================================================ */
add_action('add_meta_boxes', function(){
    add_meta_box('extras_asignados_box', __('Extras asignados', 'manaslu'), function($post){
        $selected = get_post_meta($post->ID, 'extras_asignados', true);
        $selected = is_array($selected) ? $selected : [];
        $extras = get_posts([
            'post_type'      => 'extra',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        echo '<p>'.__('Selecciona los extras asociados a este producto. Luego, en la pestaña “Extras”, podrás fijar sus precios por producto (y si usas fechas, el plugin Viajes permite poner precio por fecha).', 'manaslu').'</p><ul style="margin:0;padding:0">';
        foreach ($extras as $e) {
            $chk = in_array($e->ID, $selected, true) ? 'checked' : '';
            echo '<li style="list-style:none;margin:4px 0"><label><input type="checkbox" name="extras_asignados[]" value="'.esc_attr($e->ID).'" '.$chk.'> '.esc_html($e->post_title).'</label></li>';
        }
        echo '</ul>';
    }, 'product', 'side', 'default');
});

add_action('save_post_product', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (isset($_POST['extras_asignados']) && is_array($_POST['extras_asignados'])) {
        update_post_meta($post_id, 'extras_asignados', array_map('intval', $_POST['extras_asignados']));
    } else {
        delete_post_meta($post_id, 'extras_asignados');
    }
});

/* ============================================================
 * 3) Pestañas Woo “Extras” y “Seguro de viaje” (precios por producto)
 * ============================================================ */

/** Añadir pestañas */
add_filter('woocommerce_product_data_tabs', function($tabs){
    $tabs['mv_extras'] = [
        'label'    => __('Extras', 'manaslu'),
        'target'   => 'mv_extras_data',
        'class'    => ['show_if_viaje'],
        'priority' => 22,
    ];
    $tabs['mv_seguro'] = [
        'label'    => __('Seguro de viaje', 'manaslu'),
        'target'   => 'mv_seguro_data',
        'class'    => ['show_if_viaje'],
        'priority' => 23,
    ];
    $tabs['mv_sc_extras_tab'] = [
        'label'    => __('Shortcode: Orden de Extras', 'manaslu'),
        'target'   => 'mv_sc_extras_panel',
        'class'    => ['show_if_viaje'],
        'priority' => 90,
    ];
    // Personas (por fecha) tab removed
    return $tabs;
}, 30);

add_action('admin_head', function(){ ?>
<style>
/* Asegura que los checkboxes 'Incluye' se vean en las pestañas de producto */
.woocommerce_options_panel input.mv-inc[type="checkbox"]{
  display:inline-block !important;
  width:auto; height:auto;
  opacity:1 !important; visibility:visible !important;
}
</style>
<?php });

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
    if (!isset($_GET['post']) && $hook !== 'post-new.php') return;
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'product') return;
    wp_enqueue_script('jquery-ui-sortable');
});

add_action('admin_footer', function(){
    global $pagenow, $post;
    if (!is_admin()) return;
    if (!in_array($pagenow, ['post.php','post-new.php'], true)) return;
    if (!$post || $post->post_type !== 'product') return;
    if (!function_exists('mv_get_fechas')) return;

    $pid = (int)$post->ID;
    $fechas   = mv_get_fechas($pid);
    if (empty($fechas) || !is_array($fechas)) return;

    $assigned = get_post_meta($pid,'extras_asignados',true);
    $assigned = is_array($assigned) ? array_map('intval',$assigned) : [];
    if (empty($assigned)) return;

    $personas = array_values(array_filter($assigned, function($eid){
        return function_exists('mv_is_personas_extra') && mv_is_personas_extra($eid);
    }));
    if (empty($personas)) return;

    $by_idx = get_post_meta($pid,'_viaje_fechas_extras',true);
    $by_idx = is_array($by_idx) ? $by_idx : [];

    // Pre-serializa datos para JS
    $data = [
        'fechas'   => array_values($fechas),
        'personas' => array_map(function($eid){ return ['id'=>$eid,'title'=>get_the_title($eid)]; }, $personas),
        'by_idx'   => $by_idx,
        'i18n'     => [
            'blockTitle' => __('Personas (por fecha)', 'manaslu'),
            'extra'      => __('Extra', 'manaslu'),
            'reg'        => __('Precio regular', 'manaslu'),
            'sale'       => __('Precio rebajado', 'manaslu'),
            'incluye'    => __('Incluye', 'manaslu'),
            'adultAuto'  => __('(auto: igual al precio de la fecha)', 'manaslu'),
        ],
    ];
    ?>
    <script>
(function(){
  if (typeof window.jQuery === 'undefined') return;
  var $ = jQuery;

  // ===== SAFE ESCAPE (fallback if underscore not present) =====
  var esc = (window._ && _.escape) ? _.escape : function(s){
    return String(s == null ? '' : s).replace(/[&<>"'`=\/]/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c];
    });
  };

  // ===== DATA PREP FROM PHP =====
  var data = <?php echo wp_json_encode($data); ?>;

  function baseForRow(row){
    var r = parseFloat(row && row.precio_normal ? row.precio_normal : 0) || 0;
    var s = parseFloat(row && row.precio_rebajado ? row.precio_rebajado : 0) || 0;
    return (s>0 && (r===0 || s<r)) ? s : r;
  }
  function isAdult(title){
    if(!title) return false; var l = String(title).trim().toLowerCase();
    return (l==='adultos' || l==='adulto' || l.indexOf('adult')!==-1);
  }
  function isChild(title){ if(!title) return false; var l=String(title).toLowerCase(); return (l.indexOf('niñ')!==-1 || l.indexOf('child')!==-1 || l.indexOf('kids')!==-1); }

  // Render <tr> rows for Personas extras for a given date index
  function renderRows(idx){
    var rows = [];
    var row = data.fechas[idx] || {};
    var base = baseForRow(row);

    for (var i=0;i<data.personas.length;i++){
      var e = data.personas[i];
      var cur = (data.by_idx[idx] && data.by_idx[idx][e.id]) ? data.by_idx[idx][e.id] : {};
      var reg = (typeof cur.regular!== 'undefined') ? cur.regular : '';
      var sal = (typeof cur.sale   !== 'undefined') ? cur.sale    : '';
      var inc = !!(cur.included);
      var pct = (typeof cur.pct !== 'undefined') ? cur.pct : '';

      var tr = '<tr>';
      if (isAdult(e.title)){
        reg = base; sal = base;
        tr += '<td>'+esc(e.title)+' <small style="opacity:.75">'+esc(data.i18n.adultAuto)+'</small></td>';
        tr += '<td><input type="number" step="0.01" min="0" name="_viaje_fechas_extras['+idx+']['+e.id+'][regular]" value="'+esc(reg)+'" readonly style="width:100%;background:#f7f7f7;color:#666"></td>';
        tr += '<td><input type="number" step="0.01" min="0" name="_viaje_fechas_extras['+idx+']['+e.id+'][sale]" value="'+esc(sal)+'" readonly style="width:100%;background:#f7f7f7;color:#666"></td>';
        tr += '<td style="text-align:center">'
           +  '<input type="hidden" name="_viaje_fechas_extras['+idx+']['+e.id+'][included]" value="0">'
           +  '<input class="mv-inc checkbox" type="checkbox" name="_viaje_fechas_extras['+idx+']['+e.id+'][included]" value="1" '+(inc?'checked':'')+'>'
           +  '</td>';
      } else if (isChild(e.title)) {
        tr += '<td>'+esc(e.title)+'</td>';
        tr += '<td><input type="number" step="0.01" min="0" name="_viaje_fechas_extras['+idx+']['+e.id+'][regular]" value="'+esc(reg)+'" readonly style="width:100%;background:#f7f7f7;color:#666"></td>';
        tr += '<td>'+
              '<div style="display:flex;gap:6px;align-items:center">'+
                '<input type="number" step="0.01" min="0" max="100" name="_viaje_fechas_extras['+idx+']['+e.id+'][pct]" value="'+esc(pct)+'" placeholder="%" style="width:70px">'+
                '<input type="number" step="0.01" min="0" name="_viaje_fechas_extras['+idx+']['+e.id+'][sale]" value="'+esc(sal)+'" readonly style="flex:1;background:#f7f7f7;color:#666">'+
              '</div>'+
              '</td>';
        tr += '<td style="text-align:center">'
           +  '<input type="hidden" name="_viaje_fechas_extras['+idx+']['+e.id+'][included]" value="0">'
           +  '<input class="mv-inc checkbox" type="checkbox" name="_viaje_fechas_extras['+idx+']['+e.id+'][included]" value="1" '+(inc?'checked':'')+'>'
           +  '</td>';
      } else {
        tr += '<td>'+esc(e.title)+'</td>';
        tr += '<td><input type="number" step="0.01" min="0" name="_viaje_fechas_extras['+idx+']['+e.id+'][regular]" value="'+esc(reg)+'" style="width:100%"></td>';
        tr += '<td><input type="number" step="0.01" min="0" name="_viaje_fechas_extras['+idx+']['+e.id+'][sale]" value="'+esc(sal)+'" style="width:100%"></td>';
        tr += '<td style="text-align:center">'
           +  '<input type="hidden" name="_viaje_fechas_extras['+idx+']['+e.id+'][included]" value="0">'
           +  '<input class="mv-inc checkbox" type="checkbox" name="_viaje_fechas_extras['+idx+']['+e.id+'][included]" value="1" '+(inc?'checked':'')+'>'
           +  '</td>';
      }
      tr += '</tr>';
      rows.push(tr);
    }
    return rows.join('');
  }

  // Locate the main Fechas panel
  var $panel = $('#mv_fechas_data');
  if (!$panel.length){
    // fallback: try to find a panel that contains Fechas table
    var $any = $('table.widefat:contains("Precios de extras para esta fecha")').closest('.panel.woocommerce_options_panel');
    if ($any.length) $panel = $any.first();
  }
  if(!$panel.length) return;

  // Find all target tables (the existing collapsible tables where Individual extras live)
  var $tables = $panel.find('table.widefat').filter(function(){
    var $ths = $(this).find('thead th');
    if ($ths.length < 3) return false;
    var t0 = $.trim($ths.eq(0).text()).toLowerCase();
    var t1 = $.trim($ths.eq(1).text()).toLowerCase();
    var t2 = $.trim($ths.eq(2).text()).toLowerCase();
    return (t0.indexOf('extra')!==-1 && t1.indexOf('precio')!==-1 && t2.indexOf('precio')!==-1);
  });

  // Append Personas rows into each date's existing table body
  $tables.each(function(idx){
    if (!data.fechas || idx >= data.fechas.length) return; // keep indices aligned
    var $tbody = $(this).find('tbody');
    if (!$tbody.length) return;

    // Avoid duplicating if script runs twice
    if ($tbody.data('mv-personas-injected')) return;

    // Inject rows after the existing (Individual) rows
    var html = renderRows(idx);
    if (html){
      $tbody.append(html);
      $tbody.data('mv-personas-injected', true);
    }
  });
})();
</script>
    <?php
});

add_action('admin_footer', function(){
    global $pagenow, $post;
    if (!is_admin()) return;
    if (!in_array($pagenow, ['post.php','post-new.php'], true)) return;
    if (!$post || $post->post_type !== 'product') return;
?>
<style>
#mv-sc-cat-sort{margin:0;padding:0;list-style:none}
#mv-sc-cat-sort li{display:flex;align-items:center;gap:10px;padding:8px 10px;margin:0 0 6px;background:#fff;border:1px solid #dcdcdc;border-radius:6px;cursor:move}
#mv-sc-cat-sort li .mv-sc-term-name{font-weight:600;flex:0 0 auto;min-width:140px}
#mv-sc-cat-sort li .mv-sc-term-label{flex:1 1 auto}
.mv-sc-actions{margin-top:10px}
</style>
<script>
jQuery(function($){
    var $list = $('#mv-sc-cat-sort');
    if(!$list.length) return;

    var $orderField = $('#mv_sc_cat_order_field');
    var $resetField = $('#mv_sc_cat_reset_field');

    function refreshOrder(){
        var ids = [];
        $list.find('li').each(function(){
            var id = $(this).data('term-id');
            if (id) ids.push(id);
        });
        $orderField.val(ids.join(','));
    }

    $list.sortable({
        axis: 'y',
        update: function(){
            $resetField.val('0');
            refreshOrder();
        },
        create: refreshOrder
    });

    $list.on('input change', '.mv-sc-term-label', function(){
        $resetField.val('0');
    });

    $('#mv-sc-cat-reset').on('click', function(e){
        e.preventDefault();
        $resetField.val('1');
        $list.find('.mv-sc-term-label').val('');
        var items = $list.find('li').get();
        items.sort(function(a,b){
            var aPos = parseInt($(a).data('default-position'), 10) || 0;
            var bPos = parseInt($(b).data('default-position'), 10) || 0;
            return aPos - bPos;
        });
        $.each(items, function(_, item){
            $list.append(item);
        });
        refreshOrder();
        $orderField.val('');
    });

    $('#post').on('submit', function(){
        if ($resetField.val() !== '1') {
            refreshOrder();
        } else {
            $orderField.val('');
        }
    });
});
</script>
<?php });

/** Panel: EXTRAS (no “seguro”) */
add_action('woocommerce_product_data_panels', function () {
    global $post;
    if (!$post) return;
    $pid       = $post->ID;
    $assigned  = get_post_meta($pid, 'extras_asignados', true);
    $assigned  = is_array($assigned) ? $assigned : [];
    $saved     = get_post_meta($pid, 'mv_extra_prices', true);
    $saved     = is_array($saved) ? $saved : [];
    $included = function_exists('mv_get_included_extras') ? mv_get_included_extras($pid) : (get_post_meta($pid, 'mv_included_extras', true) ?: []);
    $included = is_array($included) ? array_map('intval', $included) : [];

    echo '<div id="mv_extras_data" class="panel woocommerce_options_panel"><div class="options_group">';

    // Filtrar: solo extras NO "seguro de viaje", NO "Individual", NO "Personas"
    $normales = array_values(array_filter($assigned, function($eid){
        $is_seg = function_exists('mv_is_seguro_extra') && mv_is_seguro_extra($eid);
        $is_ind = function_exists('mv_is_individual_extra') && mv_is_individual_extra($eid);
        $is_per = function_exists('mv_is_personas_extra') && mv_is_personas_extra($eid);
        return !$is_seg && !$is_ind && !$is_per; // Solo extras normales (no Seguros, no Individual, no Personas)
    }));

    if (empty($normales)) {
        echo '<p>'.esc_html__('No hay extras asignados (o todos pertenecen a “Seguro de viaje” o “Individual”). Usa la caja lateral “Extras asignados” y guarda el producto.', 'manaslu').'</p>';
        echo '</div></div>';
        return;
    }

    echo '<p><strong>'.esc_html__('Precios por producto (extras)', 'manaslu').'</strong></p>';
    echo '<table class="widefat striped"><thead><tr>'
        . '<th>'.esc_html__('Extra', 'manaslu').'</th>'
        . '<th style="width:180px">'.esc_html__('Precio regular', 'manaslu').'</th>'
        . '<th style="width:180px">'.esc_html__('Precio rebajado', 'manaslu').'</th>'
        . '<th style="width:120px;text-align:center">'.esc_html__('Incluye', 'manaslu').'</th>'
        . '</tr></thead><tbody>';

    foreach ($normales as $eid) {
        $title = get_the_title($eid);
        $reg   = isset($saved[$eid]['regular']) ? $saved[$eid]['regular'] : '';
        $sal   = isset($saved[$eid]['sale'])    ? $saved[$eid]['sale']    : '';

        $pct   = get_post_meta($eid, '_mv_pct_descuento', true);
        $is_dyn = is_numeric($pct) && (float)$pct > 0;

        // Valores a mostrar en los inputs
        $reg_display = $reg;
        $sal_display = $sal;

        if ($is_dyn) {
            // Tomar como base el extra "Adultos" asignado a este producto (precio por producto)
            $adult_id = function_exists('mv_find_adult_extra_for_product') ? mv_find_adult_extra_for_product($pid) : 0;
            $base = 0.0;
            if ($adult_id) {
                $areg = isset($saved[$adult_id]['regular']) ? (float)$saved[$adult_id]['regular'] : 0.0;
                $asal = isset($saved[$adult_id]['sale'])    ? (float)$saved[$adult_id]['sale']    : 0.0;
                $base = ($asal > 0 && ($areg == 0 || $asal < $areg)) ? $asal : $areg;
            }
            // Si no hay "Adultos" con precio, dejamos vacío
            $reg_display = ($base > 0) ? wc_format_decimal($base * (1.0 - ((float)$pct/100.0))) : '';
            $sal_display = '';
        }

        $ro_attr = $is_dyn ? 'readonly style="width:100%;background:#f7f7f7;color:#666"' : 'style="width:100%"';
        $is_inc = in_array((int)$eid, $included, true);

        echo '<tr>';
        echo '<td>'.esc_html($title);
        if ($is_dyn) {
            $base_label = 'Adultos';
            if (!empty($adult_id)) { $base_label = get_the_title($adult_id); }
            echo ' <br><small style="opacity:.75">'.sprintf(esc_html__('Precio automático: %s − %s%%', 'manaslu'), esc_html($base_label), esc_html($pct)).'</small>';
        }
        echo '</td>';
        echo '<td><input type="number" step="0.01" min="0" name="mv_extra_price_reg['.esc_attr($eid).']" value="'.esc_attr($reg_display).'" '.$ro_attr.'></td>';
        echo '<td><input type="number" step="0.01" min="0" name="mv_extra_price_sale['.esc_attr($eid).']" value="'.esc_attr($sal_display).'" '.$ro_attr.'></td>';
        echo '<td style="text-align:center"><input type="checkbox" class="mv-inc" name="mv_included_extras[]" value="'.esc_attr($eid).'" '.checked(true, $is_inc, false).'></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div></div>';
});

add_action('woocommerce_product_data_panels', function () {
    global $post;
    if (!$post || $post->post_type !== 'product') return;

    $pid = (int) $post->ID;
    $assigned = get_post_meta($pid, 'extras_asignados', true);
    $assigned = is_array($assigned) ? array_map('intval', $assigned) : [];

    echo '<div id="mv_sc_extras_panel" class="panel woocommerce_options_panel">';
    echo '<div class="options_group">';

    if (empty($assigned)) {
        echo '<p>'.esc_html__('Todavía no has asignado extras a este producto. Usa la caja “Extras asignados” en la barra lateral y guarda el producto.', 'manaslu').'</p>';
        echo '</div></div>';
        return;
    }

    $terms_map = [];
    foreach ($assigned as $eid) {
        $terms = get_the_terms($eid, 'extra_category');
        if (!is_wp_error($terms) && $terms) {
            foreach ($terms as $term) {
                $terms_map[$term->term_id] = $term;
            }
        }
    }

    if (empty($terms_map)) {
        echo '<p>'.esc_html__('Los extras asignados no tienen categorías disponibles.', 'manaslu').'</p>';
        echo '</div></div>';
        return;
    }

    $stored_order = get_post_meta($pid, 'mv_sc_extra_cat_order', true);
    $stored_order = is_array($stored_order) ? array_values(array_map('absint', $stored_order)) : [];
    $stored_labels = get_post_meta($pid, 'mv_sc_extra_cat_labels', true);
    $stored_labels = is_array($stored_labels) ? $stored_labels : [];

    $alphabetical_terms = $terms_map;
    uasort($alphabetical_terms, function($a, $b){
        return strcasecmp($a->name, $b->name);
    });
    $default_positions = [];
    $default_index = 0;
    foreach ($alphabetical_terms as $term_id => $term_obj) {
        $default_positions[$term_id] = ++$default_index;
    }

    $terms_for_order = $terms_map;
    $ordered_terms = [];
    if (!empty($stored_order)) {
        foreach ($stored_order as $term_id) {
            if (isset($terms_for_order[$term_id])) {
                $ordered_terms[$term_id] = $terms_for_order[$term_id];
                unset($terms_for_order[$term_id]);
            }
        }
    }

    if (!empty($terms_for_order)) {
        uasort($terms_for_order, function($a, $b){
            return strcasecmp($a->name, $b->name);
        });
        foreach ($terms_for_order as $term_id => $term_obj) {
            $ordered_terms[$term_id] = $term_obj;
        }
    }

    $initial_order = implode(',', array_map('absint', array_keys($ordered_terms))); // may be empty string

    echo '<p>'.esc_html__('Arrastra para reordenar las categorías que se muestran en el Shortcode de extras y opcionalmente sobrescribe el título visible.', 'manaslu').'</p>';

    echo '<ul id="mv-sc-cat-sort" class="mv-sc-cat-sortable">';
    $position = 0;
    foreach ($ordered_terms as $term_id => $term_obj) {
        $position++;
        $label_value = isset($stored_labels[$term_id]) ? sanitize_text_field($stored_labels[$term_id]) : '';
        $default_pos = isset($default_positions[$term_id]) ? (int)$default_positions[$term_id] : $position;
        echo '<li class="mv-sc-cat-item" data-term-id="'.esc_attr($term_id).'" data-default-position="'.esc_attr($default_pos).'">';
        echo '<span class="mv-sc-term-name">'.esc_html($term_obj->name).'</span>';
        echo '<input type="text" class="mv-sc-term-label" name="mv_sc_cat_label['.esc_attr($term_id).']" value="'.esc_attr($label_value).'" placeholder="'.esc_attr($term_obj->name).'" />';
        echo '</li>';
    }
    echo '</ul>';

    echo '<input type="hidden" id="mv_sc_cat_order_field" name="mv_sc_cat_order" value="'.esc_attr($initial_order).'" />';
    echo '<input type="hidden" id="mv_sc_cat_reset_field" name="mv_sc_cat_reset" value="0" />';

    echo '<p class="mv-sc-actions">';
    echo '<button type="button" class="button" id="mv-sc-cat-reset">'.esc_html__('Restaurar por defecto', 'manaslu').'</button> ';
    echo '<span class="description">'.esc_html__('Los cambios se guardan al actualizar el producto.', 'manaslu').'</span>';
    echo '</p>';

    echo '</div></div>';
});


/** Panel: SEGURO (solo seguros) */
add_action('woocommerce_product_data_panels', function () {
    global $post;
    if (!$post) return;
    $pid       = $post->ID;
    $assigned  = get_post_meta($pid, 'extras_asignados', true);
    $assigned  = is_array($assigned) ? $assigned : [];
    $saved     = get_post_meta($pid, 'mv_extra_prices', true);
    $saved     = is_array($saved) ? $saved : [];

    echo '<div id="mv_seguro_data" class="panel woocommerce_options_panel"><div class="options_group">';

    $seguros = array_values(array_filter($assigned, function($eid){ return mv_is_seguro_extra($eid); }));

    if (empty($seguros)) {
        echo '<p>'.esc_html__('No tienes asignado ningún “Seguro de viaje”. Asigna uno en la caja lateral “Extras asignados” y guarda el producto.', 'manaslu').'</p>';
        echo '</div></div>';
        return;
    }

    echo '<p><strong>'.esc_html__('Precios por producto (Seguro de viaje)', 'manaslu').'</strong></p>';
    echo '<table class="widefat striped"><thead><tr>'
        . '<th>'.esc_html__('Seguro', 'manaslu').'</th>'
        . '<th style="width:240px">'.esc_html__('SIN cancelación (precio + enlace)', 'manaslu').'</th>'
        . '<th style="width:240px">'.esc_html__('CON cancelación (precio + enlace)', 'manaslu').'</th>'
        . '</tr></thead><tbody>';

    foreach ($seguros as $eid) {
        $title = get_the_title($eid);
        $sin = isset($saved[$eid]['seguro']['sin']) ? $saved[$eid]['seguro']['sin'] : '';
        $con = isset($saved[$eid]['seguro']['con']) ? $saved[$eid]['seguro']['con'] : '';
        $link_sin = isset($saved[$eid]['seguro_links']['sin']) ? $saved[$eid]['seguro_links']['sin'] : '';
        $link_con = isset($saved[$eid]['seguro_links']['con']) ? $saved[$eid]['seguro_links']['con'] : '';

        if ($link_sin === '' && $link_con === '') {
            $legacy = get_post_meta($eid, '_mv_seguro_policy_url', true);
            if (is_string($legacy) && $legacy !== '') {
                $link_sin = $legacy;
                $link_con = $legacy;
            }
        }

        echo '<tr>';
        echo '<td>'.esc_html($title).' <em style="opacity:.7">('.esc_html__('Seguro de Viaje','manaslu').')</em></td>';
        echo '<td>'
            . '<input type="number" step="0.01" min="0" style="width:100%" name="mv_seguro_price_sin['.esc_attr($eid).']" value="'.esc_attr($sin).'">'
            . '<input type="url" style="width:100%;margin-top:6px" placeholder="https://..." name="mv_seguro_link_sin['.esc_attr($eid).']" value="'.esc_attr($link_sin).'">'
            . '<span class="description">'.esc_html__('Enlace del botón para “Sin cancelación”.', 'manaslu').'</span>'
        . '</td>';
        echo '<td>'
            . '<input type="number" step="0.01" min="0" style="width:100%" name="mv_seguro_price_con['.esc_attr($eid).']" value="'.esc_attr($con).'">'
            . '<input type="url" style="width:100%;margin-top:6px" placeholder="https://..." name="mv_seguro_link_con['.esc_attr($eid).']" value="'.esc_attr($link_con).'">'
            . '<span class="description">'.esc_html__('Enlace del botón para “Con cancelación”.', 'manaslu').'</span>'
        . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if (count($seguros) > 1) {
        echo '<p style="margin-top:8px;color:#666">'.esc_html__('Tienes más de un “Seguro de viaje” asignado a este producto. El frontal permite uno por tarjeta; usa un shortcode por cada seguro si quieres mostrarlos por separado.', 'manaslu').'</p>';
    }

    echo '</div></div>';
});

/** Guardado de precios por producto (incluye Seguro) */
add_action('woocommerce_admin_process_product_object', function ($product) {
    $reg = isset($_POST['mv_extra_price_reg'])  && is_array($_POST['mv_extra_price_reg'])  ? wp_unslash($_POST['mv_extra_price_reg'])  : [];
    $sal = isset($_POST['mv_extra_price_sale']) && is_array($_POST['mv_extra_price_sale']) ? wp_unslash($_POST['mv_extra_price_sale']) : [];
    $seg_sin = isset($_POST['mv_seguro_price_sin']) && is_array($_POST['mv_seguro_price_sin']) ? wp_unslash($_POST['mv_seguro_price_sin']) : [];
    $seg_con = isset($_POST['mv_seguro_price_con']) && is_array($_POST['mv_seguro_price_con']) ? wp_unslash($_POST['mv_seguro_price_con']) : [];
    $seg_link_sin = isset($_POST['mv_seguro_link_sin']) && is_array($_POST['mv_seguro_link_sin']) ? wp_unslash($_POST['mv_seguro_link_sin']) : [];
    $seg_link_con = isset($_POST['mv_seguro_link_con']) && is_array($_POST['mv_seguro_link_con']) ? wp_unslash($_POST['mv_seguro_link_con']) : [];
    $inc = isset($_POST['mv_included_extras']) && is_array($_POST['mv_included_extras']) ? array_map('intval', $_POST['mv_included_extras']) : [];
    $inc = array_values(array_filter($inc, function($eid){
        $is_seg = function_exists('mv_is_seguro_extra') && mv_is_seguro_extra($eid);
        $is_ind = function_exists('mv_is_individual_extra') && mv_is_individual_extra($eid);
        $is_per = function_exists('mv_is_personas_extra') && mv_is_personas_extra($eid);
        return !$is_seg && !$is_ind && !$is_per;
    }));

    $sc_order_raw  = isset($_POST['mv_sc_cat_order']) ? wp_unslash($_POST['mv_sc_cat_order']) : '';
    $sc_labels_raw = isset($_POST['mv_sc_cat_label']) && is_array($_POST['mv_sc_cat_label']) ? wp_unslash($_POST['mv_sc_cat_label']) : [];
    $sc_reset_flag = !empty($_POST['mv_sc_cat_reset']);

    $has_sc_payload = $sc_reset_flag;
    if (!$has_sc_payload && $sc_order_raw !== '') {
        $has_sc_payload = true;
    }
    if (!$has_sc_payload && !empty($sc_labels_raw)) {
        foreach ($sc_labels_raw as $raw_label) {
            if (trim((string)$raw_label) !== '') {
                $has_sc_payload = true;
                break;
            }
        }
    }

    $has_fecha_post = isset($_POST['_viaje_fechas_extras']) && is_array($_POST['_viaje_fechas_extras']);
    if (empty($reg) && empty($sal) && empty($seg_sin) && empty($seg_con) && empty($seg_link_sin) && empty($seg_link_con) && !$has_fecha_post && !$has_sc_payload) return;

    $map = [];
    $all_ids = array_unique(array_merge(
        array_keys($reg),
        array_keys($sal),
        array_keys($seg_sin),
        array_keys($seg_con),
        array_keys($seg_link_sin),
        array_keys($seg_link_con)
    ));
    foreach ($all_ids as $eid) {
        $entry = [];

        // Si el extra usa precio dinámico por % no guardamos precio fijo por producto
        $pct = get_post_meta((int)$eid, '_mv_pct_descuento', true);
        $is_dyn = is_numeric($pct) && (float)$pct > 0;

        if (!$is_dyn) {
            if (isset($reg[$eid]) || isset($sal[$eid])) {
                $r = isset($reg[$eid]) ? wc_format_decimal($reg[$eid]) : '';
                $s = isset($sal[$eid]) ? wc_format_decimal($sal[$eid]) : '';
                $entry['regular'] = ($r !== '') ? $r : '';
                $entry['sale']    = ($s !== '') ? $s : '';
            }
        }

        if (isset($seg_sin[$eid]) || isset($seg_con[$eid]) || isset($seg_link_sin[$eid]) || isset($seg_link_con[$eid])) {
            $sin = isset($seg_sin[$eid]) ? wc_format_decimal($seg_sin[$eid]) : '';
            $con = isset($seg_con[$eid]) ? wc_format_decimal($seg_con[$eid]) : '';
            $entry['seguro'] = ['sin' => ($sin!==''?$sin:''), 'con' => ($con!==''?$con:'')];

            $link_sin_val = '';
            if (isset($seg_link_sin[$eid])) {
                $link_sin_raw = trim((string)$seg_link_sin[$eid]);
                $link_sin_val = $link_sin_raw !== '' ? esc_url_raw($link_sin_raw) : '';
            }

            $link_con_val = '';
            if (isset($seg_link_con[$eid])) {
                $link_con_raw = trim((string)$seg_link_con[$eid]);
                $link_con_val = $link_con_raw !== '' ? esc_url_raw($link_con_raw) : '';
            }

            $entry['seguro_links'] = ['sin' => $link_sin_val, 'con' => $link_con_val];
        }

        $map[(int)$eid] = $entry;
    }
    update_post_meta($product->get_id(), 'mv_extra_prices', $map);

    // Guardar "Incluye" a nivel de producto
    update_post_meta($product->get_id(), 'mv_included_extras', $inc);

    // Guardar datos enviados desde el panel "Personas (por fecha)"
    if (isset($_POST['_viaje_fechas_extras']) && is_array($_POST['_viaje_fechas_extras'])) {
        $pid = (int)$product->get_id();
        $posted = $_POST['_viaje_fechas_extras'];

        $by_idx = get_post_meta($pid, '_viaje_fechas_extras', true); $by_idx = is_array($by_idx) ? $by_idx : [];
        $fechas = function_exists('mv_get_fechas') ? mv_get_fechas($pid) : [];

        foreach ($posted as $idx => $row) {
            $idx = (int)$idx; if (!isset($by_idx[$idx]) || !is_array($by_idx[$idx])) $by_idx[$idx] = [];
            $r = isset($fechas[$idx]['precio_normal']) ? (float)$fechas[$idx]['precio_normal'] : 0.0;
            $s = isset($fechas[$idx]['precio_rebajado']) ? (float)$fechas[$idx]['precio_rebajado'] : 0.0;
            $base = ($s > 0 && ($r == 0 || $s < $r)) ? $s : $r;
            foreach ($row as $eid => $vals) {
                $eid = (int)$eid; $vals = is_array($vals) ? $vals : [];
                $title = strtolower(trim(get_the_title($eid)));
                $is_adult = (strpos($title, 'adult') !== false);
                $is_child = (strpos($title, 'niñ') !== false) || (strpos($title, 'child') !== false) || (strpos($title, 'kids') !== false);
                $included = !empty($vals['included']) ? 1 : 0;
                if ($is_adult) {
                    $val = (float)$base; // Adulto = precio base de la fecha
                    $by_idx[$idx][$eid] = ['regular'=>$val,'sale'=>$val,'included'=>$included];
                } elseif ($is_child) {
                    $pct_val = isset($vals['pct']) && $vals['pct'] !== '' ? (float)$vals['pct'] : 0.0;
                    if ($pct_val < 0) $pct_val = 0.0; if ($pct_val > 100) $pct_val = 100.0;
                    // If no pct provided and previous pct exists, retain previous pct and value
                    if ($pct_val === 0.0 && isset($by_idx[$idx][$eid]['pct'])) {
                        $pct_val = (float)$by_idx[$idx][$eid]['pct'];
                        $val = (float)$base * (1.0 - ($pct_val/100.0));
                        $val = (float)wc_format_decimal($val);
                        $by_idx[$idx][$eid]['regular'] = $val;
                        $by_idx[$idx][$eid]['sale']    = $val;
                        $by_idx[$idx][$eid]['included'] = $included;
                        $by_idx[$idx][$eid]['pct']      = $pct_val;
                        continue;
                    }
                    $val = (float)$base * (1.0 - ($pct_val/100.0));
                    $val = (float)wc_format_decimal($val);
                    $by_idx[$idx][$eid] = [
                        'regular'  => $val,
                        'sale'     => $val,
                        'included' => $included,
                        'pct'      => $pct_val,
                    ];
                } else {
                    $reg = isset($vals['regular']) && $vals['regular'] !== '' ? wc_format_decimal($vals['regular']) : '';
                    $sal = isset($vals['sale'])    && $vals['sale']    !== '' ? wc_format_decimal($vals['sale'])    : '';
                    $by_idx[$idx][$eid] = ['regular'=>$reg,'sale'=>$sal,'included'=>$included];
                }
            }
        }
        update_post_meta($pid, '_viaje_fechas_extras', $by_idx);
    }

    // >>> Auto-popular precios por FECHA para categoría "Personas" (Adultos/Niños)
    // Deja funcional aunque el UI del repetidor no los liste; no sobreescribe valores existentes.
    if (function_exists('mv_get_fechas')) {
        $pid = (int) $product->get_id();
        $fechas = mv_get_fechas($pid);
        if (is_array($fechas) && !empty($fechas)) {
            $asig = get_post_meta($pid, 'extras_asignados', true);
            $asig = is_array($asig) ? array_map('intval', $asig) : [];
            // Extras de la categoría Personas
            $personas = array_values(array_filter($asig, function($eid){
                return function_exists('mv_is_personas_extra') && mv_is_personas_extra($eid);
            }));

            if (!empty($personas)) {
                $by_idx = get_post_meta($pid, '_viaje_fechas_extras', true);
                $by_idx = is_array($by_idx) ? $by_idx : [];

                foreach ($fechas as $idx => $row) {
                    $r = isset($row['precio_normal'])   ? (float)$row['precio_normal']   : 0.0;
                    $s = isset($row['precio_rebajado']) ? (float)$row['precio_rebajado'] : 0.0;
                    $base = ($s > 0 && ($r == 0 || $s < $r)) ? $s : $r; // Rebajado tiene prioridad si es menor

                    if (!isset($by_idx[$idx]) || !is_array($by_idx[$idx])) $by_idx[$idx] = [];

                    foreach ($personas as $eid) {
                        $eid = (int)$eid;
                        // No sobrescribir si ya hay un valor para esta fecha/extra
                        if (!isset($by_idx[$idx][$eid]) || !is_array($by_idx[$idx][$eid]) || (empty($by_idx[$idx][$eid]['regular']) && empty($by_idx[$idx][$eid]['sale']))) {
                            $title = strtolower(trim(get_the_title($eid)));
                            if (strpos($title, 'adult') !== false) {
                                // Adulto: read-only en UI y aquí forzamos a seguir el precio base de la fecha
                                $val = (float)$base;
                                $by_idx[$idx][$eid] = [
                                    'regular'  => $val,
                                    'sale'     => $val,
                                    'included' => isset($by_idx[$idx][$eid]['included']) ? (int)$by_idx[$idx][$eid]['included'] : 0,
                                ];
                            } else {
                                // Niños u otros dentro de Personas: no prellenamos precio fijo.
                                // Se calcularán dinámicamente por % si el extra tiene _mv_pct_descuento.
                                if (!isset($by_idx[$idx][$eid])) {
                                    $by_idx[$idx][$eid] = ['included' => 0];
                                }
                            }
                        }
                    }
                }
                update_post_meta($pid, '_viaje_fechas_extras', $by_idx);
            }
        }
    }

    if ($sc_reset_flag) {
        delete_post_meta($product->get_id(), 'mv_sc_extra_cat_order');
        delete_post_meta($product->get_id(), 'mv_sc_extra_cat_labels');
    } else {
        $order_ids = [];
        if (is_string($sc_order_raw) && $sc_order_raw !== '') {
            $chunks = array_filter(array_map('trim', explode(',', $sc_order_raw)), 'strlen');
            foreach ($chunks as $chunk) {
                $tid = absint($chunk);
                if ($tid) $order_ids[] = $tid;
            }
            $order_ids = array_values(array_unique($order_ids));
        }

        if (!empty($order_ids)) {
            update_post_meta($product->get_id(), 'mv_sc_extra_cat_order', $order_ids);
        } else {
            delete_post_meta($product->get_id(), 'mv_sc_extra_cat_order');
        }

        $labels_clean = [];
        if (!empty($sc_labels_raw) && is_array($sc_labels_raw)) {
            foreach ($sc_labels_raw as $term_id => $label) {
                $term_id = absint($term_id);
                $label = sanitize_text_field($label);
                if ($term_id && $label !== '') {
                    $labels_clean[$term_id] = $label;
                }
            }
        }

        if (!empty($labels_clean)) {
            update_post_meta($product->get_id(), 'mv_sc_extra_cat_labels', $labels_clean);
        } else {
            delete_post_meta($product->get_id(), 'mv_sc_extra_cat_labels');
        }
    }
}, 999); // high priority to run after other meta savers (merge Niños % into _viaje_fechas_extras)

/* ============================================================
 * 4) Precios helpers (producto y por FECHA)
 * ============================================================ */

/**
 * Devuelve el precio vigente del extra para un producto (por producto).
 * Para Seguro de Viaje, pasar $variant = 'con' | 'sin'.
 */
function mv_product_extra_price($product_id, $extra_id, $variant = null) {
    $product_id = (int) $product_id;
    $extra_id   = (int) $extra_id;

    // Mapa por producto (fallback y seguro)
    $map = get_post_meta($product_id, 'mv_extra_prices', true);
    $map = is_array($map) ? $map : [];
    $entry = isset($map[$extra_id]) ? $map[$extra_id] : [];

    // 1) Seguro de viaje: siempre por producto + variante
    if ($variant && isset($entry['seguro']) && is_array($entry['seguro'])) {
        $val = ($variant === 'con') ? (float)($entry['seguro']['con'] ?? 0) : (float)($entry['seguro']['sin'] ?? 0);
        return max(0.0, $val);
    }

    // 3) Para "Individual" y "Personas": intentar precio por FECHA guardado en el repetidor
    if ((function_exists('mv_is_individual_extra') && mv_is_individual_extra($extra_id))
        || (function_exists('mv_is_personas_extra') && mv_is_personas_extra($extra_id))) {
        $idx = function_exists('mv_cart_selected_idx_local') ? mv_cart_selected_idx_local($product_id) : -1;
        if ($idx >= 0) {
            $by_idx = get_post_meta($product_id, '_viaje_fechas_extras', true);
            $by_idx = is_array($by_idx) ? $by_idx : [];
            if (isset($by_idx[$idx][$extra_id])) {
                $r = isset($by_idx[$idx][$extra_id]['regular']) ? (float)$by_idx[$idx][$extra_id]['regular'] : 0.0;
                $s = isset($by_idx[$idx][$extra_id]['sale'])    ? (float)$by_idx[$idx][$extra_id]['sale']    : 0.0;
                if ($s > 0 && ($r == 0 || $s < $r)) return $s;
                if ($r > 0) return $r;
            }
        }
    }

    // 4) Fallback por producto
    $reg  = isset($entry['regular']) ? (float)$entry['regular'] : 0.0;
    $sale = isset($entry['sale'])    ? (float)$entry['sale']    : 0.0;
    if ($sale > 0 && ($reg == 0 || $sale < $reg)) return $sale;
    if ($reg  > 0) return $reg;
    return 0.0;
}

/**
 * Devuelve el enlace configurado para el Seguro de Viaje según variante.
 * Fallback a otra variante o al meta antiguo del CPT si no se definió.
 */
function mv_product_seguro_link($product_id, $extra_id, $variant = 'sin') {
    $product_id = (int) $product_id;
    $extra_id   = (int) $extra_id;
    $variant    = ($variant === 'con') ? 'con' : 'sin';

    $map   = get_post_meta($product_id, 'mv_extra_prices', true);
    $map   = is_array($map) ? $map : [];
    $entry = isset($map[$extra_id]) ? $map[$extra_id] : [];
    $links = isset($entry['seguro_links']) && is_array($entry['seguro_links']) ? $entry['seguro_links'] : [];

    $link = isset($links[$variant]) ? trim((string)$links[$variant]) : '';
    if ($link === '') {
        $other_variant = ($variant === 'con') ? 'sin' : 'con';
        if (isset($links[$other_variant]) && trim((string)$links[$other_variant]) !== '') {
            $link = trim((string)$links[$other_variant]);
        }
    }

    if ($link === '') {
        $legacy = get_post_meta($extra_id, '_mv_seguro_policy_url', true);
        if (is_string($legacy) && $legacy !== '') {
            $link = $legacy;
        }
    }

    return $link !== '' ? esc_url_raw($link) : '';
}

/** Wrapper retrocompatible: mv_extra_price($extra_id[, $product_id]) */
function mv_extra_price($extra_id, $maybe_product_id = null) {
    $pid = $maybe_product_id ?: mv_resolve_product_id();
    return mv_product_extra_price($pid, $extra_id);
}

/** === NUEVO: helpers de precio por FECHA === */

/** Índice de fecha actualmente seleccionada en el carrito para un producto viaje */
function mv_cart_selected_idx_for_product($product_id){
    if (!function_exists('WC') || !WC()->cart) return -1;
    foreach (WC()->cart->get_cart() as $ci) {
        if (!empty($ci['product_id']) && (int)$ci['product_id']===(int)$product_id && isset($ci['viaje_fecha']['idx'])) {
            return (int)$ci['viaje_fecha']['idx'];
        }
    }
    return -1;
}

/** Precio del extra para un índice de fecha concreto (solo extras “normales”) */
function mv_product_extra_price_for_idx($product_id, $extra_id, $idx, $variant = null){
    // Seguros mantienen su precio por producto (variant 'con'/'sin')
    if (mv_is_seguro_extra($extra_id)) {
        return mv_product_extra_price($product_id, $extra_id, $variant);
    }
    $rows = get_post_meta($product_id, '_viaje_fechas_extras', true);
    if (is_array($rows) && isset($rows[(int)$idx]) && isset($rows[(int)$idx][(int)$extra_id])) {
        $e = $rows[(int)$idx][(int)$extra_id];
        $reg  = isset($e['regular']) ? (float)$e['regular'] : 0.0;
        $sale = isset($e['sale'])    ? (float)$e['sale']    : 0.0;
        if ($sale > 0 && ($reg == 0 || $sale < $reg)) return $sale;
        if ($reg  > 0) return $reg;
    }
    // Fallback a precio por producto
    return mv_product_extra_price($product_id, $extra_id, $variant);
}

/* ============================================================
 * 5) Carrito: agrupación de extras bajo la línea del viaje
 * ============================================================ */

function mv_find_target_cart_item_key($product_id){
    if (!WC()->cart) return '';
    $fallback = '';
    $target   = '';
    foreach (WC()->cart->get_cart() as $key => $item) {
        if ((int)$item['product_id'] !== (int)$product_id) continue;
        $fallback = $key;
        if (isset($item['viaje_fecha'])) $target = $key;
    }
    return $target ?: $fallback;
}

function mv_cart_qty_for_extra($product_id, $extra_id){
    if (!WC()->cart) return 0;
    $key = mv_find_target_cart_item_key($product_id);
    if (!$key) return 0;
    $cart = WC()->cart->get_cart();
    if (!isset($cart[$key])) return 0;
    $map = isset($cart[$key]['viaje_extras']) && is_array($cart[$key]['viaje_extras']) ? $cart[$key]['viaje_extras'] : [];
    return isset($map[$extra_id]['qty']) ? (int)$map[$extra_id]['qty'] : 0;
}

// Seguro de Viaje: obtener variante seleccionada (si existe en el carrito)
function mv_cart_seguro_variant($product_id, $extra_id){
    if (!WC()->cart) return '';
    $key = mv_find_target_cart_item_key($product_id);
    if (!$key) return '';
    $cart = WC()->cart->get_cart();
    if (!isset($cart[$key])) return '';
    $map = isset($cart[$key]['viaje_extras']) && is_array($cart[$key]['viaje_extras']) ? $cart[$key]['viaje_extras'] : [];
    if (isset($map[$extra_id]) && isset($map[$extra_id]['variant'])) return ($map[$extra_id]['variant']==='con'?'con':'sin');
    return '';
}

add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    if (!empty($cart_item['viaje_extras']) && is_array($cart_item['viaje_extras'])) {
        foreach ($cart_item['viaje_extras'] as $x) {
            $qty = isset($x['qty']) ? (int)$x['qty'] : 0;
            if ($qty <= 0) continue;

            $title    = $x['title'] ?? __('Extra','manaslu');
            $price    = isset($x['price']) ? (float)$x['price'] : 0.0;
            $included = isset($x['included']) ? (int)$x['included'] : 0;
            if ($included < 0) $included = 0;

            $charge_qty = max(0, $qty - $included);

            if ($charge_qty > 0) {
                $total_price = wc_price($price * $charge_qty);
                $label = sprintf('%s × %d — %s', $title, $charge_qty, $total_price);
                $item_data[] = ['name' => __('Extra', 'manaslu'), 'value' => $label];
            } elseif ($included > 0) {
                $label = sprintf('%s (%s)', $title, __('incluido', 'manaslu'));
                $item_data[] = ['name' => __('Incluido', 'manaslu'), 'value' => $label];
            }
        }
    }
    return $item_data;
}, 10, 2);

/**
 * Recalcular precio de la línea del viaje = base + extras
 * (Actualiza, además, el precio unitario de cada extra según la FECHA seleccionada)
 */
add_action('woocommerce_before_calculate_totals', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $key => $item) {
        if (!isset($item['data']) || !is_object($item['data'])) continue;

        $base_price = (float) $item['data']->get_price();
        $extras_total = 0.0;

        if (empty($item['viaje_extras']) || !is_array($item['viaje_extras'])) {
            continue;
        }

        $product_id = (int)($item['product_id'] ?? 0);
        $idx = isset($item['viaje_fecha']['idx']) ? (int)$item['viaje_fecha']['idx'] : -1;

        foreach ($item['viaje_extras'] as $eid => $x) {
            $eid_i = (int)$eid;
            $qty = max(0, (int)($x['qty'] ?? 0));

            $baseline = $product_id ? (int) mv_extra_should_be_included($product_id, $eid_i, $idx) : 0;
            $included_units = $baseline > 0 ? $baseline : 0;
            if ($included_units > 0 && $qty < $included_units) {
                $qty = $included_units;
            }

            $is_seguro = function_exists('mv_is_seguro_extra') ? mv_is_seguro_extra($eid_i) : false;
            $price_each = isset($x['price']) ? (float)$x['price'] : 0.0;

            if ($is_seguro) {
                $variant = $x['variant'] ?? null;
                if (function_exists('mv_product_extra_price')) {
                    $price_each = (float) mv_product_extra_price($product_id, $eid_i, $variant);
                }
            } else {
                if ($idx >= 0 && function_exists('mv_product_extra_price_for_idx')) {
                    $price_each = (float) mv_product_extra_price_for_idx($product_id, $eid_i, $idx);
                } elseif (function_exists('mv_product_extra_price')) {
                    $price_each = (float) mv_product_extra_price($product_id, $eid_i, null);
                }
            }

            $cart->cart_contents[$key]['viaje_extras'][$eid]['qty'] = $qty;
            $cart->cart_contents[$key]['viaje_extras'][$eid]['included'] = $included_units;
            $cart->cart_contents[$key]['viaje_extras'][$eid]['price'] = $price_each;

            $charge_qty = max(0, $qty - $included_units);
            $extras_total += $charge_qty * $price_each;
        }

        $item['data']->set_price($base_price + $extras_total);
    }
}, 25);

/* ============================================================
 * 6) AJAX ± (extras normales) y SET para Seguro de Viaje
 * ============================================================ */
add_action('wp_ajax_viaje_extra_add', 'mv_ajax_extra_add');
add_action('wp_ajax_nopriv_viaje_extra_add', 'mv_ajax_extra_add');
function mv_ajax_extra_add(){
    check_ajax_referer('mv_extras_nonce', 'nonce');
    if (!function_exists('WC') || !WC()->cart) wp_send_json_error(['message'=>'Cart not available']);
    $pid = isset($_POST['pid']) ? absint($_POST['pid']) : 0;
    // Accept both 'eid' and legacy 'extra_id'
    $eid = isset($_POST['eid']) ? absint($_POST['eid']) : (isset($_POST['extra_id']) ? absint($_POST['extra_id']) : 0);
    if(!$pid || !$eid) wp_send_json_error(['message'=>'Params missing']);

    foreach (WC()->cart->get_cart() as $key=>$ci) {
        if ((int)($ci['product_id'] ?? 0) !== $pid) continue;

        $map = isset($ci['viaje_extras']) && is_array($ci['viaje_extras']) ? $ci['viaje_extras'] : [];
        $idx = isset($ci['viaje_fecha']['idx']) ? (int)$ci['viaje_fecha']['idx'] : -1;
        $included_flag = mv_extra_should_be_included($pid, $eid, $idx);
        $title = get_the_title($eid);

        // Precio live (preferir por FECHA)
        $live = 0.0;
        if ($idx >= 0 && function_exists('mv_product_extra_price_for_idx')) {
            $live = (float) mv_product_extra_price_for_idx($pid, $eid, $idx);
        } elseif (function_exists('mv_product_extra_price')) {
            $live = (float) mv_product_extra_price($pid, $eid, null);
        }

        $created = false;
        if (!isset($map[$eid])) {
            $seed_qty = $included_flag > 0 ? (int)$included_flag : 0;
            $map[$eid] = [
                'extra_id' => $eid,
                'title'    => $title,
                'price'    => $live,
                'qty'      => $seed_qty,
                'included' => $seed_qty,
            ];
            $created = true;
        }

        $current_included = max(0, (int)($map[$eid]['included'] ?? 0));
        if ($included_flag > 0) {
            $current_included = max($current_included, (int)$included_flag);
        }

        $current_qty = max(0, (int)($map[$eid]['qty'] ?? 0));
        if ($current_included > 0 && $current_qty < $current_included) {
            $current_qty = $current_included;
        }

        // Si acabamos de crear el baseline incluido, devolvemos sin sumar
        if ($created && $current_included > 0 && $current_qty === $current_included) {
            $map[$eid]['qty'] = $current_qty;
            $map[$eid]['included'] = $current_included;
            $map[$eid]['price'] = $live;
            $map[$eid]['title'] = $title;

            WC()->cart->cart_contents[$key]['viaje_extras'] = $map;
            WC()->cart->set_session();
            WC()->cart->calculate_totals();
            wp_send_json_success([
                'qty'        => $current_qty,
                'included'   => $current_included,
                'price'      => $live,
                'price_html' => function_exists('wc_price') ? wc_price($live) : $live,
            ]);
        }

        $new_qty = $current_qty + 1;
        if ($current_included > 0 && $new_qty < $current_included) {
            $new_qty = $current_included;
        }

        $map[$eid]['qty'] = $new_qty;
        $map[$eid]['included'] = $current_included;
        $map[$eid]['price'] = $live;
        $map[$eid]['title'] = $title;

        WC()->cart->cart_contents[$key]['viaje_extras'] = $map;
        WC()->cart->set_session();
        WC()->cart->calculate_totals();
        wp_send_json_success([
            'qty'        => $new_qty,
            'included'   => $current_included,
            'price'      => $live,
            'price_html' => function_exists('wc_price') ? wc_price($live) : $live,
        ]);
    }

    wp_send_json_error(['message'=>'Cart line not found']);
}

add_action('wp_ajax_viaje_extra_sub', 'mv_ajax_extra_sub');
add_action('wp_ajax_nopriv_viaje_extra_sub', 'mv_ajax_extra_sub');
function mv_ajax_extra_sub(){
    check_ajax_referer('mv_extras_nonce', 'nonce');
    if (!function_exists('WC') || !WC()->cart) wp_send_json_error(['message'=>'Cart not available']);
    $pid = isset($_POST['pid']) ? absint($_POST['pid']) : 0;
    // Accept both 'eid' and legacy 'extra_id'
    $eid = isset($_POST['eid']) ? absint($_POST['eid']) : (isset($_POST['extra_id']) ? absint($_POST['extra_id']) : 0);
    if(!$pid || !$eid) wp_send_json_error(['message'=>'Params missing']);

    foreach (WC()->cart->get_cart() as $key=>$ci) {
        if ((int)($ci['product_id'] ?? 0) !== $pid) continue;

        $map = isset($ci['viaje_extras']) && is_array($ci['viaje_extras']) ? $ci['viaje_extras'] : [];
        $idx = isset($ci['viaje_fecha']['idx']) ? (int)$ci['viaje_fecha']['idx'] : -1;
        $baseline = mv_extra_should_be_included($pid, $eid, $idx);
        $title = get_the_title($eid);

        $live = 0.0;
        if ($idx >= 0 && function_exists('mv_product_extra_price_for_idx')) {
            $live = (float) mv_product_extra_price_for_idx($pid, $eid, $idx);
        } elseif (function_exists('mv_product_extra_price')) {
            $live = (float) mv_product_extra_price($pid, $eid, null);
        }

        if (!isset($map[$eid])) {
            if ($baseline > 0) {
                $map[$eid] = [
                    'extra_id' => $eid,
                    'title'    => $title,
                    'price'    => $live,
                    'qty'      => (int)$baseline,
                    'included' => (int)$baseline,
                ];

                WC()->cart->cart_contents[$key]['viaje_extras'] = $map;
                WC()->cart->set_session();
                WC()->cart->calculate_totals();
                wp_send_json_success([
                    'qty'        => (int)$baseline,
                    'included'   => (int)$baseline,
                    'price'      => $live,
                    'price_html' => function_exists('wc_price') ? wc_price($live) : $live,
                ]);
            }

            wp_send_json_success(['qty'=>0, 'included'=>0]);
        }

        $current_included = max(0, (int)($map[$eid]['included'] ?? 0));
        if ($baseline > 0) {
            $current_included = max($current_included, (int)$baseline);
        }

        $current_qty = max(0, (int)($map[$eid]['qty'] ?? 0));
        if ($current_included > 0 && $current_qty < $current_included) {
            $current_qty = $current_included;
        }

        $new_qty = max($current_included, $current_qty - 1);

        if ($new_qty === 0 && $current_included === 0) {
            unset($map[$eid]);
        } else {
            $map[$eid]['qty'] = $new_qty;
            $map[$eid]['included'] = $current_included;
            $map[$eid]['price'] = $live;
            $map[$eid]['title'] = $title;
        }

        WC()->cart->cart_contents[$key]['viaje_extras'] = $map;
        WC()->cart->set_session();
        WC()->cart->calculate_totals();
        wp_send_json_success([
            'qty'        => isset($map[$eid]) ? (int)$map[$eid]['qty'] : 0,
            'included'   => $current_included,
            'price'      => $live,
            'price_html' => function_exists('wc_price') ? wc_price($live) : $live,
        ]);
    }

    wp_send_json_error(['message'=>'Cart line not found']);
}
// >>> Seguro de Viaje: set (on/off + variante) — mantiene precio por producto
add_action('wp_ajax_viaje_seguro_set', 'mv_ajax_seguro_set');
add_action('wp_ajax_nopriv_viaje_seguro_set', 'mv_ajax_seguro_set');
function mv_ajax_seguro_set(){
    check_ajax_referer('mv_extras_nonce', 'nonce');
    $extra_id   = absint($_POST['extra_id'] ?? 0);
    $product_id = absint($_POST['pid'] ?? mv_resolve_product_id());
    $variant    = sanitize_text_field($_POST['variant'] ?? 'sin'); // 'con'|'sin'
    $enabled    = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 1;

    if (!$extra_id || !$product_id) wp_send_json_error(['message' => 'Datos insuficientes']);
    if ($variant !== 'con' && $variant !== 'sin') $variant = 'sin';

    $key = mv_find_target_cart_item_key($product_id);
    if (!$key) {
        wp_send_json_error(['message' => __('Primero añade el viaje al carrito (elige una fecha).','manaslu')]);
    }

    $cart = WC()->cart->get_cart();
    if (!isset($cart[$key])) wp_send_json_error(['message' => 'Carrito inválido']);

    $title_base = get_the_title($extra_id);
    $variant_label = ($variant==='con') ? __('Con cancelación','manaslu') : __('Sin cancelación','manaslu');

    // Seguro: precio por producto (no dependiente de fecha)
    $price = mv_product_extra_price($product_id, $extra_id, $variant);

    $map = isset($cart[$key]['viaje_extras']) && is_array($cart[$key]['viaje_extras']) ? $cart[$key]['viaje_extras'] : [];

    if ($enabled) {
        $map[$extra_id] = [
            'extra_id' => $extra_id,
            'title'    => $title_base . ' (' . $variant_label . ')',
            'price'    => $price,
            'qty'      => 1,
            'variant'  => $variant,
            'cat'      => 'seguro'
        ];
    } else {
        if (isset($map[$extra_id])) unset($map[$extra_id]);
    }

    WC()->cart->cart_contents[$key]['viaje_extras'] = $map;
    WC()->cart->set_session();

    wp_send_json_success([
        'enabled' => (int)$enabled,
        'variant' => $variant,
        'price'   => wc_price($price),
    ]);
}

/* ============================================================
 * 7) Estilos/JS front (UI ± y Seguro)
 * ============================================================ */
add_action('wp_enqueue_scripts', function(){
    wp_enqueue_style(
        'host-grotesk',
        'https://fonts.googleapis.com/css2?family=Host+Grotesk:wght@400;500;600;700&display=swap',
        [],
        null
    );
});

add_action('wp_head', function(){ ?>
<style>
.vx-extras-root{font-family:'Host Grotesk',system-ui,-apple-system,'Segoe UI',Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif;color:#000}
.vx-extras-block{margin:1.25rem 0}
.vx-extras-heading{font-weight:700;margin:0 0 .75rem;color:#000}
.vx-extras-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
@media (max-width:768px){.vx-extras-grid{grid-template-columns:1fr}}
.vx-extra{display:flex;align-items:center;justify-content:space-between;padding:14px;border-radius:12px;background:#fff;border:1px solid #e5e7eb}
.vx-extra .vx-title{font-size:.95rem;font-weight:600;color:#000}
.vx-extra .vx-price{opacity:.9;margin-left:6px;color:#000}
.vx-inc-note{margin-left:8px;font-size:.75rem;font-weight:600;color:#2563eb;white-space:nowrap}
.vx-qty{display:flex;align-items:center;gap:8px}
.vx-btn{border:0;border-radius:999px;width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;background:#e21;color:#fff;cursor:pointer;line-height:1;font-weight:700}
.vx-btn:disabled{opacity:.6;cursor:not-allowed}
.vx-count{min-width:20px;text-align:center;display:inline-block;color:#000}
.vx-sep{border:0;border-top:1px solid #e5e7eb;margin:18px 0}

/* >>> Seguro de Viaje */
.vx-seguro-card{display:grid;grid-template-columns:180px 1fr;gap:18px;align-items:center;border:1px solid #e5e7eb;border-radius:12px;padding:16px;background:#fff}
@media (max-width:720px){.vx-seguro-card{grid-template-columns:1fr}}
.vx-seguro-img img{width:100%;height:auto;border-radius:10px;display:block}
.vx-seguro-title{font-weight:700;font-size:18px;margin:0 0 4px}
.vx-seguro-desc{opacity:.9;margin:0 0 10px}
.vx-seguro-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
.vx-seguro-select{min-width:220px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
.vx-seguro-btn{border:0;border-radius:999px;padding:10px 18px;font-weight:700;background:#e3172d;color:#fff;cursor:pointer}
.vx-seguro-btn.is-on{background:#21a65b}
.vx-seguro-price{font-weight:800}
.vx-seguro-actions{display:flex;flex-direction:row;align-items:center;gap:10px}
/* Nuevo: botón secundario para descargar póliza */
.vx-seguro-dl{display:inline-flex;align-items:center;gap:6px;border:1px solid #e5e7eb;border-radius:999px;padding:9px 14px;background:#fff;color:#111;text-decoration:none;font-weight:700}
.vx-seguro-dl:hover{background:#f5f5f5}
@media (max-width:720px){.vx-seguro-row{gap:8px}}
</style>
<?php });

add_action('wp_footer', function(){
    if (is_admin()) return; ?>
<script>
(function(){
    var root=document;
    function syncExtraState(wrap, qty, inc){
        if(!wrap) return;
        var countEl = wrap.querySelector('.vx-count');
        var subBtn  = wrap.querySelector('[data-vx-act="sub"]');
        var addBtn  = wrap.querySelector('[data-vx-act="add"]');

        var q = parseInt(qty, 10); if (isNaN(q)) q = 0;
        var i = parseInt(inc, 10); if (isNaN(i)) i = 0;
        if (i > 0 && q < i) q = i;

        if (countEl) {
            countEl.dataset.qty = String(q);
            countEl.dataset.inc = String(i);
            countEl.textContent = String(q);
        }
        wrap.dataset.qty = String(q);
        wrap.dataset.inc = String(i);

        if (subBtn) subBtn.disabled = q <= i;
        if (addBtn && addBtn.disabled) addBtn.disabled = false;
    }
    function post(action,data,cb){
        var fd=new FormData();
        fd.append('action',action);
        for(var k in data){fd.append(k,data[k]);}
        fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>',{method:'POST',credentials:'same-origin',body:fd})
        .then(r=>r.json()).then(cb).catch(()=>{});
    }

    function updateSeguroLink(card, variant){
        if(!card) return;
        var linkEl = card.querySelector('[data-role="vx-seguro-link"]');
        if(!linkEl) return;
        var linkCon = card.getAttribute('data-link-con') || '';
        var linkSin = card.getAttribute('data-link-sin') || '';
        var href = (variant === 'con') ? linkCon : linkSin;
        if(!href){
            href = (variant === 'con') ? linkSin : linkCon;
        }
        if(href){
            linkEl.style.display = '';
            linkEl.setAttribute('href', href);
            linkEl.setAttribute('target', '_blank');
            linkEl.setAttribute('rel', 'noopener noreferrer');
        } else {
            linkEl.style.display = 'none';
            linkEl.removeAttribute('href');
            linkEl.removeAttribute('target');
            linkEl.removeAttribute('rel');
        }
    }

    // Botones +/- (extras normales)
    root.addEventListener('click',function(e){
        var t=e.target.closest('[data-vx-act]'); if(!t) return;
        if(t.closest('.vx-seguro-card')) return; // el seguro usa su propio handler
        e.preventDefault();
        var wrap=t.closest('.vx-extra');
        var pid=wrap.closest('[data-pid]')?.getAttribute('data-pid')||'';
        var id=wrap.getAttribute('data-extra');
        var cnt=wrap.querySelector('.vx-count');
        var act=t.getAttribute('data-vx-act');
        var nonce=wrap.closest('.vx-extras-root')?.getAttribute('data-nonce')||'';
        var action=(act==='add')?'viaje_extra_add':'viaje_extra_sub';
        t.disabled=true;
        post(action,{extra_id:id,pid:pid,nonce:nonce},function(res){
            t.disabled=false;
            if(res&&res.success&&wrap){
                var fallbackQty = '0';
                if (cnt) {
                    if (cnt.dataset && typeof cnt.dataset.qty !== 'undefined') {
                        fallbackQty = cnt.dataset.qty;
                    } else if (cnt.textContent) {
                        fallbackQty = cnt.textContent;
                    }
                }
                var fallbackInc = 0;
                if (cnt && cnt.dataset && typeof cnt.dataset.inc !== 'undefined') {
                    fallbackInc = cnt.dataset.inc;
                }
                var rawQty = res.data && typeof res.data.qty !== 'undefined' ? res.data.qty : fallbackQty;
                var rawInc = res.data && typeof res.data.included !== 'undefined' ? res.data.included : fallbackInc;
                syncExtraState(wrap, rawQty, rawInc);
                if (res.data && typeof res.data.price_html !== 'undefined') {
                    var priceEl = wrap.querySelector('.vx-price');
                    if(priceEl){ priceEl.innerHTML = ' — ' + res.data.price_html; }
                } else if (res.data && typeof res.data.price !== 'undefined') {
                    wrap.dataset.price = String(res.data.price);
                }
            }
            if(res&&!res.success&&res.data&&res.data.message){alert(res.data.message);}
            if (typeof window.manasluSummaryRefresh === 'function') window.manasluSummaryRefresh();
            if (window.jQuery && jQuery(document.body)) jQuery(document.body).trigger('wc_fragment_refresh');
        });
    });

    // >>> Seguro de Viaje: toggle + variante
    root.addEventListener('click', function(e){
        var btn = e.target.closest('.vx-seguro-btn'); if(!btn) return;
        e.preventDefault();
        var card = btn.closest('.vx-seguro-card');
        var pid  = card.getAttribute('data-pid');
        var id   = card.getAttribute('data-extra');
        var sel  = card.querySelector('.vx-seguro-select');
        var nonce= card.getAttribute('data-nonce') || '';
        var on   = !btn.classList.contains('is-on');
        var variant = (sel && sel.value==='con') ? 'con' : 'sin';

        btn.disabled = true;
        post('viaje_seguro_set', {extra_id:id,pid:pid,variant:variant,enabled:on?1:0,nonce:nonce}, function(res){
            btn.disabled=false;
            if(res && res.success){
                btn.classList.toggle('is-on', !!on);
                btn.textContent = on ? '<?php echo esc_js(__('Quitar','manaslu')); ?>' : '<?php echo esc_js(__('Añadir','manaslu')); ?>';
                var priceEl = card.querySelector('.vx-seguro-price');
                if(priceEl && res.data && res.data.price) priceEl.innerHTML = res.data.price;
                updateSeguroLink(card, variant);
                if (typeof window.manasluSummaryRefresh === 'function') window.manasluSummaryRefresh();
                if (window.jQuery && jQuery(document.body)) jQuery(document.body).trigger('wc_fragment_refresh');
            } else if(res && res.data && res.data.message){
                alert(res.data.message);
            }
        });
    });

    root.addEventListener('change', function(e){
        var sel = e.target.closest('.vx-seguro-select'); if(!sel) return;
        var card = sel.closest('.vx-seguro-card');
        var pid  = card.getAttribute('data-pid');
        var id   = card.getAttribute('data-extra');
        var nonce= card.getAttribute('data-nonce') || '';
        var on   = card.querySelector('.vx-seguro-btn')?.classList.contains('is-on');
        var variant = (sel.value==='con') ? 'con':'sin';

        // Si ya está añadido, cambiar variante en vivo
        if (on) {
            var btn = card.querySelector('.vx-seguro-btn');
            if(btn){ btn.disabled=true; }
            post('viaje_seguro_set', {extra_id:id,pid:pid,variant:variant,enabled:1,nonce:nonce}, function(res){
                if(btn){ btn.disabled=false; }
                if(res && res.success){
                    var priceEl = card.querySelector('.vx-seguro-price');
                    if(priceEl && res.data && res.data.price) priceEl.innerHTML = res.data.price;
                    updateSeguroLink(card, variant);
                    if (typeof window.manasluSummaryRefresh === 'function') window.manasluSummaryRefresh();
                    if (window.jQuery && jQuery(document.body)) jQuery(document.body).trigger('wc_fragment_refresh');
                }
            });
        } else {
            // Solo actualizar precio mostrado (sin tocar carrito)
            var priceCon = card.getAttribute('data-price-con') || '0';
            var priceSin = card.getAttribute('data-price-sin') || '0';
            var priceEl  = card.querySelector('.vx-seguro-price');
            if(priceEl){
                priceEl.innerHTML = (variant==='con') ? priceCon : priceSin;
            }
            updateSeguroLink(card, variant);
        }
    });

    var allExtras = document.querySelectorAll('.vx-extra');
    allExtras.forEach(function(node){
        var cntEl = node.querySelector('.vx-count');
        var qtyAttr = node.getAttribute('data-qty');
        if (!qtyAttr && cntEl && cntEl.textContent) qtyAttr = cntEl.textContent;
        var incAttr = node.getAttribute('data-inc');
        if (!incAttr && cntEl && cntEl.getAttribute('data-inc')) incAttr = cntEl.getAttribute('data-inc');
        syncExtraState(node, qtyAttr || 0, incAttr || 0);
    });

    var seguroCards = document.querySelectorAll('.vx-seguro-card');
    seguroCards.forEach(function(card){
        var sel = card.querySelector('.vx-seguro-select');
        var variant = sel ? ((sel.value === 'con') ? 'con' : 'sin') : 'sin';
        updateSeguroLink(card, variant);
    });
})();
</script>
<?php });

/* ============================================================
 * 8) Shortcodes
 * ============================================================ */

// [viaje_extras category="personas,extras" heading="2. Personas" start="2"]
add_shortcode('viaje_extras', function($atts = []){
    $a = shortcode_atts([
        'product_id' => '',
        'category'   => '',
        'heading'    => '',
        'start'      => '2',
    ], $atts, 'viaje_extras');

    $pid = $a['product_id'] !== '' ? absint($a['product_id']) : mv_resolve_product_id();
    if (!$pid) return '';

    $assigned = get_post_meta($pid, 'extras_asignados', true);
    if (!is_array($assigned) || empty($assigned)) return '';

    $cat_filter = [];
    if ($a['category'] !== '') {
        foreach (explode(',', $a['category']) as $c) { $c=trim($c); if($c!=='') $cat_filter[] = $c; }
    }

    $groups = [];
    foreach ($assigned as $eid) {
        $terms = get_the_terms($eid, 'extra_category');
        if (!$terms || is_wp_error($terms)) {
            $key = 'uncat';
            if (!isset($groups[$key])) $groups[$key] = ['term'=>false,'extras'=>[]];
            $groups[$key]['extras'][] = $eid;
        } else {
            foreach ($terms as $t) {
                $slug = (string)$t->slug;
                $idstr= (string)$t->term_id;
                if ($cat_filter) {
                    $match = in_array($slug, $cat_filter, true) || in_array($idstr, $cat_filter, true);
                    if (!$match) continue;
                }
                if (!isset($groups[$t->term_id])) $groups[$t->term_id] = ['term'=>$t,'extras'=>[]];
                $groups[$t->term_id]['extras'][] = $eid;
            }
        }
    }
    if (empty($groups)) return '';

    $stored_order = get_post_meta($pid, 'mv_sc_extra_cat_order', true);
    $stored_order = is_array($stored_order) ? array_values(array_map('absint', $stored_order)) : [];
    $stored_labels = get_post_meta($pid, 'mv_sc_extra_cat_labels', true);
    $stored_labels = is_array($stored_labels) ? $stored_labels : [];

    // Orden de categorías: si se pasó category="", respeta ese orden; si no, alfabético
    $ordered = [];
    if ($cat_filter) {
        foreach ($cat_filter as $token) {
            foreach ($groups as $key => $g) {
                $match = $g['term'] && ($g['term']->slug === $token || (string)$g['term']->term_id === (string)$token);
                if ($match) { $ordered[$key] = $g; unset($groups[$key]); }
            }
        }
        foreach ($groups as $key=>$g) { $ordered[$key] = $g; }
    } else {
        $ordered = $groups;
        uasort($ordered, function($A,$B){
            $an = $A['term'] ? $A['term']->name : 'Extras';
            $bn = $B['term'] ? $B['term']->name : 'Extras';
            return strcasecmp($an, $bn);
        });
    }

    if (!empty($stored_order)) {
        $reordered = [];
        $term_index = [];
        foreach ($ordered as $key => $g) {
            if ($g['term']) {
                $term_index[(int)$g['term']->term_id] = ['key' => $key, 'group' => $g];
            }
        }
        foreach ($stored_order as $term_id) {
            $term_id = (int)$term_id;
            if (isset($term_index[$term_id])) {
                $entry = $term_index[$term_id];
                $reordered[$entry['key']] = $entry['group'];
                unset($term_index[$term_id]);
            }
        }
        if (!empty($term_index)) {
            uasort($term_index, function($a, $b){
                $nameA = $a['group']['term'] ? $a['group']['term']->name : '';
                $nameB = $b['group']['term'] ? $b['group']['term']->name : '';
                return strcasecmp($nameA, $nameB);
            });
            foreach ($term_index as $entry) {
                $reordered[$entry['key']] = $entry['group'];
            }
        }
        foreach ($ordered as $key => $g) {
            if (!isset($reordered[$key])) {
                $reordered[$key] = $g;
            }
        }
        $ordered = $reordered;
    }

    $nonce = wp_create_nonce('mv_extras_nonce');
    $start = is_numeric($a['start']) ? (int)$a['start'] : 2;

    // Índice de FECHA seleccionada (para mostrar precio correcto en la UI)
    $idxSel = function_exists('WC') && WC()->cart ? mv_cart_selected_idx_for_product($pid) : -1;

    $render_groups = [];
    foreach ($ordered as $gid => $g) {
        $extras_ids = $g['extras'];
        $items = [];
        foreach ($extras_ids as $eid) {
            $price = (float) mv_product_extra_price_for_idx($pid, $eid, $idxSel);
            if ($price <= 0) {
                continue;
            }
            $title = get_the_title($eid);
            $qty   = function_exists('WC') && WC()->cart ? mv_cart_qty_for_extra($pid, $eid) : 0;
            $base_inc = function_exists('mv_extra_base_included') ? mv_extra_base_included($pid, $eid, $idxSel) : 0;
            $effective_qty = max((int)$qty, (int)$base_inc);

            $items[] = [
                'id'            => $eid,
                'title'         => $title,
                'price'         => $price,
                'base_inc'      => $base_inc,
                'effective_qty' => $effective_qty,
            ];
        }
        if (!empty($items)) {
            $render_groups[] = [
                'term'   => $g['term'],
                'extras' => $items,
            ];
        }
    }

    if (empty($render_groups)) {
        return '';
    }

    $count_groups = count($render_groups);

    ob_start();
    echo '<div class="vx-extras-root" data-nonce="'.esc_attr($nonce).'">';
    foreach ($render_groups as $index => $group) {
        $num = $start + $index;
        $term_label = '';
        if ($group['term']) {
            $tid = (int)$group['term']->term_id;
            if (!empty($stored_labels[$tid])) {
                $term_label = sanitize_text_field($stored_labels[$tid]);
            }
        }
        $display_name = $term_label !== '' ? $term_label : ($group['term'] ? $group['term']->name : __('Extras','manaslu'));
        $base_heading = ($a['heading'] && $count_groups === 1) ? $a['heading'] : $display_name;
        $heading = $num . '. ' . $base_heading;

        echo '<div class="vx-extras-block" data-pid="'.esc_attr($pid).'">';
        echo '<h3 class="vx-extras-heading">'.esc_html($heading).'</h3>';
        echo '<div class="vx-extras-grid">';

        foreach ($group['extras'] as $item) {
            $inc_note = '';
            if ((int)$item['base_inc'] > 0) {
                $inc_note = '<span class="vx-inc-note">'.sprintf(esc_html__('Incluye %d', 'manaslu'), (int)$item['base_inc']).'</span>';
            }
            echo '<div class="vx-extra" data-extra="'.esc_attr($item['id']).'" data-inc="'.(int)$item['base_inc'].'" data-qty="'.esc_attr($item['effective_qty']).'">';
            echo '<div class="vx-title">'.esc_html($item['title']).'<span class="vx-price"> — '.wc_price($item['price']).'</span>'.$inc_note.'</div>';
            echo '<div class="vx-qty">';
            echo '<button class="vx-btn" data-vx-act="sub" aria-label="'.esc_attr__('Quitar','manaslu').'">−</button>';
            echo '<span class="vx-count" aria-live="polite" data-inc="'.(int)$item['base_inc'].'" data-qty="'.esc_attr($item['effective_qty']).'">'.esc_html($item['effective_qty']).'</span>';
            echo '<button class="vx-btn" data-vx-act="add" aria-label="'.esc_attr__('Añadir','manaslu').'">+</button>';
            echo '</div></div>';
        }
        echo '</div>';
        if ($index + 1 < $count_groups) echo '<hr class="vx-sep" />';
        echo '</div>';
    }
    echo '</div>';
    return ob_get_clean();
});
// [viaje_extra id="123" product_id=""] (simple)
add_shortcode('viaje_extra', function($atts = []){
    $a = shortcode_atts(['id'=>0,'product_id'=>''], $atts, 'viaje_extra');
    $eid = absint($a['id']); if (!$eid) return '';
    $pid = $a['product_id'] !== '' ? absint($a['product_id']) : mv_resolve_product_id();
    if (!$pid) return '';

    // FECHA seleccionada (para precio correcto en UI)
    $idxSel = function_exists('WC') && WC()->cart ? mv_cart_selected_idx_for_product($pid) : -1;

    $title = get_the_title($eid);
    $price = (float) mv_product_extra_price_for_idx($pid, $eid, $idxSel);
    if ($price <= 0) return '';
    $qty   = function_exists('WC') && WC()->cart ? mv_cart_qty_for_extra($pid, $eid) : 0;
    $base_inc = function_exists('mv_extra_base_included') ? mv_extra_base_included($pid, $eid, $idxSel) : 0;
    $effective_qty = max((int)$qty, (int)$base_inc);
    $inc_note = '';
    if ($base_inc > 0) {
        $inc_note = '<span class="vx-inc-note">'.sprintf(esc_html__('Incluye %d', 'manaslu'), (int)$base_inc).'</span>';
    }
    $nonce = wp_create_nonce('mv_extras_nonce');

    ob_start();
    echo '<div class="vx-extras-root" data-nonce="'.esc_attr($nonce).'">';
    echo '<div class="vx-extras-block" data-pid="'.esc_attr($pid).'">';
    echo '<div class="vx-extras-grid" style="grid-template-columns:1fr">';
    echo '<div class="vx-extra" data-extra="'.esc_attr($eid).'" data-inc="'.(int)$base_inc.'" data-qty="'.esc_attr($effective_qty).'">';
    echo '<div class="vx-title">'.esc_html($title).'<span class="vx-price"> — '.wc_price($price).'</span>'.$inc_note.'</div>';
    echo '<div class="vx-qty"><button class="vx-btn" data-vx-act="sub" aria-label="'.esc_attr__('Quitar','manaslu').'">−</button><span class="vx-count" aria-live="polite" data-inc="'.(int)$base_inc.'" data-qty="'.esc_attr($effective_qty).'">'.esc_html($effective_qty).'</span><button class="vx-btn" data-vx-act="add" aria-label="'.esc_attr__('Añadir','manaslu').'">+</button></div>';
    echo '</div></div></div></div>';
    return ob_get_clean();
});

/* >>> Shortcode específico Seguro de Viaje
   Uso: [viaje_seguro id="EXTRA_ID" product_id=""]
*/
add_shortcode('viaje_seguro', function($atts = []){
    $a = shortcode_atts(['id'=>0,'product_id'=>''], $atts, 'viaje_seguro');
    $eid = absint($a['id']); if (!$eid) return '';
    $pid = $a['product_id'] !== '' ? absint($a['product_id']) : mv_resolve_product_id();
    if (!$pid) return '';

    $post = get_post($eid); if (!$post) return '';
    $title = get_the_title($eid);
    $desc  = has_excerpt($eid) ? get_the_excerpt($eid) : wp_trim_words(strip_shortcodes(wp_kses_post($post->post_content)), 24);
    $img   = get_the_post_thumbnail_url($eid, 'large');
    // Seguro mantiene precio por producto (no por fecha)
    $price_sin = mv_product_extra_price($pid, $eid, 'sin');
    $price_con = mv_product_extra_price($pid, $eid, 'con');

    $link_sin_raw = mv_product_seguro_link($pid, $eid, 'sin');
    $link_con_raw = mv_product_seguro_link($pid, $eid, 'con');

    $nonce = wp_create_nonce('mv_extras_nonce');

    // Estado inicial desde carrito
    $in_variant = function_exists('WC') && WC()->cart ? mv_cart_seguro_variant($pid, $eid) : '';
    $is_on = $in_variant === 'con' || $in_variant === 'sin';
    $current_variant = $is_on ? $in_variant : 'sin';

    $price_current = ($current_variant==='con') ? wc_price($price_con) : wc_price($price_sin);
    $link_current_raw = ($current_variant === 'con') ? $link_con_raw : $link_sin_raw;
    if ($link_current_raw === '' && $current_variant === 'con' && $link_sin_raw !== '') {
        $link_current_raw = $link_sin_raw;
    } elseif ($link_current_raw === '' && $current_variant === 'sin' && $link_con_raw !== '') {
        $link_current_raw = $link_con_raw;
    }
    $has_any_link = ($link_sin_raw !== '' || $link_con_raw !== '');

    ob_start();
    echo '<div class="vx-extras-root">';
    echo '<div class="vx-seguro-card"'
        . ' data-pid="'.esc_attr($pid).'"'
        . ' data-extra="'.esc_attr($eid).'"'
        . ' data-nonce="'.esc_attr($nonce).'"'
        . ' data-price-con="'.esc_attr(wc_price($price_con)).'"'
        . ' data-price-sin="'.esc_attr(wc_price($price_sin)).'"'
        . ' data-link-con="'.esc_attr($link_con_raw).'"'
        . ' data-link-sin="'.esc_attr($link_sin_raw).'"'
    . '>';

    echo '<div class="vx-seguro-img">'.($img?'<img src="'.esc_url($img).'" alt="'.esc_attr($title).'">':'').'</div>';

    echo '<div class="vx-seguro-main">';
    echo '<h4 class="vx-seguro-title">'.esc_html($title).'</h4>';
    if ($desc) echo '<p class="vx-seguro-desc">'.esc_html($desc).'</p>';

    echo '<div class="vx-seguro-row">';

    // 1) Precio primero
    echo '<div class="vx-seguro-price">'.$price_current.'</div>';

    // 2) Desplegable de cancelación
    echo '<select class="vx-seguro-select">';
    echo '<option value="con" '.selected($current_variant,'con',false).'>'.esc_html__('CON CANCELACIÓN','manaslu').'</option>';
    echo '<option value="sin" '.selected($current_variant,'sin',false).'>'.esc_html__('SIN CANCELACIÓN','manaslu').'</option>';
    echo '</select>';

    // 3) Contenedor padre con los dos botones (horizontal)
    echo '<div class="vx-seguro-actions">';
    $link_style = ($has_any_link && $link_current_raw !== '') ? '' : ' style="display:none"';
    $link_href = ($has_any_link && $link_current_raw !== '') ? esc_url($link_current_raw) : '#';
    echo '<a class="vx-seguro-dl" data-role="vx-seguro-link" href="'.$link_href.'" target="_blank" rel="noopener noreferrer"'.$link_style.'>'.esc_html__('Descargar póliza','manaslu').'</a>';
    echo '<button type="button" class="vx-seguro-btn '.($is_on?'is-on':'').'" aria-pressed="'.($is_on?'true':'false').'">'.($is_on?esc_html__('Quitar','manaslu'):esc_html__('Añadir','manaslu')).'</button>';
    echo '</div>';

    echo '</div>'; // row
    echo '</div>'; // main

    echo '</div>'; // card
    echo '</div>'; // root
    return ob_get_clean();
});

// Devuelve precios actuales para extras "Individual" asignados a un producto
add_action('wp_ajax_viaje_extra_prices', 'mv_ajax_extra_prices');
add_action('wp_ajax_nopriv_viaje_extra_prices', 'mv_ajax_extra_prices');
function mv_ajax_extra_prices(){
    $pid = isset($_POST['pid']) ? absint($_POST['pid']) : 0;
    if (!$pid) wp_send_json_error(['message'=>'PID inválido']);

    $assigned = get_post_meta($pid, 'extras_asignados', true);
    $assigned = is_array($assigned) ? array_map('intval', $assigned) : [];

    // Filtrar solo "Individual" y "Personas" (no seguro)
    $ids = array_values(array_filter($assigned, function($eid){
        $is_ind = function_exists('mv_is_individual_extra') && mv_is_individual_extra($eid);
        $is_per = function_exists('mv_is_personas_extra') && mv_is_personas_extra($eid);
        return $is_ind || $is_per;
    }));

    $out = [];
    foreach ($ids as $eid) {
        // Esta función ya usa la fecha elegida en carrito si existe
        $price = mv_product_extra_price($pid, $eid, null);
        $out[$eid] = wc_price($price);
    }
    wp_send_json_success(['prices'=>$out]);
}
add_action('wp_ajax_viaje_extra_prices', 'mv_ajax_viaje_extra_prices');
add_action('wp_ajax_nopriv_viaje_extra_prices', 'mv_ajax_viaje_extra_prices');
function mv_ajax_viaje_extra_prices(){
    $pid = isset($_POST['pid']) ? absint($_POST['pid']) : 0;
    if (!$pid) wp_send_json_error(['message'=>'pid inválido']);

    $assigned = get_post_meta($pid, 'extras_asignados', true);
    $assigned = is_array($assigned) ? array_map('intval', $assigned) : [];

    $idx = function_exists('mv_cart_selected_idx_for_product') ? mv_cart_selected_idx_for_product($pid) : -1;

    $prices = [];
    foreach ($assigned as $eid) {
        // Omitir seguros
        if (function_exists('mv_is_seguro_extra') && mv_is_seguro_extra($eid)) continue;
        $p = ($idx >= 0 && function_exists('mv_product_extra_price_for_idx'))
            ? mv_product_extra_price_for_idx($pid, $eid, $idx)
            : mv_product_extra_price($pid, $eid, null);
        $prices[$eid] = wc_price($p);
    }

    wp_send_json_success(['prices' => $prices]);
}
