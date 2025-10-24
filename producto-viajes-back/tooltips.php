<?php
/**
 * Plugin Name: Manaslu - Tooltips (MU)
 * Description: Gestiona tooltips personalizados para productos y los muestra en el frontend.
 * Version: 1.0.0
 * Author: Manaslu Adventures
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtiene la configuración base de los tooltips.
 *
 * @return array
 */
function mv_tooltip_fields_config() {
    return [
        'fecha_explicacion' => [
            'label'        => __('Fecha - explicación', 'manaslu'),
            'admin_label'  => __('Fecha (explicación)', 'manaslu'),
            'class'        => 'fecha-explicacion',
            'placeholder'  => __('Ej. Las fechas pueden variar según las condiciones.', 'manaslu'),
            'description'  => __('Se mostrará en elementos con la clase .fecha-explicacion.', 'manaslu'),
        ],
        'nivel_explicacion' => [
            'label'        => __('Nivel - explicación', 'manaslu'),
            'admin_label'  => __('Nivel (explicación)', 'manaslu'),
            'class'        => 'nivel-explicacion',
            'placeholder'  => __('Ej. Nivel 3: requiere buena condición física.', 'manaslu'),
            'description'  => __('Se mostrará en elementos con la clase .nivel-explicacion.', 'manaslu'),
        ],
        'regimen_explicacion' => [
            'label'        => __('Régimen - explicación', 'manaslu'),
            'admin_label'  => __('Régimen (explicación)', 'manaslu'),
            'class'        => 'regimen-explicacion',
            'placeholder'  => __('Ej. Pensión completa durante el trekking.', 'manaslu'),
            'description'  => __('Se mostrará en elementos con la clase .regimen-explicacion.', 'manaslu'),
        ],
        'transporte_explicacion' => [
            'label'        => __('Transporte - explicación', 'manaslu'),
            'admin_label'  => __('Transporte (explicación)', 'manaslu'),
            'class'        => 'transporte-explicacion',
            'placeholder'  => __('Ej. Traslados internos incluidos.', 'manaslu'),
            'description'  => __('Se mostrará en elementos con la clase .transporte-explicacion.', 'manaslu'),
        ],
    ];
}

/**
 * Devuelve el nombre de la meta key asociada al tooltip.
 *
 * @param string $slug
 * @return string
 */
function mv_tooltip_meta_key($slug) {
    return '_mv_tooltip_' . $slug;
}

/**
 * Añade la pestaña "Tooltips" en el editor de producto.
 *
 * @param array $tabs
 * @return array
 */
function mv_tooltips_add_product_tab($tabs) {
    $tabs['mv_tooltips'] = [
        'label'    => __('Tooltips', 'manaslu'),
        'target'   => 'mv_tooltips_data',
        'class'    => ['show_if_viaje', 'show_if_simple', 'show_if_variable', 'show_if_external', 'show_if_grouped'],
        'priority' => 32,
    ];
    return $tabs;
}
add_filter('woocommerce_product_data_tabs', 'mv_tooltips_add_product_tab', 30);

/**
 * Renderiza el contenido de la pestaña de tooltips.
 *
 */
