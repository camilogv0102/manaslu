<?php
/**
 * Plugin Name: Manaslu – Viajes CSV (export/import robusto)
 * Description: Exporta productos "viaje" como type=viaje y añade columna viaje_fechas (JSON). Import: reconoce type=viaje y mapea viaje_fechas a _viaje_fechas.
 * Author: ProCro
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------
 * Helpers
 * ------------------------------------------ */
function mv_viajes_csv_encode($data) {
    if (empty($data)) {
        return '';
    }
    $json_options = 0;
    if (defined('JSON_UNESCAPED_UNICODE')) {
        $json_options |= JSON_UNESCAPED_UNICODE;
    }
    if (defined('JSON_UNESCAPED_SLASHES')) {
        $json_options |= JSON_UNESCAPED_SLASHES;
    }
    return wp_json_encode($data, $json_options);
}

function mv_viajes_csv_decode($value) {
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function mv_viajes_csv_normalize_extra_slug($slug) {
    $base = sanitize_title($slug);
    if ($base === '') {
        return '';
    }
    $trimmed = preg_replace('/-(eur|usd|ars|mxn|clp|pen|cop|gtq|chf|gbp)-[0-9]+$/', '', $base);
    $trimmed = preg_replace('/-[0-9]+$/', '', $trimmed);
    $trimmed = preg_replace('/-(\d+)(?:km|dias|dia)$/', '', $trimmed);
    $trimmed = preg_replace('/-+$/', '', $trimmed);
    return $trimmed !== '' ? $trimmed : $base;
}

function mv_viajes_csv_resolve_extra_id($entry) {
    $entry = is_array($entry) ? $entry : [];
    $id = isset($entry['id']) ? (int) $entry['id'] : 0;
    if ($id && get_post($id) && get_post_type($id) === 'extra') {
        return $id;
    }
    $slug = isset($entry['slug']) ? mv_viajes_csv_normalize_extra_slug($entry['slug']) : '';
    if ($slug) {
        $post = get_page_by_path($slug, OBJECT, 'extra');
        if ($post) {
            return (int) $post->ID;
        }
    }
    $title = isset($entry['title']) ? sanitize_text_field($entry['title']) : '';
    if ($title) {
        $maybe = get_page_by_title($title, OBJECT, 'extra');
        if ($maybe) {
            return (int) $maybe->ID;
        }
    }
    return 0;
}

function mv_viajes_csv_resolve_coupon_id($data) {
    $code = '';
    if (is_array($data)) {
        $code = isset($data['code']) ? $data['code'] : '';
    } elseif (is_string($data)) {
        $code = $data;
    }
    $code = trim(wp_strip_all_tags($code));
    if ($code === '') {
        return 0;
    }
    if (function_exists('wc_get_coupon_id_by_code')) {
        $cid = wc_get_coupon_id_by_code($code);
        return $cid ? (int) $cid : 0;
    }
    $coupon = get_page_by_title($code, OBJECT, 'shop_coupon');
    return $coupon ? (int) $coupon->ID : 0;
}

function mv_viajes_csv_resolve_term_id($data, $taxonomy = 'extra_category') {
    $data = is_array($data) ? $data : [];
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id && get_term($id, $taxonomy)) {
        return $id;
    }
    $slug = isset($data['slug']) ? sanitize_title($data['slug']) : '';
    if ($slug) {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term && !is_wp_error($term)) {
            return (int) $term->term_id;
        }
    }
    $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
    if ($name) {
        $term = get_term_by('name', $name, $taxonomy);
        if ($term && !is_wp_error($term)) {
            return (int) $term->term_id;
        }
    }
    return 0;
}

function mv_viajes_csv_get_acf_fields_list() {
    return [
        'grupo',
        'minimo_grupo',
        'maximo_grupo',
        'tipo_de_viaje',
        'estilo_viaje',
        'nivel',
        'duracion',
        'pais',
        'cordillera',
        'regimen_alojamiento',
        'forma_de_viajar',
        'guia',
        'transporte_interior',
        'mapa_embed',
        'itinerario',
        'incluido',
        'no_incluido',
        'actividades_opcionales_no_incluidas',
        'transfer',
        'alojamiento_y_comidas',
        'staff_durante_el_viaje',
        'material_recomendado',
        'informacion_adicional',
        'parrafo',
        'imagen',
        'galeria_imagenes',
        'faqs',
    ];
}

function mv_viajes_csv_collect_acf_data($product_id) {
    $fields = [];
    if (!function_exists('get_field')) {
        return $fields;
    }
    foreach (mv_viajes_csv_get_acf_fields_list() as $field_name) {
        $value = get_field($field_name, $product_id);
        if ($value !== null && $value !== '') {
            $fields[$field_name] = $value;
        }
    }
    return $fields;
}

function mv_viajes_csv_apply_acf_data($product_id, $data) {
    if (!is_array($data) || empty($data)) {
        return;
    }
    foreach ($data as $field => $value) {
        if (function_exists('update_field')) {
            update_field($field, $value, $product_id);
        } else {
            update_post_meta($product_id, $field, $value);
        }
    }
}



function mv_viajes_csv_extra_slug_from_title($title, $post_id = 0) {
    $title = is_string($title) ? $title : '';
    if ($title !== '') {
        return mv_viajes_csv_normalize_extra_slug($title);
    }
    if ($post_id) {
        $maybe = get_the_title($post_id);
        if ($maybe) {
            return mv_viajes_csv_normalize_extra_slug($maybe);
        }
        $post_name = get_post_field('post_name', $post_id);
        if ($post_name) {
            return mv_viajes_csv_normalize_extra_slug($post_name);
        }
    }
    return '';
}

function mv_viajes_csv_track_seen_extra(array &$seen, $slug, $id, $title) {
    $slug = mv_viajes_csv_normalize_extra_slug($slug);
    if ($slug === '') {
        return;
    }
    $id = (int) $id;
    $title = is_string($title) ? $title : '';
    if (!isset($seen[$slug])) {
        $seen[$slug] = ['id' => $id, 'title' => $title];
        return;
    }
    if (empty($seen[$slug]['id']) && $id) {
        $seen[$slug]['id'] = $id;
    }
    if ((empty($seen[$slug]['title']) || $seen[$slug]['title'] === '') && $title !== '') {
        $seen[$slug]['title'] = $title;
    }
}

