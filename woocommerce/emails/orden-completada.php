<?php
/**
 * Plantilla personalizada para el email de viaje completado.
 *
 * Basada en customer-completed-order.php de WooCommerce 9.9.0.
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('manaslu_email_normalize_meta_array')) {
    /**
     * Normaliza un valor de meta en array.
     *
     * @param mixed $value Valor a normalizar.
     * @return array
     */
    function manaslu_email_normalize_meta_array($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (is_string($value)) {
            $maybe = maybe_unserialize($value);
            if (is_array($maybe)) {
                return $maybe;
            }

            $json = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
        }

        return [];
    }
}

if (!function_exists('manaslu_email_collect_item_extras')) {
    /**
     * Extrae los extras (incluyendo seguros) de un ítem del pedido.
     *
     * @param WC_Order_Item_Product $item Order item.
     * @return array{extras: array<int, array<string, mixed>>, seguros: array<int, array<string, mixed>>}
     */
    function manaslu_email_collect_item_extras(WC_Order_Item_Product $item): array
    {
        $extras  = [];
        $seguros = [];
        $sources = [];
        $candidate_keys = ['viaje_extras', 'manaslu_extras', 'extras', 'mv_extras'];

        foreach ($candidate_keys as $key) {
            $value = $item->get_meta($key, true);
            if (empty($value)) {
                $value = $item->get_meta('_' . $key, true);
            }
            if (!empty($value)) {
                $sources[] = $value;
            }
        }

        foreach ($item->get_meta_data() as $meta_obj) {
            $meta = $meta_obj->get_data();
            if (!isset($meta['key']) || !preg_match('/extra|seguro/i', (string) $meta['key'])) {
                continue;
            }
            $sources[] = $meta['value'];
        }

        foreach ($sources as $raw) {
            $data = manaslu_email_normalize_meta_array($raw);
            foreach ($data as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $title = isset($entry['title']) ? (string) $entry['title'] : '';
                if ($title === '' && isset($entry['name'])) {
                    $title = (string) $entry['name'];
                }
                if ($title === '') {
                    continue;
                }

                $qty      = isset($entry['qty']) ? max(0, (int) $entry['qty']) : 0;
                $included = isset($entry['included']) ? max(0, (int) $entry['included']) : 0;
                $variant  = isset($entry['variant']) ? (string) $entry['variant'] : '';
                $cat      = isset($entry['cat']) ? (string) $entry['cat'] : '';

                $row = [
                    'title'    => $title,
                    'qty'      => $qty,
                    'included' => $included,
                    'variant'  => $variant,
                    'cat'      => $cat,
                ];

                $target = $cat === 'seguro';
                if (!$target && stripos($title, 'seguro') !== false) {
                    $target = true;
                }

                if ($target) {
                    $seguros[] = $row;
                } else {
                    $extras[] = $row;
                }
            }
        }

        if (empty($extras) && empty($seguros)) {
            foreach ($item->get_formatted_meta_data('') as $meta) {
                $label = trim((string) $meta->display_key);
                $value = wp_strip_all_tags((string) $meta->display_value, true);
                if ($label === '' || $value === '') {
                    continue;
                }

                if (stripos($label, 'seguro') !== false) {
                    $seguros[] = ['title' => $label . ': ' . $value];
                } elseif (stripos($label, 'extra') !== false || stripos($label, 'adicional') !== false) {
                    $extras[] = ['title' => $label . ': ' . $value];
                }
            }
        }

        return [
            'extras'  => $extras,
            'seguros' => $seguros,
        ];
    }
}