function mv_tooltips_render_product_panel() {
    global $post;
    if (!$post) {
        return;
    }

    $fields = mv_tooltip_fields_config();
    ?>
    <div id="mv_tooltips_data" class="panel woocommerce_options_panel hidden">
        <div class="options_group">
            <?php wp_nonce_field('mv_tooltips_save', 'mv_tooltips_nonce'); ?>
            <p class="description">
                <?php esc_html_e('Completa los textos que se mostrarán como tooltip al usar las clases correspondientes en el sitio.', 'manaslu'); ?>
            </p>
            <?php foreach ($fields as $slug => $field):
                $meta_key = mv_tooltip_meta_key($slug);
                $value    = get_post_meta($post->ID, $meta_key, true);
                ?>
                <p class="form-field">
                    <label for="mv_tooltip_<?php echo esc_attr($slug); ?>"><?php echo esc_html($field['admin_label']); ?></label>
                    <input type="text"
                           class="short"
                           name="mv_tooltips[<?php echo esc_attr($slug); ?>]"
                           id="mv_tooltip_<?php echo esc_attr($slug); ?>"
                           value="<?php echo esc_attr($value); ?>"
                           placeholder="<?php echo esc_attr($field['placeholder']); ?>"
                    />
                    <?php if (!empty($field['description'])): ?>
                        <span class="description"><?php echo esc_html($field['description']); ?></span>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action('woocommerce_product_data_panels', 'mv_tooltips_render_product_panel');

/**
 * Guarda los metadatos de tooltips.
 *
 * @param WC_Product $product
 */
function mv_tooltips_save_product_meta($product) {
    if (!isset($_POST['mv_tooltips_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mv_tooltips_nonce'])), 'mv_tooltips_save')) {
        return;
    }

    $fields = mv_tooltip_fields_config();
    $values = isset($_POST['mv_tooltips']) && is_array($_POST['mv_tooltips']) ? wp_unslash($_POST['mv_tooltips']) : [];

    foreach ($fields as $slug => $field) {
        $value = isset($values[$slug]) ? sanitize_text_field($values[$slug]) : '';
        $meta_key = mv_tooltip_meta_key($slug);

        if ($value !== '') {
            update_post_meta($product->get_id(), $meta_key, $value);
        } else {
            delete_post_meta($product->get_id(), $meta_key);
        }
    }
}
add_action('woocommerce_admin_process_product_object', 'mv_tooltips_save_product_meta');

/**
 * Obtiene los tooltips asignados a un producto.
 *
 * @param int $product_id
 * @return array
 */
function mv_get_tooltips($product_id) {
    static $cache = [];
    $product_id = (int) $product_id;
    if ($product_id <= 0) {
        return [];
    }

    if (isset($cache[$product_id])) {
        return $cache[$product_id];
    }

    $fields = mv_tooltip_fields_config();
    $tooltips = [];

    foreach ($fields as $slug => $config) {
        $meta = get_post_meta($product_id, mv_tooltip_meta_key($slug), true);
        if ($meta !== '' && $meta !== null) {
            $tooltips[$slug] = $meta;
        }
    }

    $cache[$product_id] = $tooltips;
    return $tooltips;
}

/**
 * Prepara los datos a exponer en el front-end vía JS.
 *
 * @return array
 */
function mv_tooltips_localize_data() {
    $fields = mv_tooltip_fields_config();

    $data = [
        'ajaxurl'    => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('mv_tooltips'),
        'action'     => 'mv_tooltips',
        'fields'     => [],
        'initial'    => [],
        'currentId'  => null,
        'i18n'       => [
            'iconLabel' => __('Mostrar explicación', 'manaslu'),
        ],
    ];

    foreach ($fields as $slug => $config) {
        $data['fields'][$slug] = [
            'class' => $config['class'],
            'label' => $config['label'],
        ];
    }

    if (is_singular('product')) {
        $pid = get_queried_object_id();
        if ($pid) {
            $data['currentId'] = $pid;
            $tooltips = mv_get_tooltips($pid);
            if (!empty($tooltips)) {
                $data['initial'][(string) $pid] = $tooltips;
            }
        }
    }

    if (empty($data['initial'])) {
        $data['initial'] = new stdClass();
    }

    return $data;
}

/**
 * Devuelve el bloque CSS para los tooltips.
 *
 * @return string
 */