function mv_viajes_csv_get_extra_id_from_slug($slug, $title = '', array &$cache = null) {
    $slug = mv_viajes_csv_normalize_extra_slug($slug);
    if ($slug && $cache !== null && isset($cache[$slug])) {
        return (int) $cache[$slug];
    }
    $extra_id = 0;
    if ($slug) {
        $post = get_page_by_path($slug, OBJECT, 'extra');
        if ($post) {
            $extra_id = (int) $post->ID;
        }
    }
    if (!$extra_id && $title) {
        $post = get_page_by_title($title, OBJECT, 'extra');
        if ($post) {
            $extra_id = (int) $post->ID;
        }
    }
    if (!$extra_id && $title) {
        $alt = sanitize_title($title);
        if ($alt && $alt !== $slug) {
            $post = get_page_by_path($alt, OBJECT, 'extra');
            if ($post) {
                $extra_id = (int) $post->ID;
            }
        }
    }
    if ($extra_id && $cache !== null && $slug) {
        $cache[$slug] = $extra_id;
    }
    return $extra_id;
}


function mv_viajes_csv_export_fechas($product_id) {
    $fechas = get_post_meta($product_id, '_viaje_fechas', true);
    return (is_array($fechas) && !empty($fechas)) ? array_values($fechas) : [];
}

function mv_viajes_csv_export_fechas_extras($product_id) {
    $data = get_post_meta($product_id, '_viaje_fechas_extras', true);
    if (!is_array($data) || empty($data)) {
        return [];
    }
    $out = [];
    foreach ($data as $idx => $extras) {
        if (!is_array($extras)) {
            continue;
        }
        foreach ($extras as $eid => $row) {
            $eid = (int) $eid;
            $title = $eid ? get_the_title($eid) : ''; 
            $slug = mv_viajes_csv_extra_slug_from_title($title, $eid);
            if ($slug === '' && $eid) {
                $slug = get_post_field('post_name', $eid);
            }
            if ($slug === '') {
                $slug = 'extra_' . $eid;
            }
            $out[$idx][$slug] = array_merge([
                'id'    => $eid,
                'slug'  => $slug,
                'title' => $title,
            ], is_array($row) ? $row : []);
        }
    }
    return $out;
}

function mv_viajes_csv_export_extras_assignments($product_id) {
    $assigned = get_post_meta($product_id, 'extras_asignados', true);
    if (!is_array($assigned) || empty($assigned)) {
        return [];
    }
    $items = [];
    foreach ($assigned as $eid) {
        $eid = (int) $eid;
        $title = $eid ? get_the_title($eid) : '';
        $slug = mv_viajes_csv_extra_slug_from_title($title, $eid);
        if ($slug === '' && $eid) {
            $slug = mv_viajes_csv_normalize_extra_slug(get_post_field('post_name', $eid));
        }
        $slug = mv_viajes_csv_normalize_extra_slug($slug);
        $items[] = [
            'id'    => $eid,
            'slug'  => $slug,
            'title' => $title,
        ];
    }
    return $items;
}

function mv_viajes_csv_export_extra_prices($product_id) {
    $map = get_post_meta($product_id, 'mv_extra_prices', true);
    if (!is_array($map) || empty($map)) {
        return [];
    }
    $items = [];
    foreach ($map as $eid => $row) {
        $eid = (int) $eid;
        $title = $eid ? get_the_title($eid) : '';
        $slug = mv_viajes_csv_extra_slug_from_title($title, $eid);
        if ($slug === '' && $eid) {
            $slug = mv_viajes_csv_normalize_extra_slug(get_post_field('post_name', $eid));
        }
        $slug = mv_viajes_csv_normalize_extra_slug($slug);
        $row = is_array($row) ? $row : [];
        unset($row['slug'], $row['title']);
        $items[] = array_merge([
            'id'    => $eid,
            'slug'  => $slug,
            'title' => $title,
        ], $row);
    }
    return $items;
}

function mv_viajes_csv_export_included_extras($product_id) {
    $arr = get_post_meta($product_id, 'mv_included_extras', true);
    if (!is_array($arr) || empty($arr)) {
        return [];
    }
    $out = [];
    foreach ($arr as $eid) {
        $eid = (int) $eid;
        $title = $eid ? get_the_title($eid) : '';
        $slug = mv_viajes_csv_extra_slug_from_title($title, $eid);
        if ($slug === '' && $eid) {
            $slug = mv_viajes_csv_normalize_extra_slug(get_post_field('post_name', $eid));
        }
        $slug = mv_viajes_csv_normalize_extra_slug($slug);
        $out[] = [
            'id'   => $eid,
            'slug' => $slug,
            'title'=> $title,
        ];
    }
    return $out;
}

function mv_viajes_csv_export_extra_cat_order($product_id) {
    $order = get_post_meta($product_id, 'mv_sc_extra_cat_order', true);
    if (!is_array($order) || empty($order)) {
        return [];
    }
    $items = [];
    foreach ($order as $term_id) {
        $term = get_term((int)$term_id, 'extra_category');
        if ($term && !is_wp_error($term)) {
            $items[] = [
                'id'   => (int) $term->term_id,
                'slug' => $term->slug,
                'name' => $term->name,
            ];
        }
    }
    return $items;
}

function mv_viajes_csv_export_extra_cat_labels($product_id) {
    $labels = get_post_meta($product_id, 'mv_sc_extra_cat_labels', true);
    if (!is_array($labels) || empty($labels)) {
        return [];
    }
    $items = [];
    foreach ($labels as $term_id => $label) {
        $term = get_term((int)$term_id, 'extra_category');
        $items[] = [
            'id'    => (int) $term_id,
            'slug'  => ($term && !is_wp_error($term)) ? $term->slug : '',
            'name'  => ($term && !is_wp_error($term)) ? $term->name : '',
            'label' => $label,
        ];
    }
    return $items;
}

