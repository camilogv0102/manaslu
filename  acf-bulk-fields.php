<?php
/**
 * Plugin Name: ACF Bulk Fields – Manaslu (Hero)
 * Description: Crea en masa los campos ACF del grupo "Hero".
 */

add_action('acf/init', function () {
  if (!function_exists('acf_add_local_field_group')) return;

  // ======= HERO (labels exactos) =======
  $lista = [
    ['slug' => 'grupo',           'type' => 'text',   'label' => 'Grupo'],
    ['slug' => 'minimo_grupo',    'type' => 'number', 'label' => 'Mínimo Grupo'],
    ['slug' => 'maximo_grupo',    'type' => 'number', 'label' => 'Máximo Grupo'],
    ['slug' => 'tipo_de_viaje',   'type' => 'text',   'label' => 'Tipo de viaje'],
    ['slug' => 'estilo_viaje',    'type' => 'text',   'label' => 'Estilo Viaje'],
    ['slug' => 'nivel',           'type' => 'text',   'label' => 'Nivel'],
    ['slug' => 'duracion',        'type' => 'text',   'label' => 'Duración'],
  ];

  // Construye los fields con labels personalizados
  $fields = [];
  foreach ($lista as $item) {
    $fields[] = [
      'key'   => 'field_' . substr(md5('hero_'.$item['slug']), 0, 12),
      'label' => $item['label'],
      'name'  => $item['slug'],
      'type'  => $item['type'],
    ];
  }

  // Grupo de campos: "Hero"
  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_hero_manaslu'), 0, 12),
    'title'  => 'Hero',
    'fields' => $fields,
    'location' => [[[
      // ======= EDITABLE: destino =======
      // Cambia 'post' por tu post type (ej.: 'page', 'tour', 'viaje', etc.)
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
    'show_in_rest' => 0,
  ]);

  // ===== Detalles del viaje (solo los campos solicitados) =====
  $lista_detalles = [
    ['slug' => 'pais',                 'type' => 'text', 'label' => 'País'],
    ['slug' => 'cordillera',           'type' => 'text', 'label' => 'Cordillera'],
    ['slug' => 'regimen_alojamiento',  'type' => 'text', 'label' => 'Régimen Alojamiento'],
    ['slug' => 'forma_de_viajar',      'type' => 'text', 'label' => 'Forma de Viajar'],
    ['slug' => 'guia',                 'type' => 'text', 'label' => 'Guía'],
    ['slug' => 'transporte_interior',  'type' => 'text', 'label' => 'Transporte (interior)'],
  ];

  $fields_detalles = [];
  foreach ($lista_detalles as $item) {
    $fields_detalles[] = [
      'key'   => 'field_' . substr(md5('detalles_'.$item['slug']), 0, 12),
      'label' => $item['label'],
      'name'  => $item['slug'],
      'type'  => $item['type'],
    ];
  }

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_detalles_viaje_manaslu'), 0, 12),
    'title'  => 'Detalles del viaje',
    'fields' => $fields_detalles,
    'location' => [[[
      // Usa el mismo destino que el grupo "Hero" por ahora
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
    'show_in_rest' => 0,
  ]);

  // ===== Grupo de campos: "Mapa del viaje" (un solo campo) =====
  $fields_mapa = [[
    'key'               => 'field_' . substr(md5('mapa_embed'), 0, 12),
    'label'             => 'Mapa (embed de Google Maps)',
    'name'              => 'mapa_embed',
    'type'              => 'textarea',   // pegamos el <iframe> aquí
    'instructions'      => 'Pega el <iframe> de Google Maps (Compartir → Insertar un mapa).',
    'rows'              => 4,
  ]];

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_mapa_viaje_manaslu'), 0, 12),
    'title'  => 'Mapa del viaje',
    'fields' => $fields_mapa,
    'location' => [[[
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
  ]);

  // ===== Grupo de campos: "Itinerario" (repeater de 10 días) =====
  $fields_itinerario = [[
    'key'          => 'field_' . substr(md5('itinerario_repeater'), 0, 12),
    'label'        => 'Días del itinerario',
    'name'         => 'itinerario',
    'type'         => 'repeater',
    'min'          => 0,
    'max'          => 30,
    'layout'       => 'row',
    'button_label' => 'Agregar día',
    'sub_fields'   => [
      [
        'key'   => 'field_' . substr(md5('it_dia_titulo'), 0, 12),
        'label' => 'Título del día',
        'name'  => 'titulo',
        'type'  => 'text',
      ],
      [
        'key'           => 'field_' . substr(md5('it_dia_imagen'), 0, 12),
        'label'         => 'Imagen',
        'name'          => 'imagen',
        'type'          => 'image',
        'return_format' => 'array',
        'preview_size'  => 'medium',
        'library'       => 'all',
      ],
      [
        'key'   => 'field_' . substr(md5('it_dia_descripcion'), 0, 12),
        'label' => 'Descripción',
        'name'  => 'descripcion',
        'type'  => 'wysiwyg',
        'tabs'  => 'all',
        'toolbar' => 'full',
        'media_upload' => 0,
      ],
    ],
  ]];

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_itinerario_manaslu'), 0, 12),
    'title'  => 'Itinerario',
    'fields' => $fields_itinerario,
    'location' => [[[
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
  ]);

  // ===== Grupo de campos: "Precios y Servicios" =====
  $lista_precio_serv = [
    'incluido'                          => 'wysiwyg',
    'no_incluido'                       => 'wysiwyg',
    'actividades_opcionales_no_incluidas'=> 'wysiwyg',
    'transfer'                          => 'wysiwyg',
    'alojamiento_y_comidas'             => 'wysiwyg',
    'staff_durante_el_viaje'            => 'wysiwyg',
  ];

  $fields_precio_serv = [];
  foreach ($lista_precio_serv as $slug => $tipo) {
    $fields_precio_serv[] = [
      'key'          => 'field_' . substr(md5('precio_serv_'.$slug), 0, 12),
      'label'        => ucwords(str_replace('_', ' ', $slug)),
      'name'         => $slug,
      'type'         => $tipo,
      'tabs'         => 'all',
      'toolbar'      => 'basic',
      'media_upload' => 0,
      'instructions' => 'Contenido del acordeón correspondiente.',
    ];
  }

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_precios_servicios_manaslu'), 0, 12),
    'title'  => 'Precios y Servicios',
    'fields' => $fields_precio_serv,
    'location' => [[[
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
  ]);

  // ===== Grupo de campos: "Info final" =====
  $fields_info_final = [
    [
      'key'          => 'field_' . substr(md5('info_final_material_recomendado'), 0, 12),
      'label'        => 'Material recomendado',
      'name'         => 'material_recomendado',
      'type'         => 'wysiwyg',
      'tabs'         => 'all',
      'toolbar'      => 'basic',
      'media_upload' => 0,
      'instructions' => 'Texto con posible enlace “Leer más”.',
    ],
    [
      'key'          => 'field_' . substr(md5('info_final_informacion_adicional'), 0, 12),
      'label'        => 'Información adicional',
      'name'         => 'informacion_adicional',
      'type'         => 'wysiwyg',
      'tabs'         => 'all',
      'toolbar'      => 'basic',
      'media_upload' => 0,
      'instructions' => 'Texto con posible enlace “Leer más”.',
    ],
  ];

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_info_final_manaslu'), 0, 12),
    'title'  => 'Info final',
    'fields' => $fields_info_final,
    'location' => [[[
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
  ]);

  // ===== Grupo de campos: "¿Qué lo hace diferente?" =====
  $fields_diferente = [
    [
      'key'   => 'field_' . substr(md5('que_lo_hace_diferente_parrafo'), 0, 12),
      'label' => 'Párrafo',
      'name'  => 'parrafo',
      'type'  => 'wysiwyg',
      'tabs'  => 'all',
      'toolbar' => 'basic',
      'media_upload' => 0,
    ],
    [
      'key'           => 'field_' . substr(md5('que_lo_hace_diferente_imagen'), 0, 12),
      'label'         => 'Imagen',
      'name'          => 'imagen',
      'type'          => 'image',
      'return_format' => 'array',
      'preview_size'  => 'medium',
      'library'       => 'all',
    ],
  ];

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_que_lo_hace_diferente_manaslu'), 0, 12),
    'title'  => '¿Qué lo hace diferente?',
    'fields' => $fields_diferente,
    'location' => [[[
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
  ]);

  // ===== Grupo de campos: "Galería" =====
  $fields_galeria = [[
    'key'           => 'field_' . substr(md5('galeria_imagenes'), 0, 12),
    'label'         => 'Galería de imágenes',
    'name'          => 'galeria_imagenes',
    'type'          => 'gallery',
    'instructions'  => 'Sube hasta 12 imágenes.',
    'return_format' => 'array',
    'preview_size'  => 'medium',
    'insert'        => 'append',
    'library'       => 'all',
    'min'           => 0,
    'max'           => 10,
  ]];

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_galeria_manaslu'), 0, 12),
    'title'  => 'Galería',
    'fields' => $fields_galeria,
    'location' => [[[
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
  ]);

  // ===== Grupo de campos: "FAQs" (8 fijos) =====
  $fields_faqs = [[
    'key'          => 'field_' . substr(md5('faqs_repeater'), 0, 12),
    'label'        => 'FAQs',
    'name'         => 'faqs',
    'type'         => 'repeater',
    'min'          => 0,
    'max'          => 15,
    'layout'       => 'row',
    'button_label' => 'Agregar FAQ',
    'sub_fields'   => [
      [
        'key'   => 'field_' . substr(md5('faq_titulo'), 0, 12),
        'label' => 'Título',
        'name'  => 'titulo',
        'type'  => 'text',
      ],
      [
        'key'          => 'field_' . substr(md5('faq_parrafo'), 0, 12),
        'label'        => 'Párrafo',
        'name'         => 'parrafo',
        'type'         => 'wysiwyg',
        'tabs'         => 'all',
        'toolbar'      => 'basic',
        'media_upload' => 0,
      ],
    ],
  ]];

  acf_add_local_field_group([
    'key'    => 'group_' . substr(md5('grupo_faqs_manaslu'), 0, 12),
    'title'  => 'FAQs',
    'fields' => $fields_faqs,
    'location' => [[[
      'param' => 'post_type', 'operator' => '==', 'value' => 'product'
    ]]],
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'active' => true,
  ]);
});