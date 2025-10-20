<?php
/**
 * Plugin Module: Manaslu - Shortcode Tomar Vuelo
 * Shortcode: [tomar_vuelo]
 * Versión: 1.0.0
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('mv_tv_print_styles')) {
    function mv_tv_print_styles(){
        static $printed = false;
        if ($printed) return;
        $printed = true;
        $css = <<<CSS
.tv-wrap{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:20px;margin:18px 0;box-shadow:0 12px 35px rgba(15,23,42,.07);font-family:"Host Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;color:#111}
.tv-wrap .tv-heading{display:flex;align-items:center;justify-content:space-between;gap:12px}
.tv-wrap .tv-heading .tv-title{font-weight:700;font-size:1.05rem;color:#0f172a;margin:0}
.tv-choice-group{display:flex;gap:12px;flex-wrap:wrap;margin-top:14px}
.tv-choice{position:relative}
.tv-choice input{position:absolute;inset:0;opacity:0;cursor:pointer}
.tv-choice span{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:999px;font-weight:600;border:1px solid rgba(15,23,42,.35);color:#0f172a;background:#fff;transition:all .2s ease}
.tv-choice input:focus-visible + span{outline:2px solid #FB2240;outline-offset:2px}
.tv-choice input:checked + span{background:linear-gradient(135deg,#FB2240,#d10f2c);color:#fff;border-color:transparent;box-shadow:0 10px 30px rgba(251,34,64,.35)}
.tv-panel{margin-top:22px;padding:18px;border-radius:14px;background:#f8fafc;border:1px solid rgba(15,23,42,.08);display:grid;gap:14px;transition:max-height .25s ease,opacity .2s ease}
.tv-panel.is-hidden{display:block;max-height:0;opacity:0;overflow:hidden;padding-top:0;padding-bottom:0;border-width:0;margin-top:0}
.tv-fieldset{display:grid;gap:14px}
.tv-field{position:relative;display:flex;align-items:center;background:#fff;border:1px solid rgba(15,23,42,.12);border-radius:12px;padding:10px 14px;box-shadow:0 8px 18px rgba(15,23,42,.05)}
.tv-field input{flex:1;border:0;font-size:.95rem;font-weight:500;color:#0f172a;background:transparent;outline:none}
.tv-field input::placeholder{color:#94a3b8}
.tv-field svg{flex-shrink:0;width:18px;height:18px;color:#FB2240}
.tv-feedback{font-size:.85rem;font-weight:600;color:#64748b;min-height:18px}
.tv-feedback.is-empty{color:#FB2240}
@media (max-width:640px){.tv-wrap{padding:18px}.tv-fieldset{gap:12px}.tv-choice span{width:100%;justify-content:center}}
CSS;

        echo '<style id="mv-tomar-vuelo-inline">' . $css . '</style>';
    }
}

if (!function_exists('mv_tv_ensure_styles')) {
    function mv_tv_ensure_styles(){
        static $hooked = false;
        if ($hooked) return;
        $hooked = true;
        add_action('wp_footer', 'mv_tv_print_styles', 20);
        add_action('elementor/editor/footer', 'mv_tv_print_styles', 20);
    }
}

/** Shortcode: [tomar_vuelo default="no" name="tomar_vuelo"] */
add_shortcode('tomar_vuelo', function($atts){
    $a = shortcode_atts([
        'default' => 'no',          // 'si' | 'no'
        'name'    => 'tomar_vuelo', // prefijo campos por si hay varias instancias
    ], $atts, 'tomar_vuelo');

    $uid         = uniqid('tv_');
    $name        = sanitize_key($a['name']);
    $default_yes = strtolower($a['default']) === 'si';

    mv_tv_ensure_styles();
    ob_start();
    ?>
    <div class="tv-wrap" id="<?php echo esc_attr($uid); ?>">
      <div class="tv-heading">
        <p class="tv-title"><?php echo esc_html__('¿Quieres que busquemos los vuelos por ti?','manaslu'); ?></p>
        <span class="tv-hint" style="font-size:.85rem;color:#64748b;font-weight:600;">
          <?php echo esc_html__('No podras decidirlo más adelante','manaslu'); ?>
        </span>
      </div>
      <div class="tv-choice-group" role="radiogroup" aria-label="<?php echo esc_attr__('¿Quieres asistencia con los vuelos?','manaslu'); ?>">
        <label class="tv-choice">
          <input type="radio" name="<?php echo esc_attr($name); ?>[want]" value="si" <?php checked(true, $default_yes); ?>>
          <span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 19.5l19-15"/><path d="M18 19.5h3v-3"/><path d="M2.5 6.5v-3h3"/></svg>
            <?php echo esc_html__('Sí, ayúdenme','manaslu'); ?>
          </span>
        </label>
        <label class="tv-choice">
          <input type="radio" name="<?php echo esc_attr($name); ?>[want]" value="no" <?php checked(false, $default_yes); ?>>
          <span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4l16 16"/><path d="M20 4L4 20"/></svg>
            <?php echo esc_html__('No, gracias','manaslu'); ?>
          </span>
        </label>
      </div>

      <div class="tv-panel<?php echo $default_yes ? '' : ' is-hidden'; ?>" aria-live="polite">
        <div class="tv-fieldset">
          <label for="<?php echo esc_attr($uid); ?>_city" class="tv-title" style="font-weight:700;margin:0;">
            <?php echo esc_html__('¿Desde qué ciudad viajas?','manaslu'); ?>
          </label>
          <div class="tv-field">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 5.25-9 11-9 11S3 15.25 3 10a9 9 0 1118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <input type="text"
                   id="<?php echo esc_attr($uid); ?>_city"
                   class="tv-input"
                   name="<?php echo esc_attr($name); ?>[city]"
                   placeholder="<?php echo esc_attr__('Ej. Madrid','manaslu'); ?>"
                   autocomplete="off"
                   aria-label="<?php echo esc_attr__('Escribe la ciudad desde la que viajas','manaslu'); ?>">
          </div>
          <p class="tv-feedback is-empty" data-role="feedback"><?php echo esc_html__('Cuéntanos desde qué ciudad saldrías.', 'manaslu'); ?></p>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var root   = document.getElementById(<?php echo json_encode($uid); ?>);
      if(!root) return;
      var yes    = root.querySelector('input[type="radio"][value="si"]');
      var no     = root.querySelector('input[type="radio"][value="no"]');
      var panel  = root.querySelector('.tv-panel');
      var cityInput = root.querySelector('.tv-input');
      var feedback = root.querySelector('[data-role="feedback"]');
      var baseMsg = <?php echo wp_json_encode(esc_html__('Cuéntanos desde qué ciudad saldrías.', 'manaslu')); ?>;

      function updatePanel(){
        if (!panel) return;
        if (yes && yes.checked) {
          panel.classList.remove('is-hidden');
        } else {
          panel.classList.add('is-hidden');
        }
      }
      if (yes) yes.addEventListener('change', updatePanel);
      if (no)  no.addEventListener('change', updatePanel);
      updatePanel();

      function handleCityInput(){
        if (!feedback) return;
        var hasValue = cityInput && cityInput.value.trim().length > 0;
        if (hasValue) {
          feedback.textContent = '';
          feedback.classList.remove('is-empty');
        } else {
          feedback.textContent = baseMsg;
          feedback.classList.add('is-empty');
        }
      }

      if (cityInput) {
        cityInput.addEventListener('input', handleCityInput);
        handleCityInput();
      } else if (feedback) {
        feedback.textContent = '';
        feedback.classList.remove('is-empty');
      }
    })();
    </script>
    <?php
    return ob_get_clean();
});
