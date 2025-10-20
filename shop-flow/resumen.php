<?php
/**
 * Plugin Name: Manaslu – Resumen en vivo
 * Description: Shortcode [manaslu_resumen] con actualización AJAX (fechas, personas, extras, seguro y subtotal) + integración con WooFragments.
 * Version: 1.3.0
 */

if (!defined('ABSPATH')) exit;

/* ========== Helpers básicos ========== */

function manaslu_resolve_pid_for_summary($attr_pid = 0){
    $pid = absint($attr_pid);
    if (!$pid) {
        $pid = absint(get_query_var('pid'));
        if (!$pid && isset($_GET['pid'])) $pid = absint($_GET['pid']);
    }
    return $pid;
}

function manaslu_fmt_fecha_corta($ymd){
    if (empty($ymd)) return '';
    $ts = strtotime($ymd);
    if (!$ts) return esc_html($ymd);
    $d = date_i18n('d', $ts);
    $m = mb_strtoupper(date_i18n('F', $ts), 'UTF-8');
    return $d . ' ' . $m;
}

/**
 * Agrupa extras por categoría lógica:
 * - personas (slug 'personas')
 * - seguro   (slug 'seguro-de-viaje' o cat='seguro')
 * - extras   (resto)
 */
function manaslu_group_extras(array $extras){
    $out = ['personas'=>[], 'seguro'=>[], 'extras'=>[]];
    foreach ($extras as $e) {
        $title = isset($e['title']) ? (string)$e['title'] : '';
        $qty   = isset($e['qty'])   ? (int)$e['qty']       : 0;
        $unit  = isset($e['price']) ? (float)$e['price']   : 0.0;
        $cat   = isset($e['cat'])   ? (string)$e['cat']    : '';
        $included = isset($e['included']) ? max(0, (int)$e['included']) : 0;
        $charge_qty = isset($e['charge_qty']) ? max(0, (int)$e['charge_qty']) : max(0, $qty - $included);
        $line_total = isset($e['charge_total']) ? (float)$e['charge_total'] : ($unit * $charge_qty);
        $note = '';
        if ($included > 0) {
            $note = sprintf(esc_html__('', 'manaslu'), $included);
        }

        if ($qty <= 0 || $title === '') continue;

        $row = [
            'title' => $title,
            'qty'   => $qty,
            'unit'  => $unit,
            'included' => $included,
            'charge_qty' => $charge_qty,
            'total' => $line_total,
            'note'  => $note,
        ];

        if ($cat === 'personas')        $out['personas'][] = $row;
        elseif ($cat === 'seguro')      $out['seguro'][]   = $row;
        else                             $out['extras'][]  = $row;
    }
    return $out;
}

/**
 * Extrae el array de extras de la línea del carrito, normalizándolo.
 * Intenta deducir la categoría por taxonomía cuando no venga en el item.
 */
function manaslu_extract_extras_from_cart_item(array $cart_item){
    $raw = [];
    if (!empty($cart_item['manaslu_extras']) && is_array($cart_item['manaslu_extras'])) {
        $raw = $cart_item['manaslu_extras'];
    } elseif (!empty($cart_item['viaje_extras']) && is_array($cart_item['viaje_extras'])) {
        $raw = $cart_item['viaje_extras'];
    }

    $product_id = isset($cart_item['product_id']) ? (int)$cart_item['product_id'] : 0;
    $date_idx   = isset($cart_item['viaje_fecha']['idx']) ? (int)$cart_item['viaje_fecha']['idx'] : -1;

    $extras = [];
    foreach ($raw as $e) {
        $id    = isset($e['id']) ? absint($e['id']) : ( isset($e['extra_id']) ? absint($e['extra_id']) : 0 );
        $qty   = isset($e['qty'])   ? absint($e['qty'])   : 0;
        $price = isset($e['price']) ? (float)$e['price']  : 0.0;

        // Fallback de precio (si no vino en el carrito)
        if ($price <= 0 && $id && function_exists('mv_product_extra_price')) {
            $pid_for_summary = $product_id ?: manaslu_resolve_pid_for_summary( isset($_REQUEST['pid']) ? absint($_REQUEST['pid']) : 0 );
            if ($pid_for_summary) {
                if ($date_idx >= 0 && function_exists('mv_product_extra_price_for_idx')) {
                    $price = (float) mv_product_extra_price_for_idx($pid_for_summary, $id, $date_idx);
                }
                if ($price <= 0) {
                    $price = (float) mv_product_extra_price($pid_for_summary, $id);
                }
            }
        }

        $title = isset($e['title']) ? (string)$e['title'] : '';
        $cat   = isset($e['cat'])   ? (string)$e['cat']   : '';

        $included = 0;
        if (is_array($e) && array_key_exists('included', $e)) {
            $included = max(0, (int)$e['included']);
        } elseif ($id && $product_id && function_exists('mv_extra_base_included')) {
            $included = max(0, (int) mv_extra_base_included($product_id, $id, $date_idx));
        }
        if ($included > $qty) {
            $included = $qty;
        }
        $charge_qty   = max(0, $qty - $included);
        $charge_total = $price * $charge_qty;

        // Si no viene 'cat', intentamos deducirla por taxonomía
        if (!$cat && $id) {
            $terms = get_the_terms($id, 'extra_category');
            if (!is_wp_error($terms) && $terms) {
                foreach ($terms as $t) {
                    if ($t->slug === 'personas')        { $cat = 'personas'; break; }
                    if ($t->slug === 'seguro-de-viaje') { $cat = 'seguro';   break; }
                }
            }
        }
        if (!$title && $id) $title = get_the_title($id);

        $extras[] = [
            'id'    => $id,
            'title' => $title,
            'qty'   => $qty,
            'price' => $price,
            'cat'   => $cat ?: 'extras',
            'included' => $included,
            'charge_qty' => $charge_qty,
            'charge_total' => $charge_total,
        ];
    }
    return $extras;
}