function mv_tooltips_css() {
    return <<<'CSS'
.mv-tooltip-icon {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    margin-left: 6px;
    border-radius: 50%;
    background: black;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    text-decoration: none;
}

.mv-tooltip-icon:focus {
    outline: 2px solid rgba(11, 95, 138, 0.35);
    outline-offset: 2px;
}

.mv-tooltip-icon .mv-tooltip-bubble {
    position: absolute;
    top: 50%;
    left: calc(100% + 8px);
    transform: translateY(-50%) translateY(4px);
    background: black;
    color: #fff;
    padding: 8px 10px;
    border-radius: 4px;
    min-width: 160px;
    max-width: 260px;
    font-size: 12px;
    line-height: 1.4;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 999;
    pointer-events: none;
    text-align: left;
}

.mv-tooltip-icon .mv-tooltip-bubble::before {
    content: '';
    position: absolute;
    top: 50%;
    right: 100%;
    transform: translateY(-50%);
    border: 6px solid transparent;
    border-right-color: #0b5f8a;
}

.mv-tooltip-icon:hover .mv-tooltip-bubble,
.mv-tooltip-icon:focus .mv-tooltip-bubble,
.mv-tooltip-icon:focus-within .mv-tooltip-bubble {
    opacity: 1;
    visibility: visible;
    transform: translateY(-50%);
}

@media (max-width: 782px) {
    .mv-tooltip-icon .mv-tooltip-bubble {
        left: 50%;
        top: calc(100% + 8px);
        transform: translate(-50%, 4px);
    }
    .mv-tooltip-icon .mv-tooltip-bubble::before {
        top: 0;
        right: auto;
        left: 50%;
        transform: translate(-50%, -100%);
        border-right-color: transparent;
        border-bottom-color: #0b5f8a;
    }
}
CSS;
}

/**
 * Devuelve el bloque JS que gestiona los tooltips.
 *
 * @return string
 */
