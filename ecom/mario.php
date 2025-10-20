<?php
function show_categories() {
	
 $html = '<div class="cats_home">';

  $orderby      = 'name';  
  $empty        = 0;

  $args = array(
	  	 'orderby'      => $orderby,
         'hide_empty'   => $empty,
	  	 'parent'		=> 639
  );
 $all_categories = get_terms( 'product_cat', $args );
 foreach ($all_categories as $cat) {
    if($cat->category_parent == 0) {
        $category_id = $cat->term_id;  
		
		$size = 'large';
		$thumbnail_id = get_term_meta( $category_id, 'thumbnail_id', true );
        $image = wp_get_attachment_image_src( $thumbnail_id, $size );
		
		$html .= '<a href="'. get_term_link($cat->slug, 'product_cat') .'">';
		$html .= '<div style=\'background-image:url("'.$image[0].'")\'>';
		$html .= '<div class="color-overlay"></div>';
        $html .= '<h2 class="tit_">'. $cat->name .'</h2>';
		$html .= '<h2 class="desc_">'. $cat->description .'</h2>';
		$html .= '</div>';
		$html .= '</a>';
		
    }       
}
	
	$html .= '</div>';
	
	return $html;

}

add_shortcode('categories', 'show_categories');


function show_posts() {
	
 $html = '<div class="posts_home">';

  $orderby      = 'name';  
  $show_count   = 0;
  $pad_counts   = 0;
  $hierarchical = 1; 
  $title        = '';  
  $empty        = 1;

  $args = array(
	  	 'post_type'   => 'post'/*,
         'orderby'      => $orderby,
         'show_count'   => $show_count,
         'pad_counts'   => $pad_counts,
         'hierarchical' => $hierarchical,
         'title_li'     => $title,
         'hide_empty'   => $empty*/
  );
 $all_posts = new WP_Query( $args );
 if ( $all_posts->have_posts() ) {
        while ( $all_posts->have_posts() ) {
			$all_posts->the_post();

			$link = get_permalink();
			$title = get_the_title();
		
			$html .= '<a href="'. $link .'">';
			$html .= '<div style=\'background-image:url("'.get_the_post_thumbnail_url().'")\'>';
			$html .= '<div class="color-overlay"></div>';
			$html .= '<h2 class="tit_">'. $title .'</h2>';
			$html .= '</div>';
			$html .= '</a>';
			
			
         
 		}
        wp_reset_postdata();
}
	
	$html .= '</div>';
	return $html;

}

add_shortcode('blog_posts', 'show_posts');

