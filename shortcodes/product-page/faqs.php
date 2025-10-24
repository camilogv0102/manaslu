<?php
// Shortcode: [acf_faqs]  ó  [acf_faqs id="123"]  ó  [acf_faqs accordion="0"]
function render_faqs_acf( $atts ) {
  $a = shortcode_atts([
    'id'        => get_the_ID(),
    'accordion' => '1',
    'class'     => '',
  ], $atts, 'acf_faqs');

  if ( empty($a['id']) ) return '';

  ob_start();

  if ( have_rows('faqs', $a['id']) ) {
    $is_accordion = ($a['accordion'] === '1');
    echo '<div class="faqs-wrap '.esc_attr($a['class']).'">';
    $i = 0;
    while ( have_rows('faqs', $a['id']) ) : the_row();
      $i++;
      $titulo  = trim( (string) get_sub_field('titulo') );
      $parrafo = (string) get_sub_field('parrafo');
      if ($titulo === '' && $parrafo === '') continue;

      if ($is_accordion) {
        echo '<details class="faq-item"'.($i===1 ? ' open' : '').'>';
        echo   '<summary class="faq-title">'.esc_html($titulo ?: 'Pregunta '.$i).'</summary>';
        echo   '<div class="faq-parrafo">'.wp_kses_post($parrafo).'</div>';
        echo '</details>';
      } else {
        echo '<div class="faq-item">';
        if ($titulo !== '')   echo '<h3 class="faq-title">'.esc_html($titulo).'</h3>';
        if ($parrafo !== '')  echo '<div class="faq-parrafo">'.wp_kses_post($parrafo).'</div>';
        echo '</div>';
      }
    endwhile;
    echo '</div>';
  }

  return ob_get_clean();
}
add_shortcode('acf_faqs', 'render_faqs_acf');