if (!function_exists('manaslu_email_collect_passengers')) {
    /**
     * Obtiene la lista de pasajeros almacenada en el pedido.
     *
     * @param WC_Order                 $order Pedido.
     * @param WC_Order_Item_Product|null $item Item específico (para datos por ítem).
     * @return string[]
     */
    function manaslu_email_collect_passengers(WC_Order $order, ?WC_Order_Item_Product $item = null): array
    {
        $passengers = [];
        $sources    = [];
        $keys       = ['mv_travelers', 'mv_pasajeros', 'viaje_personas', 'pasajeros', 'viajeros'];

        foreach ($keys as $key) {
            $sources[] = $order->get_meta($key, true);
            $sources[] = $order->get_meta('_' . $key, true);
            if ($item) {
                $sources[] = $item->get_meta($key, true);
                $sources[] = $item->get_meta('_' . $key, true);
            }
        }

        $meta_objects = array_merge($order->get_meta_data(), $item ? $item->get_meta_data() : []);
        foreach ($meta_objects as $meta) {
            $data = $meta->get_data();
            if (!isset($data['key']) || !preg_match('/pasaj|viajer/i', (string) $data['key'])) {
                continue;
            }
            $sources[] = $data['value'];
        }

        foreach ($sources as $source) {
            if (empty($source)) {
                continue;
            }

            $rows = manaslu_email_normalize_meta_array($source);
            if (!empty($rows)) {
                foreach ($rows as $entry) {
                    if (is_string($entry)) {
                        $entry = trim($entry);
                        if ($entry !== '') {
                            $passengers[] = $entry;
                        }
                        continue;
                    }

                    if (!is_array($entry)) {
                        continue;
                    }

                    $first = '';
                    $last  = '';
                    $full  = '';

                    foreach (['name', 'nombre', 'full_name', 'titulo'] as $candidate) {
                        if (isset($entry[$candidate]) && $entry[$candidate] !== '') {
                            $full = trim((string) $entry[$candidate]);
                            break;
                        }
                    }

                    if ($full === '') {
                        foreach (['first_name', 'nombre', 'first'] as $candidate) {
                            if (isset($entry[$candidate]) && $entry[$candidate] !== '') {
                                $first = trim((string) $entry[$candidate]);
                                break;
                            }
                        }
                        foreach (['last_name', 'apellidos', 'surname', 'last'] as $candidate) {
                            if (isset($entry[$candidate]) && $entry[$candidate] !== '') {
                                $last = trim((string) $entry[$candidate]);
                                break;
                            }
                        }
                        $full = trim($first . ' ' . $last);
                    }

                    if ($full !== '') {
                        $extra_data = [];
                        foreach (['document', 'dni', 'passport', 'documento'] as $doc_key) {
                            if (!empty($entry[$doc_key])) {
                                $extra_data[] = sprintf(__('Documento: %s', 'manaslu'), trim((string) $entry[$doc_key]));
                                break;
                            }
                        }
                        if (!empty($entry['edad'])) {
                            $extra_data[] = sprintf(__('Edad: %s', 'manaslu'), trim((string) $entry['edad']));
                        }
                        if ($extra_data) {
                            $full .= ' — ' . implode(' · ', $extra_data);
                        }
                        $passengers[] = $full;
                    }
                }
                continue;
            }

            if (is_string($source)) {
                $lines = preg_split('/\r?\n+/', $source);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $passengers[] = $line;
                    }
                }
            }
        }

        if (empty($passengers)) {
            return [];
        }

        $passengers = array_values(array_unique($passengers));
        return array_filter($passengers, 'strlen');
    }
}

if (!function_exists('manaslu_email_get_field')) {
    /**
     * Obtiene un campo personalizado (ACF/meta) del viaje.
     *
     * @param int    $product_id ID del producto/viaje.
     * @param string $field_name Nombre del campo.
     * @return string
     */
    function manaslu_email_get_field(int $product_id, string $field_name): string
    {
        $value = '';

        if (function_exists('get_field')) {
            $value = get_field($field_name, $product_id);
            if (is_array($value)) {
                $value = wp_json_encode($value);
            }
        }

        if ($value === '' || $value === null) {
            $value = get_post_meta($product_id, $field_name, true);
        }

        return is_string($value) ? $value : '';
    }
}

if (!function_exists('manaslu_email_get_flight_preference')) {
    /**
     * Determina la preferencia de vuelos almacenada.
     */
    function manaslu_email_get_flight_preference(WC_Order $order): string
    {
        $candidates = ['tomar_vuelo', 'mv_tomar_vuelo', 'buscar_vuelo', 'want_flights'];
        foreach ($candidates as $key) {
            $value = $order->get_meta($key, true);
            if (empty($value)) {
                $value = $order->get_meta('_' . $key, true);
            }
            if (is_array($value) && isset($value['want'])) {
                $want = strtolower((string) $value['want']);
                if ($want === 'si' || $want === 'sí') {
                    return __('Sí, queremos ayuda con los vuelos', 'manaslu');
                }
                if ($want === 'no') {
                    return __('No, gestionaremos los vuelos por nuestra cuenta', 'manaslu');
                }
            }
            if (is_string($value) && $value !== '') {
                $normalized = strtolower(wp_strip_all_tags($value));
                if (strpos($normalized, 'sí') !== false || strpos($normalized, 'si') !== false) {
                    return __('Sí, queremos ayuda con los vuelos', 'manaslu');
                }
                if (strpos($normalized, 'no') !== false) {
                    return __('No, gestionaremos los vuelos por nuestra cuenta', 'manaslu');
                }
            }
        }

        return __('No se registró una preferencia.', 'manaslu');
    }
}