function mv_viajes_csv_export_descuentos_personas($product_id) {
    $rangos = get_post_meta($product_id, '_mv_descuentos_personas', true);
    return (is_array($rangos) && !empty($rangos)) ? array_values($rangos) : [];
}

function mv_viajes_csv_export_allowed_coupon($product_id) {
    $coupon_id = (int) get_post_meta($product_id, '_mv_allowed_coupon', true);
    if ($coupon_id <= 0) {
        return [];
    }
    $code = $coupon_id ? get_post_field('post_title', $coupon_id) : '';
    if (!$code && class_exists('WC_Coupon')) {
        try {
            $coupon = new WC_Coupon($coupon_id);
            $code   = $coupon->get_code();
        } catch (Exception $e) {
            $code = '';
        }
    }
    return $code ? ['id' => $coupon_id, 'code' => $code] : [];
}

function mv_csv_is_viaje($product) {
    if (!$product) return false;
    if (method_exists($product, 'get_type') && $product->get_type() === 'viaje') return true;

    $pid = is_object($product) ? $product->get_id() : (int) $product;
    if (!$pid) return false;

    if (taxonomy_exists('product_type') && has_term('viaje', 'product_type', $pid)) return true;

    $fechas = get_post_meta($pid, '_viaje_fechas', true);
    if (is_array($fechas) && !empty($fechas)) return true;

    return false;
}

/* ------------------------------------------
 * Integraciones mínimas con WooCommerce
 * ------------------------------------------ */
add_filter('woocommerce_csv_product_types', function($types){
    $types['viaje'] = 'viaje';
    return $types;
});

add_filter('woocommerce_product_export_product_column_type', function($value, $product){
    return mv_csv_is_viaje($product) ? 'viaje' : $value;
}, 20, 2);

add_filter('woocommerce_product_export_row_data', function($row, $product){
    if (mv_csv_is_viaje($product)) {
        $row['type'] = 'viaje';
    }
    return $row;
}, 20, 2);

/* ------------------------------------------
 * Admin page (Export/Import)
 * ------------------------------------------ */
add_action('admin_menu', function(){
    add_submenu_page(
        'edit.php?post_type=product',
        __('Exportar/Importar Viajes', 'manaslu'),
        __('CSV Viajes', 'manaslu'),
        'manage_woocommerce',
        'mv-viajes-csv',
        'mv_viajes_csv_admin_page'
    );
});

function mv_viajes_csv_admin_page(){
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos suficientes.', 'manaslu'));
    }
    $export_url = admin_url('admin-post.php');
    $import_url = admin_url('admin-post.php');
    $message = isset($_GET['mv_msg']) ? sanitize_text_field(wp_unslash($_GET['mv_msg'])) : '';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Exportar / Importar Viajes', 'manaslu'); ?></h1>
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        <div class="card">
            <h2><?php esc_html_e('Exportar', 'manaslu'); ?></h2>
            <p><?php esc_html_e('Genera un ZIP con múltiples CSV que contienen todo el detalle de los productos tipo viaje.', 'manaslu'); ?></p>
            <form method="post" action="<?php echo esc_url($export_url); ?>">
                <?php wp_nonce_field('mv_viajes_export', 'mv_viajes_nonce'); ?>
                <input type="hidden" name="action" value="mv_viajes_export">
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Descargar ZIP', 'manaslu'); ?></button></p>
            </form>
        </div>
        <div class="card" style="margin-top:20px;">
            <h2><?php esc_html_e('Importar', 'manaslu'); ?></h2>
            <p><?php esc_html_e('Sube el ZIP generado por el exportador para crear o actualizar viajes, fechas, extras, descuentos y campos asociados.', 'manaslu'); ?></p>
            <form method="post" action="<?php echo esc_url($import_url); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('mv_viajes_import', 'mv_viajes_nonce'); ?>
                <input type="hidden" name="action" value="mv_viajes_import">
                <p><input type="file" name="mv_viajes_zip" accept="application/zip" required></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Importar ZIP', 'manaslu'); ?></button></p>
            </form>
        </div>
    </div>
    <?php
}

add_action('admin_post_mv_viajes_export', 'mv_viajes_csv_handle_export');
add_action('admin_post_mv_viajes_import', 'mv_viajes_csv_handle_import');

/* ------------------------------------------
 * Export helpers
 * ------------------------------------------ */
function mv_viajes_csv_product_key($product){
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }
    if (!$product) return '';
    $sku = $product->get_sku();
    if ($sku) {
        return 'sku:' . strtolower($sku);
    }
    $slug = $product->get_slug();
    if ($slug) {
        return 'slug:' . $slug;
    }
    return 'id:' . $product->get_id();
}

function mv_viajes_csv_parse_product_key($key){
    $key = trim((string)$key);
    if (stripos($key, 'sku:') === 0) {
        return ['type' => 'sku', 'value' => substr($key, 4)];
    }
    if (stripos($key, 'slug:') === 0) {
        return ['type' => 'slug', 'value' => substr($key, 5)];
    }
    if (stripos($key, 'id:') === 0) {
        return ['type' => 'id', 'value' => (int)substr($key, 3)];
    }
    return ['type' => '', 'value' => $key];
}

function mv_viajes_csv_collect_products(){
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'fields'         => 'ids',
    ];
    $ids = get_posts($args);
    $viajes = [];
    foreach ($ids as $pid) {
        $product = wc_get_product($pid);
        if ($product && mv_csv_is_viaje($product)) {
            $viajes[] = (int) $pid;
        }
    }
    return $viajes;
}

