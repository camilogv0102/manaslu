<?php
// Shortcode: [acf_itinerario]  |  [acf_itinerario id="123"]  |  [acf_itinerario open="1"]
function render_itinerario_acf( $atts ) {
  $a = shortcode_atts([
    'id'    => get_the_ID(), // producto actual por defecto
    'open'  => '1',          // "1" abre el primero, "0" todos cerrados
    'class' => '',
  ], $atts, 'acf_itinerario');

  if ( empty($a['id']) ) return '';

  ob_start();

  if ( have_rows('itinerario', $a['id']) ) {
    $open_first = ($a['open'] === '1');
    echo '<div class="itinerario-wrap '.esc_attr($a['class']).'">';

    $i = 0;
    while ( have_rows('itinerario', $a['id']) ): the_row();
      $i++;

      $titulo       = trim( (string) get_sub_field('titulo') );
      $descripcion  = (string) get_sub_field('descripcion'); // WYSIWYG (HTML permitido)
      $imagen_field = get_sub_field('imagen');               // array (por return_format => array)

      // Construir la imagen si existe
      $imagen_html = '';
      if (is_array($imagen_field) && !empty($imagen_field['ID'])) {
        // cambia 'large' por el tamaño que prefieras
        $imagen_html = wp_get_attachment_image( $imagen_field['ID'], 'large', false, [
          'class' => 'it-img',
          'loading' => 'lazy',
          'alt'   => esc_attr($titulo ?: 'Día '.$i),
        ]);
      }

      // Si no hay nada, saltamos
      if ($titulo === '' && $descripcion === '' && $imagen_html === '') continue;

      echo '<details class="it-item"'. ( ($open_first && $i===1) ? ' open' : '' ) .'>';

        // SUMMARY: "DÍA X: TÍTULO"
        $titulo_final = 'DÍA ' . $i . ': ' . ($titulo !== '' ? $titulo : 'Sin título');
        echo '<summary class="it-title">'. esc_html($titulo_final) .'</summary>';

        // BODY: imagen izq + texto der
        echo '<div class="it-body">';
          if ($imagen_html !== '') {
            echo '<div class="it-media">'.$imagen_html.'</div>';
          }
          echo '<div class="it-text">'. wp_kses_post($descripcion) .'</div>';
        echo '</div>';

      echo '</details>';
    endwhile;

    echo '</div>';
  }

  return ob_get_clean();
}
add_shortcode('acf_itinerario', 'render_itinerario_acf');