$email_improvements_enabled = class_exists(FeaturesUtil::class) && FeaturesUtil::feature_is_enabled('email_improvements');

$confirmation_date = $order->get_date_completed() ?: $order->get_date_paid() ?: $order->get_date_modified();
if (!$confirmation_date) {
    $confirmation_date = $order->get_date_created();
}

$confirmation_date_text = $confirmation_date ? wc_format_datetime($confirmation_date) : '';
$flight_preference      = manaslu_email_get_flight_preference($order);

$order_discount_lines = [];
$discount_total = (float) $order->get_discount_total();
if ($discount_total > 0) {
    $order_discount_lines[] = sprintf(__('Total de descuentos aplicados: %s', 'manaslu'), wp_kses_post(wc_price($discount_total)));
}

$coupon_codes = $order->get_coupon_codes();
if (!empty($coupon_codes)) {
    $order_discount_lines[] = sprintf(__('Códigos utilizados: %s', 'manaslu'), esc_html(implode(', ', $coupon_codes)));
}

$viajes = [];

foreach ($order->get_items() as $item_id => $item) {
    if (!$item instanceof WC_Order_Item_Product) {
        continue;
    }

    $product    = $item->get_product();
    $product_id = $product ? $product->get_id() : $item->get_product_id();
    $nombre     = $product ? $product->get_name() : $item->get_name();
    $duracion   = $product_id ? manaslu_email_get_field($product_id, 'duracion') : '';
    $incluye    = $product_id ? manaslu_email_get_field($product_id, 'incluido') : '';
    $no_incluye = $product_id ? manaslu_email_get_field($product_id, 'no_incluido') : '';

    $inicio = $item->get_meta('Fecha inicio');
    if (!$inicio) {
        $inicio = $item->get_meta('fecha_inicio');
    }

    $extra_data = manaslu_email_collect_item_extras($item);
    $pasajeros  = manaslu_email_collect_passengers($order, $item);
    $item_discounts = $order_discount_lines;

    $grupo_pax_pct   = $item->get_meta('Descuento grupo %');
    $grupo_pax_monto = $item->get_meta('Descuento grupo monto');
    if ($grupo_pax_pct || $grupo_pax_monto) {
        $line = __('Descuento por grupo aplicado', 'manaslu');
        $details = [];
        if ($grupo_pax_pct) {
            $details[] = sprintf('%s%%', wc_clean($grupo_pax_pct));
        }
        if ($grupo_pax_monto) {
            $details[] = wc_price((float) $grupo_pax_monto);
        }
        if ($details) {
            $line .= ': ' . implode(' — ', $details);
        }
        $item_discounts[] = $line;
    }

    $viajes[] = [
        'nombre'        => $nombre,
        'duracion'      => $duracion,
        'inicio'        => $inicio,
        'extras'        => $extra_data['extras'],
        'seguros'       => $extra_data['seguros'],
        'pasajeros'     => $pasajeros,
        'incluye'       => $incluye,
        'no_incluye'    => $no_incluye,
        'discounts'     => $item_discounts,
    ];
}

$deposit_amount = max(0.0, (float) $order->get_total() - (float) $order->get_total_refunded());
$payment_method = $order->get_payment_method_title();

/*
 * Cabecera del email
 */
do_action('woocommerce_email_header', $email_heading, $email);

?>
<?php echo $email_improvements_enabled ? '<div class="email-introduction" style="padding-bottom:24px;">' : ''; ?>
    <p>
        <?php
        if (!empty($order->get_billing_first_name())) {
            printf(esc_html__('Hola %s,', 'manaslu'), esc_html($order->get_billing_first_name()));
        } else {
            esc_html_e('Hola,', 'manaslu');
        }
        ?>
    </p>
    <p><?php esc_html_e('Hemos finalizado la gestión de tu viaje. Aquí tienes toda la información relevante para que sigas planificando la aventura.', 'manaslu'); ?></p>