function mv_viajes_csv_collect_export_data(){
    $products = mv_viajes_csv_collect_products();
    $datasets = [
        'viajes'                 => [],
        'viaje_fechas'           => [],
        'viaje_fechas_extras'    => [],
        'viaje_extras'           => [],
        'viaje_extra_prices'     => [],
        'viaje_extra_categories' => [],
        'viaje_descuentos'       => [],
        'extras_catalogo'        => [],
    ];
    $seen_extras = [];

    foreach ($products as $pid) {
        $product = wc_get_product($pid);
        if (!$product) continue;
        $key = mv_viajes_csv_product_key($product);
        $sku = $product->get_sku();
        $coupon = mv_viajes_csv_export_allowed_coupon($pid);
        $coupon_code = is_array($coupon) && isset($coupon['code']) ? $coupon['code'] : '';

        $row = [
            'product_key'   => $key,
            'product_id'    => $pid,
            'sku'           => $sku,
            'name'          => $product->get_name(),
            'slug'          => $product->get_slug(),
            'status'        => get_post_status($pid),
            'regular_price' => $product->get_regular_price(),
            'sale_price'    => $product->get_sale_price(),
            'allowed_coupon'=> $coupon_code,
        ];

        $acf = mv_viajes_csv_collect_acf_data($pid);
        if (isset($acf['imagen']) && is_array($acf['imagen'])) {
            $acf['imagen'] = isset($acf['imagen']['url']) ? $acf['imagen']['url'] : '';
        }
        if (isset($acf['galeria_imagenes']) && is_array($acf['galeria_imagenes'])) {
            $urls = [];
            foreach ($acf['galeria_imagenes'] as $img) {
                if (is_array($img) && !empty($img['url'])) {
                    $urls[] = $img['url'];
                } elseif (is_string($img) && preg_match('#^https?://#', $img)) {
                    $urls[] = $img;
                }
            }
            $acf['galeria_imagenes'] = implode('|', $urls);
        }
        $simple_fields = ['grupo','minimo_grupo','maximo_grupo','tipo_de_viaje','estilo_viaje','nivel','duracion','pais','cordillera','regimen_alojamiento','forma_de_viajar','guia','transporte_interior','mapa_embed','incluido','no_incluido','actividades_opcionales_no_incluidas','transfer','alojamiento_y_comidas','staff_durante_el_viaje','material_recomendado','informacion_adicional','parrafo','imagen','galeria_imagenes'];
        foreach ($simple_fields as $field) {
            $value = isset($acf[$field]) ? $acf[$field] : '';
            if (is_array($value)) {
                $value = wp_json_encode($value);
            }
            $row[$field] = $value;
        }
        $datasets['viajes'][] = $row;

        $fechas = mv_viajes_csv_export_fechas($pid);
        foreach ($fechas as $idx => $fecha) {
            $datasets['viaje_fechas'][] = [
                'product_key'    => $key,
                'index'          => $idx,
                'inicio'         => $fecha['inicio'] ?? '',
                'fin'            => $fecha['fin'] ?? '',
                'precio_normal'  => $fecha['precio_normal'] ?? '',
                'precio_rebajado'=> $fecha['precio_rebajado'] ?? '',
                'concepto'       => $fecha['concepto'] ?? '',
                'tipo_grupo'     => $fecha['tipo_grupo'] ?? '',
                'cupo_total'     => $fecha['cupo_total'] ?? '',
                'estado'         => $fecha['estado'] ?? '',
            ];
        }

        $fecha_extras = mv_viajes_csv_export_fechas_extras($pid);
        foreach ($fecha_extras as $idx => $extras) {
            foreach ($extras as $slug => $data) {
                $datasets['viaje_fechas_extras'][] = [
                    'product_key'   => $key,
                    'index'         => $idx,
                    'extra_slug'    => $slug,
                    'extra_title'   => $data['title'] ?? '',
                    'precio_regular'=> $data['regular'] ?? '',
                    'precio_sale'   => $data['sale'] ?? '',
                    'incluye'       => isset($data['included']) ? $data['included'] : '',
                    'pct'           => isset($data['pct']) ? $data['pct'] : '',
                ];
                mv_viajes_csv_track_seen_extra($seen_extras, $slug, $eid, $data['title'] ?? '');
            }
        }

        if (!empty($acf['itinerario']) && is_array($acf['itinerario'])) {
            foreach ($acf['itinerario'] as $index => $item) {
                $img_url = '';
                if (!empty($item['imagen'])) {
                    if (is_array($item['imagen']) && !empty($item['imagen']['url'])) {
                        $img_url = $item['imagen']['url'];
                    } elseif (is_string($item['imagen']) && preg_match('#^https?://#', $item['imagen'])) {
                        $img_url = $item['imagen'];
                    } else {
                        $maybe = wp_get_attachment_url((int)$item['imagen']);
                        if ($maybe) $img_url = $maybe;
                    }
                }
                $datasets['viaje_itinerario'][] = [
                    'product_key'  => $key,
                    'position'     => $index,
                    'titulo'       => isset($item['titulo']) ? $item['titulo'] : '',
                    'descripcion'  => isset($item['descripcion']) ? $item['descripcion'] : '',
                    'imagen'       => $img_url,
                ];
            }
        }
        if (!empty($acf['faqs']) && is_array($acf['faqs'])) {
            foreach ($acf['faqs'] as $index => $item) {
                $datasets['viaje_faqs'][] = [
                    'product_key' => $key,
                    'position'    => $index,
                    'titulo'      => isset($item['titulo']) ? $item['titulo'] : '',
                    'parrafo'     => isset($item['parrafo']) ? $item['parrafo'] : '',
                ];
            }
        }

        $assignments = mv_viajes_csv_export_extras_assignments($pid);
        foreach ($assignments as $assignment) {
            $slug = $assignment['slug'];
            $datasets['viaje_extras'][] = [
                'product_key' => $key,
                'extra_slug'  => $slug,
                'extra_title' => $assignment['title'] ?? '',
            ];
            mv_viajes_csv_track_seen_extra($seen_extras, $slug, $assignment['id'] ?? 0, $assignment['title'] ?? '');
        }

        $included_defaults = [];
        foreach (mv_viajes_csv_export_included_extras($pid) as $inc) {
            $included_defaults[$inc['slug']] = 1;
            mv_viajes_csv_track_seen_extra($seen_extras, $inc['slug'], $inc['id'] ?? 0, $inc['title'] ?? '');
        }

        $extra_prices = mv_viajes_csv_export_extra_prices($pid);
        foreach ($extra_prices as $data) {
            $slug = $data['slug'];
            $row_price = [
                'product_key'    => $key,
                'extra_slug'     => $slug,
                'extra_title'    => $data['title'] ?? '',
                'precio_regular' => $data['regular'] ?? '',
                'precio_sale'    => $data['sale'] ?? '',
                'incluye_default'=> isset($included_defaults[$slug]) ? 1 : 0,
                'seguro_sin'     => $data['seguro']['sin'] ?? '',
                'seguro_con'     => $data['seguro']['con'] ?? '',
                'seguro_link_sin'=> $data['seguro_links']['sin'] ?? '',
                'seguro_link_con'=> $data['seguro_links']['con'] ?? '',
            ];
            $datasets['viaje_extra_prices'][] = $row_price;
            mv_viajes_csv_track_seen_extra($seen_extras, $slug, $data['id'] ?? 0, $data['title'] ?? '');
        }

        $cat_order = get_post_meta($pid, 'mv_sc_extra_cat_order', true);
        $cat_order = is_array($cat_order) ? array_values(array_map('intval', $cat_order)) : [];
        $labels = get_post_meta($pid, 'mv_sc_extra_cat_labels', true);
        $labels = is_array($labels) ? $labels : [];
        $pos = 0;
        foreach ($cat_order as $term_id) {
            $term = get_term($term_id, 'extra_category');
            if ($term && !is_wp_error($term)) {
                $datasets['viaje_extra_categories'][] = [
                    'product_key' => $key,
                    'position'    => $pos,
                    'term_slug'   => $term->slug,
                    'term_name'   => $term->name,
                    'label'       => isset($labels[$term_id]) ? $labels[$term_id] : '',
                ];
                $pos++;
            }
        }

        $descuentos = mv_viajes_csv_export_descuentos_personas($pid);
        foreach ($descuentos as $index => $desc) {
            $datasets['viaje_descuentos'][] = [
                'product_key' => $key,
                'position'    => $index,
                'desde'       => $desc['desde'] ?? '',
                'hasta'       => $desc['hasta'] ?? '',
                'pct'         => $desc['pct'] ?? '',
            ];
        }

    }

    foreach ($seen_extras as $slug => $info) {
        $slug = mv_viajes_csv_normalize_extra_slug($slug);
        $info = is_array($info) ? $info : ['id' => (int)$info, 'title' => ''];
        $id = isset($info['id']) ? (int)$info['id'] : 0;
        $title = isset($info['title']) ? $info['title'] : '';
        if (!$id && $title) {
            $maybe = get_page_by_title($title, OBJECT, 'extra');
            if ($maybe) {
                $id = (int)$maybe->ID;
            }
        }
        $cats = [];
        if ($id) {
            $terms = get_the_terms($id, 'extra_category');
            if (!is_wp_error($terms) && $terms) {
                foreach ($terms as $term) {
                    $cats[] = $term->slug;
                }
            }
        }
        $datasets['extras_catalogo'][] = [
            'extra_id'    => $id,
            'extra_slug'  => $slug,
            'extra_title' => $title ?: ($id ? get_the_title($id) : ''),
            'categories'  => implode('|', $cats),
        ];
    }

    return $datasets;
}