function mv_tooltips_js() {
    return <<<'JS'
(function (window, document) {
    'use strict';
    if (!window || !document || typeof window.fetch !== 'function') {
        return;
    }

    var config = window.mvTooltipsData || {};
    var fieldConfig = config.fields || {};
    var classToSlug = {};
    var selectorParts = [];

    for (var slug in fieldConfig) {
        if (!Object.prototype.hasOwnProperty.call(fieldConfig, slug)) {
            continue;
        }
        var entry = fieldConfig[slug] || {};
        var cls = entry['class'];
        if (!cls) {
            continue;
        }
        classToSlug[cls] = slug;
        selectorParts.push('.' + cls);
    }

    if (!selectorParts.length) {
        return;
    }

    var selector = selectorParts.join(',');
    var cache = config.initial || {};
    var pending = {};
    var action = config.action || 'mv_tooltips';
    var boundAttr = 'data-mv-tooltip-bound';
    var iconLabel = (config.i18n && config.i18n.iconLabel) ? config.i18n.iconLabel : 'Tooltip';

    var debugEnabled = false;

    function refreshDebugFlag() {
        debugEnabled = false;
        if (window.mvTooltipsDebug === true) {
            debugEnabled = true;
        } else if (window.mvTooltipsDebug && typeof window.mvTooltipsDebug === 'object' && window.mvTooltipsDebug.enable === true) {
            debugEnabled = true;
        } else if (typeof window.localStorage !== 'undefined') {
            debugEnabled = window.localStorage.getItem('mvTooltipsDebug') === '1';
        }
    }

    function debugLog() {
        if (!debugEnabled) {
            return;
        }
        var args = Array.prototype.slice.call(arguments);
        args.unshift('[mv-tooltips]');
        if (console && typeof console.log === 'function') {
            console.log.apply(console, args);
        }
    }

    refreshDebugFlag();

    function parseId(value) {
        if (!value && value !== 0) {
            return null;
        }
        var str = String(value).trim();
        if (!str) {
            return null;
        }
        if (!/^\d+$/.test(str)) {
            return null;
        }
        var num = parseInt(str, 10);
        return isNaN(num) ? null : num;
    }

    function getAttrId(node) {
        if (!node || node.nodeType !== 1) {
            return null;
        }

        var attrNames = ['data-product-id', 'data-product_id', 'data-product', 'data-pid', 'data-post-id'];
        for (var i = 0; i < attrNames.length; i++) {
            var attr = node.getAttribute(attrNames[i]);
            var parsed = parseId(attr);
            if (parsed) {
                return parsed;
            }
        }

        if (node.dataset) {
            var dataKeys = ['productId', 'productid', 'pid', 'product', 'postId'];
            for (var j = 0; j < dataKeys.length; j++) {
                var key = dataKeys[j];
                if (node.dataset[key]) {
                    var val = parseId(node.dataset[key]);
                    if (val) {
                        return val;
                    }
                }
            }
        }

        var htmlId = node.getAttribute('id');
        if (htmlId) {
            var idMatch = htmlId.match(/(?:post|product)[_-]?(\d+)/i);
            if (idMatch) {
                return parseInt(idMatch[1], 10);
            }
        }

        var className = node.className;
        if (className && typeof className === 'string') {
            var classes = className.split(/\s+/);
            for (var k = 0; k < classes.length; k++) {
                var clsName = classes[k];
                if (!clsName) {
                    continue;
                }
                var classMatch = clsName.match(/^(?:post|product|type|item|entry)[_-]?(\d+)$/i);
                if (classMatch) {
                    return parseInt(classMatch[1], 10);
                }
            }
        }

        return null;
    }

    function resolveProductId(element) {
        var current = element;
        while (current && current !== document.body) {
            var found = getAttrId(current);
            if (found) {
                return found;
            }
            current = current.parentElement;
        }

        if (config.currentId) {
            return parseInt(config.currentId, 10);
        }

        if (document.body && document.body.className) {
            var match = document.body.className.match(/postid-(\d+)/);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        return null;
    }

    function loadTooltipData(productId) {
        var pid = String(productId);
        if (Object.prototype.hasOwnProperty.call(cache, pid)) {
            debugLog('cache hit', pid);
            return Promise.resolve(cache[pid]);
        }

        if (!config.ajaxurl) {
            cache[pid] = {};
            debugLog('ajaxurl missing; returning empty cache for', pid);
            return Promise.resolve(cache[pid]);
        }

        if (pending[pid]) {
            debugLog('request already pending for', pid);
            return pending[pid];
        }

        var form = new FormData();
        form.append('action', action);
        form.append('product_id', pid);
        if (config.nonce) {
            form.append('nonce', config.nonce);
        }

        debugLog('requesting tooltips for', pid);

        var request = fetch(config.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Request failed');
            }
            return response.json();
        }).then(function (payload) {
            if (payload && payload.success && payload.data && payload.data.fields) {
                debugLog('received fields', pid, payload.data.fields);
                cache[pid] = payload.data.fields;
            } else {
                debugLog('empty payload for', pid, payload);
                cache[pid] = {};
            }
            return cache[pid];
        }).catch(function (error) {
            debugLog('request failed for', pid, error);
            cache[pid] = cache[pid] || {};
            return cache[pid];
        });

        pending[pid] = request.then(function (result) {
            pending[pid] = null;
            return result;
        }, function (error) {
            pending[pid] = null;
            throw error;
        });

        return pending[pid];
    }

    function buildTooltip(element, text, pid, slug) {
        if (!text) {
            return;
        }

        if (element.querySelector('.mv-tooltip-icon')) {
            return;
        }

        var icon = document.createElement('span');
        icon.className = 'mv-tooltip-icon';
        icon.setAttribute('tabindex', '0');
        icon.setAttribute('role', 'button');
        icon.setAttribute('aria-label', iconLabel);

        var bubble = document.createElement('span');
        bubble.className = 'mv-tooltip-bubble';
        bubble.setAttribute('role', 'tooltip');
        var bubbleId = 'mvtt-' + pid + '-' + slug + '-' + Math.floor(Math.random() * 100000);
        bubble.setAttribute('id', bubbleId);
        bubble.textContent = text;

        icon.setAttribute('aria-describedby', bubbleId);
        icon.textContent = '?';
        icon.appendChild(bubble);

        element.appendChild(icon);
    }

    function processElement(element) {
        if (!element) {
            return;
        }

        var status = element.getAttribute(boundAttr);
        if (status === 'loading' || status === 'done') {
            return;
        }

        var slug = null;
        var classList = element.classList;
        if (classList) {
            for (var cls in classToSlug) {
                if (Object.prototype.hasOwnProperty.call(classToSlug, cls) && classList.contains(cls)) {
                    slug = classToSlug[cls];
                    break;
                }
            }
        } else {
            var elementClass = element.getAttribute('class') || '';
            for (var key in classToSlug) {
                if (!Object.prototype.hasOwnProperty.call(classToSlug, key)) {
                    continue;
                }
                if ((' ' + elementClass + ' ').indexOf(' ' + key + ' ') !== -1) {
                    slug = classToSlug[key];
                    break;
                }
            }
        }

        if (!slug) {
            debugLog('class slug not found for element', element);
            return;
        }

        var pid = resolveProductId(element);
        if (!pid) {
            debugLog('product id not found for element', element);
            return;
        }

        element.setAttribute(boundAttr, 'loading');
        debugLog('processing element', { slug: slug, pid: pid, element: element });

        loadTooltipData(pid).then(function (data) {
            if (!data || !Object.prototype.hasOwnProperty.call(data, slug)) {
                debugLog('no tooltip text for slug', slug, 'pid', pid, data);
                element.setAttribute(boundAttr, 'done');
                return;
            }
            buildTooltip(element, data[slug], pid, slug);
            element.setAttribute(boundAttr, 'done');
            debugLog('tooltip attached', { slug: slug, pid: pid });
        }).catch(function () {
            debugLog('tooltip load failed', slug, pid);
            element.removeAttribute(boundAttr);
        });
    }

    function scan(root) {
        var context = root && root.querySelectorAll ? root : document;
        var nodes = context.querySelectorAll(selector);
        for (var i = 0; i < nodes.length; i++) {
            processElement(nodes[i]);
        }
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        debugLog('initial scan');
        scan(document);
    });

    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];
                    if (!node || node.nodeType !== 1) {
                        continue;
                    }
                    if (node.matches && node.matches(selector)) {
                        processElement(node);
                    }
                    scan(node);
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        debugLog('mutation observer attached');
    }

    var api = window.mvTooltips || {};
    api.scan = scan;
    api.getCache = function () {
        return cache;
    };
    api.getPending = function () {
        return pending;
    };
    api.getConfig = function () {
        return config;
    };
    api.enableDebug = function () {
        if (typeof window.localStorage !== 'undefined') {
            window.localStorage.setItem('mvTooltipsDebug', '1');
        }
        window.mvTooltipsDebug = true;
        refreshDebugFlag();
        debugLog('debug enabled via api');
    };
    api.disableDebug = function () {
        if (typeof window.localStorage !== 'undefined') {
            window.localStorage.removeItem('mvTooltipsDebug');
        }
        window.mvTooltipsDebug = false;
        refreshDebugFlag();
        debugLog('debug disabled via api');
    };
    api.refresh = function () {
        refreshDebugFlag();
        scan(document);
    };

    window.mvTooltips = api;

    if (debugEnabled) {
        debugLog('debug active');
    }
})(window, document);
JS;
}

