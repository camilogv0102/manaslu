<?php
/**
 * Plugin Name: Manaslu - ID List (MU)
 * Description: Muestra una página "ID LIST" con todos los campos personalizados del tipo de producto Viajes y sus IDs.
 * Version: 1.0.0
 * Author: Manaslu Adventures
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Añade la página "ID LIST" al menú de productos.
 */
function mv_id_list_add_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=product',
        __('ID LIST - Campos Personalizados', 'manaslu'),
        __('ID LIST', 'manaslu'),
        'manage_woocommerce',
        'mv-id-list',
        'mv_id_list_admin_page'
    );
}
add_action('admin_menu', 'mv_id_list_add_admin_menu');

/**
 * Renderiza la página de administración ID LIST.
 */
function mv_id_list_admin_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos suficientes.', 'manaslu'));
    }

    // Obtener todos los campos personalizados relacionados con viajes
    $all_custom_fields = mv_id_list_get_all_viaje_fields();
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('ID LIST - Campos Personalizados de Viajes', 'manaslu'); ?></h1>
        <p class="description">
            <?php esc_html_e('Lista completa de todos los campos personalizados creados para el tipo de producto Viajes y sus IDs únicos.', 'manaslu'); ?>
        </p>
        
        <div class="card" style="max-width: none;">
            <h2><?php esc_html_e('Campos Personalizados del Sistema Viajes', 'manaslu'); ?></h2>
            
            <?php if (!empty($all_custom_fields)): ?>
                <table class="widefat fixed striped mv-id-list-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;"><?php esc_html_e('#', 'manaslu'); ?></th>
                            <th style="width: 40%;"><?php esc_html_e('Campo (Meta Key)', 'manaslu'); ?></th>
                            <th style="width: 25%;"><?php esc_html_e('ID de Campo', 'manaslu'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Tipo', 'manaslu'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Plugin/Origen', 'manaslu'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_custom_fields as $index => $field): ?>
                            <tr>
                                <td><?php echo esc_html($index + 1); ?></td>
                                <td>
                                    <strong><?php echo esc_html($field['key']); ?></strong>
                                    <?php if (!empty($field['description'])): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($field['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?php echo esc_html($field['meta_id']); ?></code>
                                </td>
                                <td>
                                    <span class="dashicons dashicons-<?php echo esc_attr($field['icon']); ?>"></span>
                                    <?php echo esc_html($field['type']); ?>
                                </td>
                                <td>
                                    <span style="background: <?php echo esc_attr($field['color']); ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <?php echo esc_html($field['plugin']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                    <h4><?php esc_html_e('Resumen', 'manaslu'); ?></h4>
                    <ul>
                        <li><strong><?php esc_html_e('Total de campos personalizados:', 'manaslu'); ?></strong> <?php echo count($all_custom_fields); ?></li>
                        <li><strong><?php esc_html_e('Campos únicos:', 'manaslu'); ?></strong> <?php echo count(array_unique(array_column($all_custom_fields, 'key'))); ?></li>
                        <li><strong><?php esc_html_e('Última actualización:', 'manaslu'); ?></strong> <?php echo current_time('Y-m-d H:i:s'); ?></li>
                    </ul>
                </div>
            <?php else: ?>
                <p><?php esc_html_e('No se encontraron campos personalizados para el tipo de producto Viajes.', 'manaslu'); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h3><?php esc_html_e('Información Técnica', 'manaslu'); ?></h3>
            <p><?php esc_html_e('Esta lista muestra todos los campos personalizados (meta_keys) que han sido creados específicamente para el sistema de viajes. Los campos ACF definidos en acf-bulk-fields.php han sido excluidos intencionalmente.', 'manaslu'); ?></p>
            
            <h4><?php esc_html_e('Categorías de Campos:', 'manaslu'); ?></h4>
            <ul>
                <li><strong>Fechas del Viaje:</strong> Campos relacionados con fechas, precios y cupos</li>
                <li><strong>Extras:</strong> Campos para gestionar extras y sus precios</li>
                <li><strong>Cupones:</strong> Campos para cupones específicos de viajes</li>
                <li><strong>Descuentos:</strong> Campos para descuentos por número de personas</li>
                <li><strong>Tooltips:</strong> Campos para explicaciones contextuales</li>
                <li><strong>WooCommerce:</strong> Campos estándar del sistema</li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Obtiene todos los campos personalizados relacionados con viajes del sistema.
 *
 * @return array
 */
function mv_id_list_get_all_viaje_fields() {
    $color_viajes   = '#0073aa';
    $color_extras   = '#00a32a';
    $color_cupones  = '#d63638';
    $color_tooltips = '#8b5cf6';
    $color_core     = '#555d66';

    $viaje_fields = [
        [
            'meta_key'    => null,
            'display_key' => 'ID de producto (post->ID)',
            'description' => 'Identificador único del producto viaje (se usa como ID base en la API).',
            'type'        => 'Integer',
            'icon'        => 'admin-post',
            'plugin'      => 'WooCommerce',
            'color'       => $color_core,
        ],

        // Campos de fechas del viaje (manaslu-viajes-tipo-fechas-ajax-cupos.php)
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas',
            'description' => 'Repetidor principal "Añadir fecha" con precios, cupos y estado por salida.',
            'type'        => 'Array',
            'icon'        => 'calendar-alt',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][inicio]',
            'description' => 'Fecha de inicio (formato Y-m-d) de cada salida.',
            'type'        => 'String',
            'icon'        => 'calendar-alt',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][fin]',
            'description' => 'Fecha de fin (formato Y-m-d) de cada salida.',
            'type'        => 'String',
            'icon'        => 'calendar-alt',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][precio_normal]',
            'description' => 'Precio estándar por persona para la salida.',
            'type'        => 'Decimal',
            'icon'        => 'money-alt',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][precio_rebajado]',
            'description' => 'Precio rebajado aplicado si existe oferta.',
            'type'        => 'Decimal',
            'icon'        => 'money',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][concepto]',
            'description' => 'Concepto o etiqueta del descuento mostrado en ficha.',
            'type'        => 'String',
            'icon'        => 'editor-justify',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][tipo_grupo]',
            'description' => 'Clasificación del grupo (ej: Regular, Privado) usada en el front.',
            'type'        => 'String',
            'icon'        => 'groups',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][cupo_total]',
            'description' => 'Cupo total disponible para la salida.',
            'type'        => 'Integer',
            'icon'        => 'chart-bar',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas',
            'display_key' => '_viaje_fechas[][estado]',
            'description' => 'Estado de disponibilidad (disponible / completo / bajo demanda).',
            'type'        => 'String',
            'icon'        => 'flag',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_ventas_map',
            'display_key' => '_viaje_ventas_map',
            'description' => 'Mapa interno de ventas por fecha para control de stock.',
            'type'        => 'Array',
            'icon'        => 'chart-line',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas_extras',
            'display_key' => '_viaje_fechas_extras',
            'description' => 'Asignación de precios de extras por fecha (incluye personas, noches, etc.).',
            'type'        => 'Array',
            'icon'        => 'money-alt',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas_extras',
            'display_key' => '_viaje_fechas_extras[][extra_id][regular]',
            'description' => 'Precio regular por fecha para cada extra (Habitación individual, Noche antes/después, etc.).',
            'type'        => 'Decimal',
            'icon'        => 'money-alt',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas_extras',
            'display_key' => '_viaje_fechas_extras[][extra_id][sale]',
            'description' => 'Precio rebajado por fecha para cada extra.',
            'type'        => 'Decimal',
            'icon'        => 'money',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],
        [
            'meta_key'    => '_viaje_fechas_extras',
            'display_key' => '_viaje_fechas_extras[][extra_id][pct]',
            'description' => 'Campo de porcentaje usado para descuentos de niños en extras de personas.',
            'type'        => 'Decimal',
            'icon'        => 'percentage',
            'plugin'      => 'Viajes',
            'color'       => $color_viajes,
        ],

        // Campos de extras (manaslu-extras-para-viajes.php)
        [
            'meta_key'    => 'extras_asignados',
            'display_key' => 'extras_asignados[]',
            'description' => 'IDs de extras asociados al producto viaje.',
            'type'        => 'Array',
            'icon'        => 'plus-alt',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_extra_prices',
            'display_key' => 'mv_extra_prices',
            'description' => 'Mapa completo de precios de extras por producto.',
            'type'        => 'Array',
            'icon'        => 'money-alt',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_extra_prices',
            'display_key' => 'mv_extra_prices[extra_id][regular]',
            'description' => 'Precio regular por producto para cada extra (ej. Habitación individual).',
            'type'        => 'Decimal',
            'icon'        => 'money-alt',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_extra_prices',
            'display_key' => 'mv_extra_prices[extra_id][sale]',
            'description' => 'Precio rebajado por producto para cada extra.',
            'type'        => 'Decimal',
            'icon'        => 'money',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_extra_prices',
            'display_key' => 'mv_extra_prices[extra_id][seguro][sin]',
            'description' => 'Precio del Seguro (sin cancelación) asignado al producto.',
            'type'        => 'Decimal',
            'icon'        => 'shield',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_extra_prices',
            'display_key' => 'mv_extra_prices[extra_id][seguro_links][sin]',
            'description' => 'URL PDF Seguro (sin cancelación) mostrado en el botón.',
            'type'        => 'String',
            'icon'        => 'admin-links',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_extra_prices',
            'display_key' => 'mv_extra_prices[extra_id][seguro][con]',
            'description' => 'Precio del Seguro (con cancelación) asignado al producto.',
            'type'        => 'Decimal',
            'icon'        => 'shield-alt',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_extra_prices',
            'display_key' => 'mv_extra_prices[extra_id][seguro_links][con]',
            'description' => 'URL PDF Seguro (con cancelación) mostrado en el botón.',
            'type'        => 'String',
            'icon'        => 'admin-links',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_included_extras',
            'display_key' => 'mv_included_extras[]',
            'description' => 'Extras incluidos por defecto al cargar el viaje.',
            'type'        => 'Array',
            'icon'        => 'checkmark',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_sc_extra_cat_order',
            'display_key' => 'mv_sc_extra_cat_order',
            'description' => 'Orden personalizado de categorías de extras para shortcodes.',
            'type'        => 'Array',
            'icon'        => 'sort',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],
        [
            'meta_key'    => 'mv_sc_extra_cat_labels',
            'display_key' => 'mv_sc_extra_cat_labels',
            'description' => 'Etiquetas personalizadas para categorías de extras.',
            'type'        => 'Array',
            'icon'        => 'tag',
            'plugin'      => 'Extras',
            'color'       => $color_extras,
        ],

        // Campos de cupones (manaslu-cupones-por-viaje.php)
        [
            'meta_key'    => '_mv_allowed_coupon',
            'display_key' => '_mv_allowed_coupon',
            'description' => 'ID del cupón específico permitido para este viaje.',
            'type'        => 'Integer',
            'icon'        => 'tickets-alt',
            'plugin'      => 'Cupones',
            'color'       => $color_cupones,
        ],

        // Campos de descuentos (manaslu-descuentos-por-personas.php)
        [
            'meta_key'    => '_mv_descuentos_personas',
            'display_key' => '_mv_descuentos_personas',
            'description' => 'Rangos de descuento por número total de personas.',
            'type'        => 'Array',
            'icon'        => 'percentage',
            'plugin'      => 'Descuentos',
            'color'       => $color_cupones,
        ],

        // Campos de tooltips (tooltips.php)
        [
            'meta_key'    => '_mv_tooltip_fecha_explicacion',
            'display_key' => '_mv_tooltip_fecha_explicacion',
            'description' => 'Tooltip de apoyo para las fechas del viaje.',
            'type'        => 'String',
            'icon'        => 'info',
            'plugin'      => 'Tooltips',
            'color'       => $color_tooltips,
        ],
        [
            'meta_key'    => '_mv_tooltip_nivel_explicacion',
            'display_key' => '_mv_tooltip_nivel_explicacion',
            'description' => 'Tooltip explicativo del nivel del viaje.',
            'type'        => 'String',
            'icon'        => 'info',
            'plugin'      => 'Tooltips',
            'color'       => $color_tooltips,
        ],
        [
            'meta_key'    => '_mv_tooltip_regimen_explicacion',
            'display_key' => '_mv_tooltip_regimen_explicacion',
            'description' => 'Tooltip explicativo del régimen alimenticio.',
            'type'        => 'String',
            'icon'        => 'info',
            'plugin'      => 'Tooltips',
            'color'       => $color_tooltips,
        ],
        [
            'meta_key'    => '_mv_tooltip_transporte_explicacion',
            'display_key' => '_mv_tooltip_transporte_explicacion',
            'description' => 'Tooltip explicativo del transporte incluido.',
            'type'        => 'String',
            'icon'        => 'info',
            'plugin'      => 'Tooltips',
            'color'       => $color_tooltips,
        ],
    ];

    // Campos dinámicos por cada extra publicado (IDs concretos para la API)
    if (post_type_exists('extra')) {
        $extras = get_posts([
            'post_type'      => 'extra',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (!empty($extras)) {
            foreach ($extras as $extra_post) {
                $extra_id    = (int) $extra_post->ID;
                $extra_title = wp_strip_all_tags($extra_post->post_title);
                $label_base  = sprintf('Extra #%d (%s)', $extra_id, $extra_title !== '' ? $extra_title : __('Sin título', 'manaslu'));

                $viaje_fields[] = [
                    'meta_key'    => 'mv_extra_prices',
                    'display_key' => sprintf('mv_extra_prices[%d][regular]', $extra_id),
                    'description' => sprintf('Precio regular por producto para %s.', $label_base),
                    'type'        => 'Decimal',
                    'icon'        => 'money-alt',
                    'plugin'      => 'Extras',
                    'color'       => $color_extras,
                ];

                $viaje_fields[] = [
                    'meta_key'    => 'mv_extra_prices',
                    'display_key' => sprintf('mv_extra_prices[%d][sale]', $extra_id),
                    'description' => sprintf('Precio rebajado por producto para %s.', $label_base),
                    'type'        => 'Decimal',
                    'icon'        => 'money',
                    'plugin'      => 'Extras',
                    'color'       => $color_extras,
                ];

                $is_seguro = taxonomy_exists('extra_category') && has_term('seguro-de-viaje', 'extra_category', $extra_id);
                if ($is_seguro) {
                    $viaje_fields[] = [
                        'meta_key'    => 'mv_extra_prices',
                        'display_key' => sprintf('mv_extra_prices[%d][seguro][sin]', $extra_id),
                        'description' => sprintf('Precio "sin cancelación" del Seguro %s.', $label_base),
                        'type'        => 'Decimal',
                        'icon'        => 'shield',
                        'plugin'      => 'Extras',
                        'color'       => $color_extras,
                    ];
                    $viaje_fields[] = [
                        'meta_key'    => 'mv_extra_prices',
                        'display_key' => sprintf('mv_extra_prices[%d][seguro_links][sin]', $extra_id),
                        'description' => sprintf('Enlace PDF "sin cancelación" del Seguro %s.', $label_base),
                        'type'        => 'String',
                        'icon'        => 'admin-links',
                        'plugin'      => 'Extras',
                        'color'       => $color_extras,
                    ];
                    $viaje_fields[] = [
                        'meta_key'    => 'mv_extra_prices',
                        'display_key' => sprintf('mv_extra_prices[%d][seguro][con]', $extra_id),
                        'description' => sprintf('Precio "con cancelación" del Seguro %s.', $label_base),
                        'type'        => 'Decimal',
                        'icon'        => 'shield-alt',
                        'plugin'      => 'Extras',
                        'color'       => $color_extras,
                    ];
                    $viaje_fields[] = [
                        'meta_key'    => 'mv_extra_prices',
                        'display_key' => sprintf('mv_extra_prices[%d][seguro_links][con]', $extra_id),
                        'description' => sprintf('Enlace PDF "con cancelación" del Seguro %s.', $label_base),
                        'type'        => 'String',
                        'icon'        => 'admin-links',
                        'plugin'      => 'Extras',
                        'color'       => $color_extras,
                    ];
                }
            }
        }
    }

    // Obtener los meta_ids únicos para cada campo
    global $wpdb;
    $fields_with_ids = [];

    $meta_id_cache = [];

    foreach ($viaje_fields as $field_info) {
        $lookup_key = isset($field_info['meta_key']) ? $field_info['meta_key'] : null;

        if ($lookup_key) {
            if (!array_key_exists($lookup_key, $meta_id_cache)) {
                $prepared = $wpdb->prepare(
                    "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
                    $lookup_key
                );
                $found_id = $wpdb->get_var($prepared);
                $meta_id_cache[$lookup_key] = $found_id ? $found_id : 'N/A';
            }
            $meta_id = $meta_id_cache[$lookup_key];
        } else {
            $meta_id = 'N/A';
        }

        $fields_with_ids[] = [
            'key' => isset($field_info['display_key']) ? $field_info['display_key'] : (string) $lookup_key,
            'meta_id' => $meta_id ? $meta_id : 'N/A',
            'description' => isset($field_info['description']) ? $field_info['description'] : '',
            'type' => isset($field_info['type']) ? $field_info['type'] : '',
            'icon' => isset($field_info['icon']) ? $field_info['icon'] : 'info',
            'plugin' => isset($field_info['plugin']) ? $field_info['plugin'] : '',
            'color' => isset($field_info['color']) ? $field_info['color'] : $color_core,
        ];
    }
    
    return $fields_with_ids;
}

/**
 * Añade estilos CSS para la página ID LIST.
 */
function mv_id_list_admin_styles() {
    ?>
    <style>
        .mv-id-list-table th,
        .mv-id-list-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #ddd;
        }
        .mv-id-list-table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        .mv-id-list-table code {
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
    <?php
}
add_action('admin_head', 'mv_id_list_admin_styles');
