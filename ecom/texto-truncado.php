<?php
/**
 * Plugin Name: Manaslu Texto Truncado
 * Description: Añade truncado con botón "Leer más/menos" para elementos con clase .texto-truncado en el front-end.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    $script_handle = 'manaslu-texto-truncado';
    $style_handle  = 'manaslu-texto-truncado';

    if (!wp_script_is($script_handle, 'registered')) {
        wp_register_script($script_handle, '', [], null, true);
    }

    $script = <<<'JS'
(function(){
    function initTextoTruncado(){
        var containers = document.querySelectorAll('.texto-truncado');
        if (!containers.length) {
            return;
        }

        containers.forEach(function(container){
            if (!container || container.dataset.textoTruncadoInit === '1') {
                return;
            }

            var content = container.querySelector('.texto-truncado__content');
            if (!content) {
                content = document.createElement('div');
                content.className = 'texto-truncado__content';
                var fragment = document.createDocumentFragment();
                while (container.firstChild) {
                    fragment.appendChild(container.firstChild);
                }
                content.appendChild(fragment);
                container.appendChild(content);
            }

            var moreLabel = container.getAttribute('data-read-more') || 'Leer más';
            var lessLabel = container.getAttribute('data-read-less') || 'Leer menos';

            var toggle = container.querySelector('.texto-truncado__toggle');
            if (!toggle) {
                toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'texto-truncado__toggle leer-mas';
                container.appendChild(toggle);
            }

            toggle.setAttribute('aria-expanded', 'false');

            function updateToggleText(){
                var expanded = container.classList.contains('texto-truncado--expanded');
                toggle.textContent = expanded ? lessLabel : moreLabel;
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }

            function refreshVisibility(){
                var expanded = container.classList.contains('texto-truncado--expanded');
                if (expanded) {
                    container.classList.remove('texto-truncado--expanded');
                }

                var hasOverflow = content.scrollHeight > content.clientHeight + 1;

                if (expanded) {
                    container.classList.add('texto-truncado--expanded');
                }

                if (!hasOverflow) {
                    container.classList.remove('texto-truncado--expanded');
                    toggle.style.display = 'none';
                } else {
                    toggle.style.display = '';
                }

                updateToggleText();
            }

            toggle.addEventListener('click', function(){
                container.classList.toggle('texto-truncado--expanded');
                updateToggleText();
            });

            refreshVisibility();

            if (typeof ResizeObserver !== 'undefined') {
                var ro = new ResizeObserver(function(){
                    refreshVisibility();
                });
                ro.observe(content);
            } else {
                window.addEventListener('resize', refreshVisibility);
            }

            container.dataset.textoTruncadoInit = '1';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTextoTruncado);
    } else {
        initTextoTruncado();
    }
})();
JS;

    wp_add_inline_script($script_handle, $script);
    wp_enqueue_script($script_handle);

    if (!wp_style_is($style_handle, 'registered')) {
        wp_register_style($style_handle, false, [], null);
    }

    $style = <<<'CSS'
.texto-truncado{
    position:relative;
}
.texto-truncado__content{
    margin:0;
    overflow:hidden;
    display:-webkit-box;
    -webkit-box-orient:vertical;
    -webkit-line-clamp:var(--texto-truncado-lines, 3);
    line-height:var(--texto-truncado-line-height, 1.5);
    max-height:calc(var(--texto-truncado-line-height, 1.5) * var(--texto-truncado-lines, 3) * 1em);
}
.texto-truncado__content > p{
    display:inline;
    margin:0;
}
.texto-truncado__content > p:not(:last-child)::after{
    content:" ";
}
.texto-truncado .leer-mas{
    padding-left:0;
    border:0;
    background:none;
    color:inherit;
    font:inherit;
    text-decoration:underline;
    cursor:pointer;
}
.texto-truncado .leer-mas:hover{
    background:none;
    color:#000;
}
.texto-truncado--expanded .texto-truncado__content{
    -webkit-line-clamp:unset;
    max-height:none;
    overflow:visible;
}
.texto-truncado--expanded .texto-truncado__content > p{
    display:block;
    margin:0 0 1em;
}
.texto-truncado--expanded .texto-truncado__content > p:last-child{
    margin-bottom:0;
}
CSS;

    wp_add_inline_style($style_handle, $style);
    wp_enqueue_style($style_handle);
});