/**
 * Construye todos los datos del resumen (reusable para AJAX y WooFragments).
 */
function manaslu_collect_summary_data($pid = 0){
    if (!class_exists('WooCommerce') || !WC()->cart) {
        return [
            'fecha'               => ['label'=>'', 'price'=>wc_price(0)],
            'personas'            => [],
            'extras'              => [],
            'seguro'              => ['label'=>'', 'total'=>wc_price(0)],
            'personas_total'      => wc_price(0),
            'extras_total'        => wc_price(0),
            'subtotal'            => wc_price(0),
            'coupon_total'        => wc_price(0),
            'pax_discount_total'  => wc_price(0),
            'personas_count'      => 0,
            'personas_total_raw'  => 0.0,
            'pax_discount_raw'    => 0.0,
            'pax_discount_ranges' => [],
        ];
    }

    // Totales frescos del carrito
    if (method_exists(WC()->cart, 'calculate_totals')) {
        WC()->cart->calculate_totals();
    }

    $fecha_label = '';
    $fecha_price = 0.0;
    $extras_raw  = [];
    $target_product_id = 0;
    $target_product_type = '';
    $cart_item_debug = [];

    foreach (WC()->cart->get_cart() as $item) {
        $p = $item['data'];
        if (!is_object($p)) continue;

        $is_viaje = method_exists($p,'get_type') && $p->get_type()==='viaje';
        $has_fecha= !empty($item['viaje_fecha']);

        if ($pid && (int)$item['product_id'] !== $pid) continue;
        if (!$is_viaje && !$has_fecha) continue;

        $target_product_type = method_exists($p, 'get_type') ? $p->get_type() : '';

        if ($has_fecha) {
            $ini = $item['viaje_fecha']['inicio'] ?? '';
            $fin = $item['viaje_fecha']['fin']    ?? '';
            if ($ini && $fin) {
                $fecha_label = manaslu_fmt_fecha_corta($ini) . ' — ' . manaslu_fmt_fecha_corta($fin);
            }
        }
        // Este subtotal de línea YA puede incluir extras/personas según la lógica del carrito
        $fecha_price = (float)($item['line_subtotal'] ?? 0.0);
        $target_product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;

        // Extraemos extras asociados a esta línea
        $extras_raw  = manaslu_extract_extras_from_cart_item($item);
        $cart_item_debug = [
            'product_id'        => $target_product_id,
            'product_type'      => $target_product_type,
            'quantity'          => isset($item['quantity']) ? (int)$item['quantity'] : null,
            'has_manaslu_extras'=> !empty($item['manaslu_extras']),
            'has_viaje_extras'  => !empty($item['viaje_extras']),
            'viaje_personas'    => isset($item['viaje_personas']) ? $item['viaje_personas'] : null,
            'mv_pax_discount'   => isset($item['mv_pax_discount']) ? $item['mv_pax_discount'] : null,
            'mv_pax_debug'      => isset($item['mv_pax_debug']) ? $item['mv_pax_debug'] : null,
            'line_subtotal'     => isset($item['line_subtotal']) ? (float)$item['line_subtotal'] : null,
            'line_total'        => isset($item['line_total']) ? (float)$item['line_total'] : null,
            'data_price'        => method_exists($p, 'get_price') ? (float)$p->get_price() : null,
            'data_regular_price'=> method_exists($p, 'get_regular_price') ? (float)$p->get_regular_price() : null,
            'data_sale_price'   => method_exists($p, 'get_sale_price') ? (float)$p->get_sale_price() : null,
        ];
        break; // primer viaje relevante
    }

    $grouped = manaslu_group_extras($extras_raw);
    $personas_count = 0;
    foreach ($grouped['personas'] as $r) {
        $personas_count += (int)$r['qty'];
    }

    $sum = function($rows){ $t=0.0; foreach($rows as $r){ $t += (float)$r['total']; } return $t; };
    $personas_total_num = $sum($grouped['personas']);
    $extras_total_num   = $sum($grouped['extras']);
    $seguro_total_num   = $sum($grouped['seguro']);

    // Label del seguro (si hay varios, concatenamos)
    $seguro_label = '';
    if (!empty($grouped['seguro'])) {
        $labels = [];
        foreach ($grouped['seguro'] as $r) {
            $labels[] = trim($r['title'] . ($r['qty'] ? ' × '.$r['qty'] : ''));
        }
        $labels = array_unique(array_filter($labels));
        $seguro_label = implode(' + ', $labels);
    }

    // Subtotal nativo del carrito (autoridad)
    $totals        = WC()->cart->get_totals();
    $cart_subtotal = 0.0;
    if (isset($totals['cart_contents_total'])) $cart_subtotal += (float)$totals['cart_contents_total'];
    if (isset($totals['fee_total']))           $cart_subtotal += (float)$totals['fee_total'];

    // Descuento por cupones aplicado al carrito
    $coupon_discount_num = 0.0;
    if (isset($totals['discount_total'])) $coupon_discount_num += (float)$totals['discount_total'];

    // Descuento por Personas (suma de mv_pax_discount en ítems viaje)
    $pax_discount_num = 0.0;
    foreach (WC()->cart->get_cart() as $ci){
        if (!empty($ci['mv_pax_discount']) && is_array($ci['mv_pax_discount']) && isset($ci['mv_pax_discount']['amount'])){
            $pax_discount_num += (float)$ci['mv_pax_discount']['amount'];
        }
    }

    // Fallback al subtotal de la línea si fuera necesario
    $subtotal_num = $cart_subtotal > 0 ? $cart_subtotal : $fecha_price;

    $pax_ranges = [];
    if ($target_product_id) {
        $raw_ranges = get_post_meta($target_product_id, '_mv_descuentos_personas', true);
        if (is_array($raw_ranges)) {
            $pax_ranges = array_values($raw_ranges);
        }
    } elseif ($pid) {
        $raw_ranges = get_post_meta($pid, '_mv_descuentos_personas', true);
        if (is_array($raw_ranges)) {
            $pax_ranges = array_values($raw_ranges);
        }
    }

    $format_rows = function($rows){
        return array_map(function($r){
            return [
                'title' => $r['title'],
                'qty'   => (int)$r['qty'],
                'included' => isset($r['included']) ? (int)$r['included'] : 0,
                'charge_qty' => isset($r['charge_qty']) ? (int)$r['charge_qty'] : max(0, (int)$r['qty'] - (int)($r['included'] ?? 0)),
                'unit'  => wc_price((float)$r['unit']),
                'total' => wc_price((float)$r['total']),
                'note'  => isset($r['note']) ? (string)$r['note'] : '',
            ];
        }, $rows);
    };

    return [
        'fecha'               => ['label'=>$fecha_label, 'price'=>wc_price($fecha_price)],
        'personas'            => $format_rows($grouped['personas']),
        'extras'              => $format_rows($grouped['extras']),   // ya SIN el seguro
        'seguro'              => ['label'=>$seguro_label, 'total'=>wc_price($seguro_total_num)],
        'personas_total'      => wc_price($personas_total_num),
        'extras_total'        => wc_price($extras_total_num),
        'subtotal'            => wc_price($subtotal_num),
        'coupon_total'        => wc_price(-$coupon_discount_num),
        'pax_discount_total'  => wc_price(-$pax_discount_num),
        'personas_count'      => $personas_count,
        'personas_total_raw'  => $personas_total_num,
        'pax_discount_raw'    => $pax_discount_num,
        'pax_discount_ranges' => $pax_ranges,
        'cart_item_debug'     => $cart_item_debug,
        'cart_totals_raw'     => $totals,
    ];
}

