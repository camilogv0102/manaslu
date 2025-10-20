<?php
function show_form_home() {
	$form = '<div>';
    	$form .= '<form class="form_home">';
        $form .= '<div class="input_div">';
        $form .= '<label>Dónde</label>';
        $form .= '<select><option>Elige tu destino</option><option>opción 1</option></select>';
        $form .='</div>';
        $form .= '<div class="input_div">';
        $form .= '<label>Cuándo</label>';
        $form .= '<select><option>Añade las fechas</option><option>opción 1</option></select>';
        $form .= '</div>';
        $form .= '<div class="input_div">';
        $form .= '<label>Qué</label>';
        $form .= '<select><option>Elige tipo de viaje</option><option>opción 1</option></select>';
        $form .= '</div>';
        $form .= '<div class="input_div">';
        $form .= '<label>Cómo</label>';
        $form .= '<select><option>Estilo de viaje</option><option>opción 1</option></select>';
        $form .= '</div>';
        $form .= '<div class="input_div">';
        $form .= '<label>Nivel</label>';
        $form .= '<select><option>Elige tu nivel</option><option>opción 1</option></select>';
        $form .= '</div>';
        $form .= '<div class="input_div sub_">';
        $form .= '<input type="submit" name="" value="">';
        $form .= '</div>';
        $form .= '</form>';
    $form .= '</div>';
	
	return $form;
}

add_shortcode('form_home', 'show_form_home');

add_filter( 'wpcf7_autop_or_not', '__return_false' );