<?php if ($email_improvements_enabled) : ?>
    <p><?php esc_html_e('A continuación encontrarás un resumen del viaje reservado, extras seleccionados y condiciones de pago.', 'manaslu'); ?></p>
<?php endif; ?>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php if (!empty($viajes)) : ?>
    <?php foreach ($viajes as $viaje) : ?>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:24px;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;background:#ffffff;">
            <tr>
                <td style="padding:24px 28px;font-family:'Host Grotesk',-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a;">
                    <h2 style="margin:0 0 12px;font-size:20px;line-height:1.3;font-weight:700;color:#0f172a;">
                        <?php echo esc_html__('Resumen del viaje', 'manaslu'); ?>
                    </h2>
                    <p style="margin:0 0 8px;font-size:16px;line-height:1.5;font-weight:700;color:#fb2240;">
                        <?php echo esc_html($viaje['nombre']); ?>
                    </p>
                    <?php if (!empty($viaje['duracion'])) : ?>
                        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#0f172a;">
                            <strong><?php esc_html_e('Programa:', 'manaslu'); ?></strong>
                            <?php echo esc_html($viaje['duracion']); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($confirmation_date_text) : ?>
                        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#0f172a;">
                            <strong><?php esc_html_e('Fecha de confirmación de la reserva:', 'manaslu'); ?></strong>
                            <?php echo esc_html($confirmation_date_text); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($viaje['inicio'])) : ?>
                        <?php
                        $departure_date = null;
                        if ($viaje['inicio'] instanceof WC_DateTime) {
                            $departure_date = $viaje['inicio'];
                        } else {
                            $candidate = wc_string_to_datetime((string) $viaje['inicio']);
                            if ($candidate instanceof WC_DateTime) {
                                $departure_date = $candidate;
                            } else {
                                $timestamp = strtotime((string) $viaje['inicio']);
                                if ($timestamp) {
                                    $departure_date = new WC_DateTime('@' . $timestamp);
                                    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string() ?: 'UTC');
                                    $departure_date->setTimezone($timezone);
                                }
                            }
                        }
                        $departure_text = $departure_date ? wc_format_datetime($departure_date) : (string) $viaje['inicio'];
                        ?>
                        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#0f172a;">
                            <strong><?php esc_html_e('Fecha de salida del viaje:', 'manaslu'); ?></strong>
                            <?php echo esc_html($departure_text); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($viaje['extras'])) : ?>
                        <div style="margin:16px 0;">
                            <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#0f172a;">
                                <?php esc_html_e('Extras añadidos', 'manaslu'); ?>
                            </p>
                            <ul style="margin:0;padding-left:18px;color:#0f172a;font-size:14px;line-height:1.6;">
                                <?php foreach ($viaje['extras'] as $extra) : ?>
                                    <?php
                                    $extra_label = isset($extra['title']) ? (string) $extra['title'] : '';
                                    $qty         = isset($extra['qty']) ? (int) $extra['qty'] : 0;
                                    $included    = isset($extra['included']) ? (int) $extra['included'] : 0;
                                    $variant     = isset($extra['variant']) ? (string) $extra['variant'] : '';
                                    $details     = [];
                                    if ($qty > 0) {
                                        $details[] = sprintf(_n('%d unidad', '%d unidades', $qty, 'manaslu'), $qty);
                                    }
                                    if ($included > 0) {
                                        $details[] = sprintf(__('Incluidas: %d', 'manaslu'), $included);
                                    }
                                    if ($variant !== '') {
                                        $details[] = $variant;
                                    }
                                    ?>
                                    <li>
                                        <?php echo esc_html(trim($extra_label)); ?>
                                        <?php if (!empty($details)) : ?>
                                            <span style="color:#475569;">(<?php echo esc_html(implode(' · ', $details)); ?>)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else : ?>
                        <p style="margin:12px 0;font-size:14px;color:#475569;">
                            <?php esc_html_e('No se añadieron extras adicionales a este viaje.', 'manaslu'); ?>
                        </p>
                    <?php endif; ?>

                    <div style="margin:16px 0;">
                        <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#0f172a;">
                            <?php esc_html_e('Seguro que ha elegido', 'manaslu'); ?>
                        </p>
                        <?php if (!empty($viaje['seguros'])) : ?>
                            <ul style="margin:0;padding-left:18px;color:#0f172a;font-size:14px;line-height:1.6;">
                                <?php foreach ($viaje['seguros'] as $seguro) : ?>
                                    <?php $seguro_label = isset($seguro['title']) ? (string) $seguro['title'] : ''; ?>
                                    <?php if ($seguro_label === '') { continue; } ?>
                                    <li><?php echo esc_html($seguro_label); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p style="margin:0;font-size:14px;color:#475569;">
                                <?php esc_html_e('No seleccionaste un seguro adicional para este viaje.', 'manaslu'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($viaje['discounts'])) : ?>
                        <div style="margin:16px 0;">
                            <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#0f172a;">
                                <?php esc_html_e('Descuentos aplicados', 'manaslu'); ?>
                            </p>
                            <ul style="margin:0;padding-left:18px;color:#0f172a;font-size:14px;line-height:1.6;">
                                <?php foreach ($viaje['discounts'] as $discount_line) : ?>
                                    <li><?php echo wp_kses_post($discount_line); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div style="margin:16px 0;">
                        <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#0f172a;">
                            <?php esc_html_e('¿Quieres que busquemos los vuelos por ti?', 'manaslu'); ?>
                        </p>
                        <p style="margin:0;font-size:14px;color:#0f172a;">
                            <?php echo esc_html($flight_preference); ?>
                        </p>
                    </div>

                    <div style="margin:16px 0;">
                        <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#0f172a;">
                            <?php esc_html_e('Listado de pasajeros', 'manaslu'); ?>
                        </p>
                        <?php if (!empty($viaje['pasajeros'])) : ?>
                            <ul style="margin:0;padding-left:18px;color:#0f172a;font-size:14px;line-height:1.6;">
                                <?php foreach ($viaje['pasajeros'] as $pasajero) : ?>
                                    <li><?php echo esc_html($pasajero); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p style="margin:0;font-size:14px;color:#475569;">
                                <?php esc_html_e('No se registraron pasajeros adicionales en el pedido.', 'manaslu'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($viaje['incluye'])) : ?>
                        <div style="margin:20px 0;">
                            <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:.04em;">
                                <?php esc_html_e('El precio incluye', 'manaslu'); ?>
                            </p>
                            <div style="font-size:14px;line-height:1.6;color:#0f172a;">
                                <?php echo wp_kses_post(wpautop($viaje['incluye'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($viaje['no_incluye'])) : ?>
                        <div style="margin:20px 0;">
                            <p style="margin:0 0 6px;font-size:14px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:.04em;">
                                <?php esc_html_e('El precio no incluye', 'manaslu'); ?>
                            </p>
                            <div style="font-size:14px;line-height:1.6;color:#0f172a;">
                                <?php echo wp_kses_post(wpautop($viaje['no_incluye'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    <?php endforeach; ?>
<?php endif; ?>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:24px;border:1px solid #e2e8f0;border-radius:16px;background:#ffffff;">
    <tr>
        <td style="padding:24px 28px;font-family:'Host Grotesk',-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a;">
            <h2 style="margin:0 0 12px;font-size:18px;line-height:1.3;font-weight:700;color:#0f172a;">
                <?php esc_html_e('Forma de pago', 'manaslu'); ?>
            </h2>
            <?php if ($payment_method) : ?>
                <p style="margin:0 0 8px;font-size:14px;color:#0f172a;">
                    <strong><?php esc_html_e('Método:', 'manaslu'); ?></strong>
                    <?php echo esc_html($payment_method); ?>
                </p>
            <?php endif; ?>
            <p style="margin:0 0 8px;font-size:14px;color:#0f172a;">
                <strong><?php esc_html_e('Precio total del viaje:', 'manaslu'); ?></strong>
                <?php echo wp_kses_post(wc_price($order->get_total())); ?>
            </p>
            <p style="margin:0;font-size:14px;color:#0f172a;">
                <strong><?php esc_html_e('Depósito recibido:', 'manaslu'); ?></strong>
                <?php echo wp_kses_post(wc_price($deposit_amount)); ?>
            </p>
        </td>
    </tr>
</table>

<?php
/*
 * Se mantiene la tabla original de WooCommerce para mostrar los detalles del viaje.
 */

do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

if (!empty($additional_content)) {
    echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td class="email-additional-content">' : '';
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
    echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

/*
 * Pie del email.
 */
do_action('woocommerce_email_footer', $email);