/* ========== AJAX (con nonce y no-cache) ========== */

add_action('wp_ajax_manaslu_resumen_data', 'manaslu_resumen_data');
add_action('wp_ajax_nopriv_manaslu_resumen_data', 'manaslu_resumen_data');

function manaslu_resumen_data(){
    nocache_headers();
    check_ajax_referer('manaslu_resumen', 'nonce');

    $pid = isset($_REQUEST['pid']) ? absint($_REQUEST['pid']) : 0;
    $data = manaslu_collect_summary_data($pid);
    wp_send_json_success($data);
}

/* ========== WooCommerce Fragments (respaldo “nativo”) ========== */

add_filter('woocommerce_add_to_cart_fragments', function($fragments){
    $data = manaslu_collect_summary_data(0);

    // FECHA
    $fragments['#mr-fecha-label']  = '<div class="mr-left" id="mr-fecha-label">'.esc_html($data['fecha']['label'] ?: '—').'</div>';
    $fragments['#mr-fecha-price']  = '<div class="mr-right" id="mr-fecha-price">'.$data['fecha']['price'].'</div>';

    // PERSONAS
    $personas_html = '<div id="mr-personas" class="mr-list">';
    if (!empty($data['personas'])) {
        foreach ($data['personas'] as $r) {
            $title_text = $r['title'];
            if (!empty($r['qty'])) $title_text .= ' × '.$r['qty'];
            if (!empty($r['note'])) $title_text .= ' ('.$r['note'].')';
            $title = esc_html($title_text);
            $amount= $r['total'];
            $personas_html .= '<div class="mr-item"><div class="title">'.$title.'</div><div class="amount">'.$amount.'</div></div>';
        }
    } else {
        $personas_html .= '<span class="mr-empty">0</span>';
    }
    $personas_html .= '</div>';
    $fragments['#mr-personas'] = $personas_html;
    $fragments['#mr-personas-total'] = '<div class="mr-right" id="mr-personas-total">'.$data['personas_total'].'</div>';

    // EXTRAS (sin seguro)
    $extras_html = '<div id="mr-extras" class="mr-list">';
    if (!empty($data['extras'])) {
        foreach ($data['extras'] as $r) {
            $title_text = $r['title'];
            if (!empty($r['qty'])) $title_text .= ' × '.$r['qty'];
            if (!empty($r['note'])) $title_text .= ' ('.$r['note'].')';
            $title = esc_html($title_text);
            $amount= $r['total'];
            $extras_html .= '<div class="mr-item"><div class="title">'.$title.'</div><div class="amount">'.$amount.'</div></div>';
        }
    } else {
        $extras_html .= '<span class="mr-empty">0</span>';
    }
    $extras_html .= '</div>';
    $fragments['#mr-extras'] = $extras_html;
    $fragments['#mr-extras-total'] = '<div class="mr-right" id="mr-extras-total">'.$data['extras_total'].'</div>';

    // SEGURO (nuevo bloque)
    $fragments['#mr-seguro']       = '<div class="mr-left" id="mr-seguro">'.esc_html($data['seguro']['label'] ?? '').'</div>';
    $fragments['#mr-seguro-total'] = '<div class="mr-right" id="mr-seguro-total">'.($data['seguro']['total'] ?? wc_price(0)).'</div>';

    // SUBTOTAL
    $fragments['#mr-subtotal'] = '<div class="mr-right" id="mr-subtotal"><strong>'.$data['subtotal'].'</strong></div>';
    $fragments['#mr-coupon-total'] = '<div class="mr-right" id="mr-coupon-total">'.($data['coupon_total'] ?? wc_price(0)).'</div>';
    $fragments['#mr-pax-discount'] = '<div class="mr-right" id="mr-pax-discount">'.($data['pax_discount_total'] ?? wc_price(0)).'</div>';

    return $fragments;
});