function mv_viajes_csv_write_csv_to_zip($zip, $filename, $rows, $headers){
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, $headers);
    foreach ($rows as $row) {
        $ordered = [];
        foreach ($headers as $header) {
            $ordered[] = isset($row[$header]) ? $row[$header] : '';
        }
        fputcsv($stream, $ordered);
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    $zip->addFromString($filename, $csv);
}

function mv_viajes_csv_handle_export(){
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos suficientes.', 'manaslu'));
    }
    check_admin_referer('mv_viajes_export', 'mv_viajes_nonce');

    if (!class_exists('ZipArchive')) {
        wp_die(__('La extensión ZipArchive no está disponible en el servidor.', 'manaslu'));
    }

    $datasets = mv_viajes_csv_collect_export_data();
    $tmp = wp_tempnam('viajes-export.zip');
    $zip = new ZipArchive();
    if (true !== $zip->open($tmp, ZipArchive::OVERWRITE)) {
        wp_die(__('No se pudo crear el ZIP.', 'manaslu'));
    }

    mv_viajes_csv_write_csv_to_zip($zip, 'viajes.csv', $datasets['viajes'], [
        'product_key','product_id','sku','name','slug','status','regular_price','sale_price','allowed_coupon','grupo','minimo_grupo','maximo_grupo','tipo_de_viaje','estilo_viaje','nivel','duracion','pais','cordillera','regimen_alojamiento','forma_de_viajar','guia','transporte_interior','mapa_embed','incluido','no_incluido','actividades_opcionales_no_incluidas','transfer','alojamiento_y_comidas','staff_durante_el_viaje','material_recomendado','informacion_adicional','parrafo','imagen','galeria_imagenes'
    ]);

    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_fechas.csv', $datasets['viaje_fechas'], ['product_key','index','inicio','fin','precio_normal','precio_rebajado','concepto','tipo_grupo','cupo_total','estado']);
    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_fechas_extras.csv', $datasets['viaje_fechas_extras'], ['product_key','index','extra_slug','extra_title','precio_regular','precio_sale','incluye','pct']);
    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_extras.csv', $datasets['viaje_extras'], ['product_key','extra_slug','extra_title']);
    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_extra_prices.csv', $datasets['viaje_extra_prices'], ['product_key','extra_slug','extra_title','precio_regular','precio_sale','incluye_default','seguro_sin','seguro_con','seguro_link_sin','seguro_link_con']);
    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_extra_categories.csv', $datasets['viaje_extra_categories'], ['product_key','position','term_slug','term_name','label']);
    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_descuentos.csv', $datasets['viaje_descuentos'], ['product_key','position','desde','hasta','pct']);
    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_itinerario.csv', $datasets['viaje_itinerario'], ['product_key','position','titulo','descripcion','imagen']);
    mv_viajes_csv_write_csv_to_zip($zip, 'viaje_faqs.csv', $datasets['viaje_faqs'], ['product_key','position','titulo','parrafo']);
    mv_viajes_csv_write_csv_to_zip($zip, 'extras_catalogo.csv', $datasets['extras_catalogo'], ['extra_id','extra_slug','extra_title','categories']);

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="viajes-export-' . date('Ymd-His') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