function show_trips() {
	
 $html = '<div class="trips_">';

  $orderby      = 'name';  
  $show_count   = 0;
  $pad_counts   = 0;
  $hierarchical = 1; 
  $title        = '';  
  $empty        = 1;

  $args = array(
	  	 'post_type'   => 'product',
         'posts_per_page' => '6'/*,
         'orderby'      => $orderby,
         'show_count'   => $show_count,
         'pad_counts'   => $pad_counts,
         'hierarchical' => $hierarchical,
         'title_li'     => $title,
         'hide_empty'   => $empty*/
  );
 $all_posts = new WP_Query( $args );
 if ( $all_posts->have_posts() ) {
        while ( $all_posts->have_posts() ) {
			$all_posts->the_post();
			global $product;

			$link = get_permalink();
			$title = get_the_title();
			$description = $product->get_short_description();
			$pdf = esc_attr( get_field('link_pdf') );
			$pais = get_field('pais');
			$cordillera = get_field('cordillera');
			$duracion = get_field('duracion');
			$precio_gancho = get_field('precio_gancho');
			$precio_dividido = get_field('precio_dividido');
			$minimo_grupo = get_field('minimo_grupo');
			$maximo_grupo = get_field('maximo_grupo');
			$nivel = get_field('nivel');
			$estilo_viaje = get_field('estilo_viaje');
            
            //$all_product_meta = get_post_meta($product->id);
            //print_r($all_product_meta);
            
            //$viajesx = $product->get_meta('_viaje_fechas');
            //print_r($viajesx);
			
		    //$viajes = $product->get_meta('_viaje_fechas');
            
            //print_r($viajes);
			
			//if(count($viajes) == 1){
				//$fecha = $viajes[0]['inicio'];
			//} 
            $viajes = $product->get_meta('_viaje_fechas');

// Normalizamos a array
if (is_string($viajes)) {
    $json = json_decode($viajes, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $viajes = $json;
    } else {
        $viajes = $viajes ? [ $viajes ] : [];
    }
}
if (empty($viajes)) {
    $viajes = [];
}

// ✅ Ahora ya es seguro usar count()
if (count($viajes) == 1 && isset($viajes[0]['inicio'])) {
    $fecha = $viajes[0]['inicio'];
}

			//print_r($viajes);
			
			
			 $all_categories = get_the_terms( get_the_ID(), 'product_cat' );
			 foreach ($all_categories as $cat) {
				 if ( $cat->parent == 639 ) { //Tipo de viaje
				 	$cat = $cat->name;
				 }
			 }
		
			$html .= '<div class="trip_">';
				$html .= '<div class="img_" style=\'background-image:url("'.get_the_post_thumbnail_url().'")\'>';
				$html .= '<div class="tag_">Más vendido</div>';
				$html .= '</div>';
				$html .= '<div class="body_">';
				$html .= '<div class="meta_">'.$cordillera.', '.$pais.' <span>/</span> '.$duracion.'</div>';
				$html .= '<h2 class="tit_">'. $title .'</h2>';
                $html .= '<div class="desc_">'. $description .'</div>';
                $html .= '<div class="price_">Desde '.$precio_gancho.' o '.$precio_dividido.'/mes</div>';
                $html .= '<div class="detalles_t">Detalles:</div>';
                $html .= '<div class="detalles_">';
                $html .= '<div class="detalle_"><img src="https://staging.manasluadventures.com/wp-content/uploads/2025/09/ic_t.png"> '.$cat.'</div>';
                $html .= '<div class="detalle_"><img style="max-width: 15px;" src="https://staging.manasluadventures.com/wp-content/uploads/2025/09/ic_p.png"> De '.$minimo_grupo.' a '.$maximo_grupo.' personas</div>';
				if(isset($fecha)){ 
                	$str = strtotime($fecha);
					$fecha = date('d \\d\\e F', $str);
                	$html .= '<div class="detalle_"><img src="https://staging.manasluadventures.com/wp-content/uploads/2025/09/ic_cal.png"> '.$fecha.'</div>';		  
				} else {
							$fechas = 'Fechas: &#xa;';
							foreach($viajes as $viaje){
								$str = strtotime($viaje['inicio']);
								$fecha_viaje = date('d \\d\\e F', $str);
								$fechas .= '·'.$fecha_viaje.' &#xa;';
							}
							$toolt = 'data-tooltip="'.$fechas.'"';
							$html .= '<div class="detalle_" ><img src="https://staging.manasluadventures.com/wp-content/uploads/2025/09/ic_cal.png"> +1 fecha <div class="toolt" '.$toolt.'><img src="https://staging.manasluadventures.com/wp-content/uploads/2025/09/toolt.svg"></div> </div>';		
					
				}
                $html .= '<div class="detalle_"><img style="max-width: 20px;" src="https://staging.manasluadventures.com/wp-content/uploads/2025/09/ic_b.png"> '.$estilo_viaje.'</div>';
                $html .= '<div class="detalle_"><img src="https://staging.manasluadventures.com/wp-content/uploads/2025/09/ic_ba.png"> '.$nivel.'</div>';
                $html .= '</div>';
                $html .= '<div class="botones_f">';
                $html .= '<a href="'. $link .'">Ver más</a>';
				if($pdf){ 
					$html .= '<a href="'.$pdf.'">Descargar PDF</a>';
				}
                $html .= '</div>';
				$html .= '</div>';
			$html .= '</div>';
			
			
         
 		}
        wp_reset_postdata();
}
	
	$html .= '</div>';
	return $html;

}

add_shortcode('trips', 'show_trips');