/* ========== Shortcode + assets ========== */

add_shortcode('manaslu_resumen', function($atts){
    $atts = shortcode_atts(['pid'=>0, 'class'=>''], $atts, 'manaslu_resumen');
    $pid  = manaslu_resolve_pid_for_summary($atts['pid']);
    $nonce = wp_create_nonce('manaslu_resumen');

    // Asegurar fragments de Woo
    wp_enqueue_script('wc-cart-fragments');

    ob_start(); ?>
<div id="manaslu-resumen" class="manaslu-resumen <?php echo esc_attr($atts['class']); ?>"
     data-pid="<?php echo esc_attr($pid); ?>"
     data-nonce="<?php echo esc_attr($nonce); ?>">
  <div class="mr-card">

    <!-- FECHA -->
    <div class="mr-section">
      <div class="mr-header">FECHA</div>
      <div class="mr-row two">
        <div class="mr-left" id="mr-fecha-label">—</div>
        <div class="mr-right" id="mr-fecha-price">0,00€</div>
      </div>
    </div>

    <!-- PERSONAS -->
    <div class="mr-sep"></div>
    <div class="mr-section">
      <div class="mr-header">PERSONAS</div>
      <div id="mr-personas" class="mr-list"><span class="mr-empty">0</span></div>
      <div class="mr-row two total-line">
        <div class="mr-left"></div>
        <div class="mr-right" id="mr-personas-total">0,00€</div>
      </div>
    </div>

    <!-- EXTRAS -->
    <div class="mr-sep"></div>
    <div class="mr-section">
      <div class="mr-header">EXTRAS</div>
      <div id="mr-extras" class="mr-list"><span class="mr-empty">0</span></div>
      <div class="mr-row two total-line">
        <div class="mr-left"></div>
        <div class="mr-right" id="mr-extras-total">0,00€</div>
      </div>
    </div>

    <!-- SEGURO -->
    <div class="mr-sep"></div>
    <div class="mr-section">
      <div class="mr-header">SEGURO</div>
      <div class="mr-row two">
        <div class="mr-left" id="mr-seguro"></div>
        <div class="mr-right" id="mr-seguro-total">0,00€</div>
      </div>
    </div>

    <!-- SUBTOTAL -->
    <div class="mr-sep"></div>
    <div class="mr-section">
      <div class="mr-row two note-line">
        <div class="mr-left">Descuento por Personas •</div>
        <div class="mr-right" id="mr-pax-discount">-0,00€</div>
      </div>
      <div class="mr-row two note-line">
        <div class="mr-left">Descuento adicional •</div>
        <div class="mr-right" id="mr-coupon-total">-0,00€</div>
      </div>
      <div class="mr-sep"></div>
      <div class="mr-row two subtotal">
        <div class="mr-left"><strong>SUBTOTAL</strong></div>
        <div class="mr-right" id="mr-subtotal"><strong>0,00€</strong></div>
      </div>
    </div>

  </div>
</div>

<style>
@font-face{font-family:"Host Grotesk";font-style:normal;font-weight:400;font-display:swap;
    src:local("Host Grotesk"), local("HostGrotesk");}
.manaslu-resumen{font-family:"Host Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;color:#000}
.mr-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:18px;box-shadow:0 4px 12px rgba(0,0,0,.05)}
.mr-header{font-weight:700;letter-spacing:.02em;margin-bottom:6px}
.mr-row.two{display:grid;grid-template-columns:1fr auto;align-items:center;gap:12px}
.mr-left{color:#111}
.mr-right{font-weight:700;text-align:right;color:#000}
.mr-list{display:flex;flex-direction:column;gap:8px}
.mr-item{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center}
.mr-item .title{color:#111}
.mr-item .amount{font-weight:700;text-align:right}
.mr-sep{height:1px;background:rgba(0,0,0,.08);margin:14px 0}
.mr-empty{opacity:.5}
.total-line{margin-top:4px}
.subtotal{padding-top:6px}
.note-line{color:#111;opacity:.9}
</style>

<script>
(function($){
  var $root   = $('#manaslu-resumen');
  var pid     = $root.data('pid') || 0;
  var nonce   = $root.data('nonce') || '';
  var inflight = null;
  var timer    = null;

  if (window.console && console.log) {
    console.log('[Manaslu] resumen activo', { pid: pid });
  }

  function renderList($container, rows){
    $container.empty();
    if (!rows || !rows.length){
      $container.append('<span class="mr-empty">0</span>');
      return;
    }
    rows.forEach(function(r){
      var $row = $('<div class="mr-item"></div>');
      var leftTitle = r.title || '';
      if (r.qty) leftTitle += ' × ' + r.qty;
      if (r.note) leftTitle += ' (' + r.note + ')';
      $row.append($('<div class="title"></div>').text(leftTitle));
      $row.append($('<div class="amount"></div>').html(r.total || r.unit || ''));
      $container.append($row);
    });
  }

  function refreshSummary(){
    if (window.console && console.log) {
      console.log('[Manaslu] refreshSummary() llamada');
    }
    if (inflight && inflight.readyState !== 4) {
      try { inflight.abort(); } catch(e){}
    }
    inflight = $.ajax({
      url: (window.ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>'),
      data: { action: 'manaslu_resumen_data', pid: pid, nonce: nonce, _t: Date.now() },
      dataType: 'json',
      cache: false,
      method: 'GET'
    }).done(function(res){
      if (!res || !res.success || !res.data) return;

      var personasCount = res.data.personas_count || 0;
      var discountRaw   = res.data.pax_discount_raw || 0;
      var discountDisplay = res.data.pax_discount_total || '<?php echo esc_js(wc_price(0)); ?>';
      var rangesInfo  = res.data.pax_discount_ranges || [];
      var cartDebug   = res.data.cart_item_debug || {};
      var totalsRaw   = res.data.cart_totals_raw || {};

      if (window.console && console.log) {
        console.log('[Manaslu] resumen data', {
          personasCount: personasCount,
          discountRaw: discountRaw,
          discountDisplay: discountDisplay,
          ranges: rangesInfo,
          cartItem: cartDebug,
          cartTotalsRaw: totalsRaw
        });
        if (discountRaw > 0) {
          console.log('[Manaslu] Descuento por personas aplicado');
        } else {
          console.log('[Manaslu] Sin descuento por personas aplicado');
        }
      }

      // FECHA
      $('#mr-fecha-label').text(res.data.fecha.label || '—');
      $('#mr-fecha-price').html(res.data.fecha.price || '<?php echo esc_js(wc_price(0)); ?>');

      // PERSONAS
      renderList($('#mr-personas'), res.data.personas);
      $('#mr-personas-total').html(res.data.personas_total || '<?php echo esc_js(wc_price(0)); ?>');

      // EXTRAS (sin seguro)
      renderList($('#mr-extras'), res.data.extras);
      $('#mr-extras-total').html(res.data.extras_total || '<?php echo esc_js(wc_price(0)); ?>');

      // SEGURO
      $('#mr-seguro').text((res.data.seguro && res.data.seguro.label) || '');
      $('#mr-seguro-total').html((res.data.seguro && res.data.seguro.total) || '<?php echo esc_js(wc_price(0)); ?>');

      // SUBTOTAL
      $('#mr-subtotal').html(res.data.subtotal || '<?php echo esc_js(wc_price(0)); ?>');
      $('#mr-coupon-total').html(res.data.coupon_total || '<?php echo esc_js(wc_price(0)); ?>');
      $('#mr-pax-discount').html(res.data.pax_discount_total || '<?php echo esc_js(wc_price(0)); ?>');
    });
  }

  function scheduleRefresh(ms){
    if (timer) clearTimeout(timer);
    timer = setTimeout(refreshSummary, typeof ms==='number' ? ms : 80);
  }

  // Exponer utilidades para otros scripts
  window.manasluSummaryRefresh = refreshSummary;
  window.manasluPing = function(){
    $(document.body).trigger('manaslu:cart_changed');
    try { localStorage.setItem('manaslu:tick', String(Date.now())); } catch(e){}
  };

  // Escuchar eventos relevantes (Woo + custom)
  $(document.body).on(
    'added_to_cart removed_from_cart updated_wc_div updated_cart_totals ' +
    'wc_fragment_refresh wc_fragments_loaded wc_fragments_refreshed ' +
    'manaslu:cart_changed manaslu:fecha manaslu:extras',
    function(){ scheduleRefresh(80); }
  );

  document.addEventListener('visibilitychange', function(){
    if (!document.hidden) scheduleRefresh(50);
  });

  window.addEventListener('storage', function(e){
    if (e && e.key === 'manaslu:tick') scheduleRefresh(30);
  });

  $(document).on('click', '.single_add_to_cart_button, .ajax_add_to_cart', function(){
    scheduleRefresh(250);
  });

  $(document).ready(function(){
    scheduleRefresh(0);
    setTimeout(refreshSummary, 300);
    setTimeout(refreshSummary, 900);
  });
})(jQuery);
</script>
<?php
    return ob_get_clean();
});