/* ------------------------------------------
 * Import helpers
 * ------------------------------------------ */
function mv_viajes_csv_read_csv_from_zip($zip, $filename){
    $index = $zip->locateName($filename, ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR);
    if ($index === false) {
        return [];
    }
    $content = $zip->getFromIndex($index);
    if ($content === false) {
        return [];
    }
    $rows = [];
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);
    $headers = [];
    if (($data = fgetcsv($handle)) !== false) {
        $headers = $data;
    }
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) === 1 && $data[0] === null) {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $header) {
            $row[$header] = isset($data[$i]) ? $data[$i] : '';
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function mv_viajes_csv_find_product_by_key($key){
    $parsed = mv_viajes_csv_parse_product_key($key);
    if (!$parsed['type']) return 0;
    switch ($parsed['type']) {
        case 'sku':
            if (function_exists('wc_get_product_id_by_sku')) {
                $pid = wc_get_product_id_by_sku($parsed['value']);
                return $pid ? (int)$pid : 0;
            }
            break;
        case 'slug':
            $post = get_page_by_path($parsed['value'], OBJECT, 'product');
            return $post ? (int)$post->ID : 0;
        case 'id':
            return get_post_type($parsed['value']) === 'product' ? (int)$parsed['value'] : 0;
    }
    return 0;
}

function mv_viajes_csv_ensure_extra($slug, $title = '', $categories = [], array &$cache = null){
    $slug = mv_viajes_csv_normalize_extra_slug($slug);
    if ($slug === '' && $title === '') {
        return 0;
    }
    if ($slug === '' && $title !== '') {
        $slug = mv_viajes_csv_normalize_extra_slug($title);
    }
    $extra_id = mv_viajes_csv_get_extra_id_from_slug($slug, $title, $cache);
    if (!$extra_id) {
        $extra_id = wp_insert_post([
            'post_type'   => 'extra',
            'post_status' => 'publish',
            'post_title'  => $title ? sanitize_text_field($title) : ucwords(str_replace('-', ' ', $slug)),
            'post_name'   => $slug ?: mv_viajes_csv_normalize_extra_slug($title),
        ]);
        if ($cache !== null && $slug) {
            $cache[$slug] = (int)$extra_id;
        }
    }
    if ($extra_id && !empty($categories) && is_array($categories)) {
        $terms = [];
        foreach ($categories as $term_slug) {
            $term_slug = sanitize_title($term_slug);
            if (!$term_slug) continue;
            $term = get_term_by('slug', $term_slug, 'extra_category');
            if (!$term) {
                $term_data = wp_insert_term(ucwords(str_replace('-', ' ', $term_slug)), 'extra_category', ['slug' => $term_slug]);
                if (!is_wp_error($term_data)) {
                    $term = get_term($term_data['term_id'], 'extra_category');
                }
            }
            if ($term && !is_wp_error($term)) {
                $terms[] = (int)$term->term_id;
            }
        }
        if (!empty($terms)) {
            wp_set_object_terms($extra_id, $terms, 'extra_category');
        }
    }
    return (int)$extra_id;
}

function mv_viajes_csv_handle_import(){
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos suficientes.', 'manaslu'));
    }
    check_admin_referer('mv_viajes_import', 'mv_viajes_nonce');

    if (empty($_FILES['mv_viajes_zip']['tmp_name'])) {
        wp_redirect(add_query_arg('mv_msg', urlencode(__('No se recibió archivo.', 'manaslu')), wp_get_referer()));
        exit;
    }

    $file = $_FILES['mv_viajes_zip'];
    $tmp  = $file['tmp_name'];

    if (!class_exists('ZipArchive')) {
        wp_redirect(add_query_arg('mv_msg', urlencode(__('ZipArchive no está disponible.', 'manaslu')), wp_get_referer()));
        exit;
    }

    $zip = new ZipArchive();
    if (true !== $zip->open($tmp)) {
        wp_redirect(add_query_arg('mv_msg', urlencode(__('No se pudo abrir el ZIP.', 'manaslu')), wp_get_referer()));
        exit;
    }

    $datasets = [
        'viajes'                 => mv_viajes_csv_read_csv_from_zip($zip, 'viajes.csv'),
        'viaje_fechas'           => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_fechas.csv'),
        'viaje_fechas_extras'    => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_fechas_extras.csv'),
        'viaje_extras'           => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_extras.csv'),
        'viaje_extra_prices'     => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_extra_prices.csv'),
        'viaje_extra_categories' => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_extra_categories.csv'),
        'viaje_descuentos'       => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_descuentos.csv'),
        'viaje_itinerario'       => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_itinerario.csv'),
        'viaje_faqs'             => mv_viajes_csv_read_csv_from_zip($zip, 'viaje_faqs.csv'),
        'extras_catalogo'        => mv_viajes_csv_read_csv_from_zip($zip, 'extras_catalogo.csv'),
    ];
    $zip->close();

    $extra_slug_map = [];
    foreach ($datasets['extras_catalogo'] as $row) {
        $slug = mv_viajes_csv_normalize_extra_slug($row['extra_slug'] ?? '');
        if (!$slug) continue;
        $cats = isset($row['categories']) ? array_filter(array_map('trim', explode('|', $row['categories']))) : [];
        $title = $row['extra_title'] ?? '';
        $extra_id = mv_viajes_csv_ensure_extra($slug, $title, $cats, $extra_slug_map);
        if ($extra_id && $slug) {
            $extra_slug_map[$slug] = $extra_id;
        }
    }

    $product_map = [];
    foreach ($datasets['viajes'] as $row) {
        $key = $row['product_key'];
        $product_id = mv_viajes_csv_find_product_by_key($key);
        $creating = false;
        if (!$product_id) {
            if (class_exists('WC_Product_Viaje')) {
                $product = new WC_Product_Viaje();
            } else {
                $product = new WC_Product_Simple();
                if (method_exists($product, 'set_type')) {
                    $product->set_type('viaje');
                }
            }
            $product->set_status($row['status'] ?: 'publish');
            $product->set_name($row['name'] ?: 'Viaje sin título');
            if (!empty($row['slug'])) {
                $product->set_slug(sanitize_title($row['slug']));
            }
            if (!empty($row['sku'])) {
                $product->set_sku($row['sku']);
            }
            $product->set_regular_price($row['regular_price']);
            $product->set_sale_price($row['sale_price']);
            $product_id = $product->save();
            $creating = true;
        } else {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            if (method_exists($product, 'set_type') && $product->get_type() !== 'viaje') {
                $product->set_type('viaje');
            }
            $product->set_status($row['status'] ?: $product->get_status());
            $product->set_name($row['name'] ?: $product->get_name());
            if (!empty($row['slug'])) {
                $product->set_slug(sanitize_title($row['slug']));
            }
            if (!empty($row['sku'])) {
                $product->set_sku($row['sku']);
            }
            $product->set_regular_price($row['regular_price']);
            $product->set_sale_price($row['sale_price']);
            $product->save();
        }
        if (!empty($row['allowed_coupon'])) {
            $coupon_id = mv_viajes_csv_resolve_coupon_id($row['allowed_coupon']);
            if ($coupon_id) {
                update_post_meta($product_id, '_mv_allowed_coupon', $coupon_id);
            } else {
                delete_post_meta($product_id, '_mv_allowed_coupon');
            }
        } else {
            delete_post_meta($product_id, '_mv_allowed_coupon');
        }

        $simple_fields = ['grupo','minimo_grupo','maximo_grupo','tipo_de_viaje','estilo_viaje','nivel','duracion','pais','cordillera','regimen_alojamiento','forma_de_viajar','guia','transporte_interior','mapa_embed','incluido','no_incluido','actividades_opcionales_no_incluidas','transfer','alojamiento_y_comidas','staff_durante_el_viaje','material_recomendado','informacion_adicional','parrafo','imagen','galeria_imagenes'];
        $acf_payload = [];
        foreach ($simple_fields as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $value = $row[$field];
            if ($field === 'imagen' && !empty($value)) {
                $attachment_id = attachment_url_to_postid($value);
                if ($attachment_id) {
                    $value = $attachment_id;
                }
            } elseif ($field === 'galeria_imagenes' && !empty($value)) {
                $ids = [];
                foreach (explode('|', $value) as $url) {
                    $url = trim($url);
                    if (!$url) continue;
                    $aid = attachment_url_to_postid($url);
                    if ($aid) {
                        $ids[] = $aid;
                    }
                }
                $value = $ids;
            }
            $acf_payload[$field] = $value;
        }
        if (!empty($acf_payload)) {
            mv_viajes_csv_apply_acf_data($product_id, $acf_payload);
        }

        $product_map[$key] = $product_id;
    }

    $fechas_by_product = [];
    foreach ($datasets['viaje_fechas'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $idx = (int)$row['index'];
        $fechas_by_product[$product_map[$key]][$idx] = [
            'inicio'         => $row['inicio'],
            'fin'            => $row['fin'],
            'precio_normal'  => $row['precio_normal'],
            'precio_rebajado'=> $row['precio_rebajado'],
            'concepto'       => $row['concepto'],
            'tipo_grupo'     => $row['tipo_grupo'],
            'cupo_total'     => $row['cupo_total'],
            'estado'         => $row['estado'],
        ];
    }
    foreach ($fechas_by_product as $pid => $rows) {
        ksort($rows);
        update_post_meta($pid, '_viaje_fechas', array_values($rows));
    }

    $fecha_extras_by_product = [];
    foreach ($datasets['viaje_fechas_extras'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $pid = $product_map[$key];
        $idx = (int)$row['index'];
        $slug = mv_viajes_csv_normalize_extra_slug($row['extra_slug']);
        $title = $row['extra_title'] ?? '';
        $extra_id = mv_viajes_csv_get_extra_id_from_slug($slug, $title, $extra_slug_map);
        if (!$extra_id) continue;
        $fecha_extras_by_product[$pid][$idx][$extra_id] = [
            'regular'  => $row['precio_regular'],
            'sale'     => $row['precio_sale'],
            'included' => $row['incluye'],
            'pct'      => $row['pct'],
        ];
    }
    foreach ($fecha_extras_by_product as $pid => $rows) {
        ksort($rows);
        update_post_meta($pid, '_viaje_fechas_extras', $rows);
    }

    $itinerario_by_product = [];
    foreach ($datasets['viaje_itinerario'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $pid = $product_map[$key];
        $pos = (int)$row['position'];
        $itinerario_by_product[$pid][$pos] = [
            'titulo'      => $row['titulo'],
            'descripcion' => $row['descripcion'],
            'imagen'      => $row['imagen'],
        ];
    }

    $faqs_by_product = [];
    foreach ($datasets['viaje_faqs'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $pid = $product_map[$key];
        $pos = (int)$row['position'];
        $faqs_by_product[$pid][$pos] = [
            'titulo'  => $row['titulo'],
            'parrafo' => $row['parrafo'],
        ];
    }

    $extras_by_product = [];
    foreach ($datasets['viaje_extras'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $slug = mv_viajes_csv_normalize_extra_slug($row['extra_slug']);
        if (!$slug) continue;
        $extra_id = mv_viajes_csv_get_extra_id_from_slug($slug, $row['extra_title'] ?? '', $extra_slug_map);
        if ($extra_id) {
            $extra_slug_map[$slug] = $extra_id;
            $extras_by_product[$product_map[$key]][] = $extra_id;
        }
    }
    foreach ($extras_by_product as $pid => $ids) {
        $ids = array_values(array_unique($ids));
        update_post_meta($pid, 'extras_asignados', $ids);
    }

    $extra_prices_by_product = [];
    $included_defaults = [];
    foreach ($datasets['viaje_extra_prices'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $slug = mv_viajes_csv_normalize_extra_slug($row['extra_slug']);
        if (!$slug) continue;
        $eid = mv_viajes_csv_get_extra_id_from_slug($slug, $row['extra_title'] ?? '', $extra_slug_map);
        if (!$eid) continue;
        $extra_slug_map[$slug] = $eid;
        $pid = $product_map[$key];
        $extra_prices_by_product[$pid][$eid] = [
            'regular'      => $row['precio_regular'],
            'sale'         => $row['precio_sale'],
            'seguro'       => [
                'sin' => $row['seguro_sin'],
                'con' => $row['seguro_con'],
            ],
            'seguro_links' => [
                'sin' => $row['seguro_link_sin'],
                'con' => $row['seguro_link_con'],
            ],
        ];
        if (!empty($row['incluye_default'])) {
            $included_defaults[$pid][] = $eid;
        }
    }
    foreach ($extra_prices_by_product as $pid => $map) {
        update_post_meta($pid, 'mv_extra_prices', $map);
    }
    foreach ($included_defaults as $pid => $ids) {
        update_post_meta($pid, 'mv_included_extras', array_values(array_unique($ids)));
    }

    foreach ($itinerario_by_product as $pid => $rows) {
        ksort($rows);
        $formatted = [];
        foreach ($rows as $row) {
            $img = $row['imagen'];
            if (is_numeric($img)) {
                $img_id = (int) $img;
            } else {
                $img_id = $img ? attachment_url_to_postid($img) : 0;
            }
            $formatted[] = [
                'titulo'      => $row['titulo'],
                'descripcion' => $row['descripcion'],
                'imagen'      => $img_id,
            ];
        }
        mv_viajes_csv_apply_acf_data($pid, ['itinerario' => $formatted]);
    }

    foreach ($faqs_by_product as $pid => $rows) {
        ksort($rows);
        $formatted = [];
        foreach ($rows as $row) {
            $formatted[] = [
                'titulo'  => $row['titulo'],
                'parrafo' => $row['parrafo'],
            ];
        }
        mv_viajes_csv_apply_acf_data($pid, ['faqs' => $formatted]);
    }

    $cat_order_by_product = [];
    $cat_label_by_product = [];
    foreach ($datasets['viaje_extra_categories'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $term = get_term_by('slug', sanitize_title($row['term_slug']), 'extra_category');
        if (!$term) continue;
        $pid = $product_map[$key];
        $position = (int)$row['position'];
        $cat_order_by_product[$pid][$position] = (int)$term->term_id;
        if (!empty($row['label'])) {
            $cat_label_by_product[$pid][$term->term_id] = $row['label'];
        }
    }
    foreach ($cat_order_by_product as $pid => $items) {
        ksort($items);
        update_post_meta($pid, 'mv_sc_extra_cat_order', array_values($items));
    }
    foreach ($cat_label_by_product as $pid => $items) {
        update_post_meta($pid, 'mv_sc_extra_cat_labels', $items);
    }

    $descuentos_by_product = [];
    foreach ($datasets['viaje_descuentos'] as $row) {
        $key = $row['product_key'];
        if (!isset($product_map[$key])) continue;
        $pid = $product_map[$key];
        $pos = (int)$row['position'];
        $descuentos_by_product[$pid][$pos] = [
            'desde' => $row['desde'],
            'hasta' => $row['hasta'],
            'pct'   => $row['pct'],
        ];
    }
    foreach ($descuentos_by_product as $pid => $rows) {
        ksort($rows);
        update_post_meta($pid, '_mv_descuentos_personas', array_values($rows));
    }

    foreach ($product_map as $key => $pid) {
        if (!isset($itinerario_by_product[$pid])) {
            mv_viajes_csv_apply_acf_data($pid, ['itinerario' => []]);
        }
        if (!isset($faqs_by_product[$pid])) {
            mv_viajes_csv_apply_acf_data($pid, ['faqs' => []]);
        }
        if (!isset($fechas_by_product[$pid])) {
            delete_post_meta($pid, '_viaje_fechas');
        }
        if (!isset($fecha_extras_by_product[$pid])) {
            delete_post_meta($pid, '_viaje_fechas_extras');
        }
        if (!isset($extras_by_product[$pid])) {
            delete_post_meta($pid, 'extras_asignados');
        }
        if (!isset($extra_prices_by_product[$pid])) {
            delete_post_meta($pid, 'mv_extra_prices');
        }
        if (!isset($included_defaults[$pid])) {
            delete_post_meta($pid, 'mv_included_extras');
        }
        if (!isset($cat_order_by_product[$pid])) {
            delete_post_meta($pid, 'mv_sc_extra_cat_order');
        }
        if (!isset($cat_label_by_product[$pid])) {
            delete_post_meta($pid, 'mv_sc_extra_cat_labels');
        }
        if (!isset($descuentos_by_product[$pid])) {
            delete_post_meta($pid, '_mv_descuentos_personas');
        }
    }

    wp_redirect(add_query_arg('mv_msg', urlencode(__('Importación completada.', 'manaslu')), admin_url('edit.php?post_type=product&page=mv-viajes-csv')));
    exit;
}

/* ------------------------------------------
 * Extra: asegurar que exista el term 'viaje'
 * (por si el sitio se clona sin tax)
 * ------------------------------------------ */
add_action('init', function () {
    if (taxonomy_exists('product_type') && !get_term_by('slug', 'viaje', 'product_type')) {
        wp_insert_term('viaje', 'product_type');
    }
}, 5);