/**
 * Encola los assets necesarios en el frontend.
 */
function mv_tooltips_enqueue_assets() {
    if (is_admin()) {
        return;
    }

    wp_register_script('mv-tooltips', false, [], '1.0.0', true);
    wp_localize_script('mv-tooltips', 'mvTooltipsData', mv_tooltips_localize_data());
    wp_enqueue_script('mv-tooltips');
    wp_add_inline_script('mv-tooltips', mv_tooltips_js());

    wp_register_style('mv-tooltips', false, [], '1.0.0');
    wp_enqueue_style('mv-tooltips');
    wp_add_inline_style('mv-tooltips', mv_tooltips_css());
}
add_action('wp_enqueue_scripts', 'mv_tooltips_enqueue_assets', 50);

/**
 * Endpoint AJAX para obtener los tooltips de un producto.
 */
function mv_tooltips_ajax_handler() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'mv_tooltips')) {
        wp_send_json_error(['message' => __('Nonce inválido.', 'manaslu')], 403);
    }

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error(['message' => __('ID de producto no válido.', 'manaslu')], 400);
    }

    $data = mv_get_tooltips($product_id);
    wp_send_json_success([
        'id'     => $product_id,
        'fields' => $data,
    ]);
}
add_action('wp_ajax_mv_tooltips', 'mv_tooltips_ajax_handler');
add_action('wp_ajax_nopriv_mv_tooltips', 'mv_tooltips_ajax_handler');
