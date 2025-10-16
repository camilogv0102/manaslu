<?php
/**
 * Plantilla personalizada de checkout a dos columnas para WooCommerce.
 *
 * Columna izquierda: datos del titular, viajeros extra y aceptación de condiciones.
 * Columna derecha: resumen del pedido + métodos de pago nativos de WooCommerce.
 *
 * Pensado para usarse como reemplazo de templates/checkout/form-checkout.php en el theme.
 */

defined('ABSPATH') || exit;

if (!function_exists('manaslu_checkout_validate_required_inputs')) {
    /**
     * Valida los campos obligatorios del checkout (titular, viajeros y aceptación).
     */
    function manaslu_checkout_validate_required_inputs(): void
    {
        if (!function_exists('wc_add_notice')) {
            return;
        }

        $required = [
            'billing_first_name' => __('Nombre', 'manaslu'),
            'billing_last_name'  => __('Apellidos', 'manaslu'),
            'billing_document'   => __('DNI / Pasaporte', 'manaslu'),
            'billing_phone'      => __('Teléfono', 'manaslu'),
            'billing_email'      => __('Correo', 'manaslu'),
            'billing_address_1'  => __('Dirección', 'manaslu'),
            'billing_city'       => __('Ciudad', 'manaslu'),
            'billing_postcode'   => __('Código postal', 'manaslu'),
            'billing_state'      => __('Provincia', 'manaslu'),
            'billing_country'    => __('País', 'manaslu'),
        ];

        foreach ($required as $field_key => $label) {
            $value = isset($_POST[$field_key]) ? wp_unslash($_POST[$field_key]) : '';
            if (!is_string($value)) {
                $value = '';
            }
            if (trim($value) === '') {
                wc_add_notice(
                    sprintf(__('El campo "%s" es obligatorio.', 'manaslu'), $label),
                    'error'
                );
            }
        }

        $email_raw = isset($_POST['billing_email']) ? wp_unslash($_POST['billing_email']) : '';
        $email     = sanitize_email($email_raw);
        if ($email_raw !== '' && $email === '') {
            wc_add_notice(__('Introduce un correo electrónico válido.', 'manaslu'), 'error');
        }

        $traveler_total = isset($_POST['mv_travelers_total']) ? (int) $_POST['mv_travelers_total'] : 0;
        $travelers_data = [];
        if (isset($_POST['mv_travelers']) && is_array($_POST['mv_travelers'])) {
            $travelers_data = $_POST['mv_travelers'];
        }

        $traveler_labels = [
            'first_name' => __('Nombre', 'manaslu'),
            'last_name'  => __('Apellidos', 'manaslu'),
            'document'   => __('DNI / Pasaporte', 'manaslu'),
        ];

        if ($traveler_total > 0) {
            for ($i = 0; $i < $traveler_total; $i++) {
                $traveler = isset($travelers_data[$i]) && is_array($travelers_data[$i]) ? $travelers_data[$i] : [];
                foreach ($traveler_labels as $key => $label) {
                    $value = isset($traveler[$key]) ? wp_unslash($traveler[$key]) : '';
                    if (!is_string($value)) {
                        $value = '';
                    }
                    if (trim($value) === '') {
                        wc_add_notice(
                            sprintf(__('Completa %1$s para la persona %2$d.', 'manaslu'), $label, $i + 1),
                            'error'
                        );
                    }
                }
            }
        }

        if (empty($_POST['mv_accept_terms'])) {
            wc_add_notice(__('Debes aceptar las condiciones del viaje.', 'manaslu'), 'error');
        }
    }
}
add_action('woocommerce_checkout_process', 'manaslu_checkout_validate_required_inputs', 5);

if (!function_exists('manaslu_checkout_handle_inline_registration')) {
    /**
     * Crea al usuario durante el checkout cuando no existe sesión.
     */
    function manaslu_checkout_handle_inline_registration(): void
    {
        if (is_user_logged_in() || !function_exists('wc_add_notice')) {
            return;
        }

        $password_raw = isset($_POST['mv_account_password']) ? wp_unslash($_POST['mv_account_password']) : '';
        if (!is_string($password_raw)) {
            $password_raw = '';
        }
        $password = trim($password_raw);

        $email_raw = isset($_POST['billing_email']) ? wp_unslash($_POST['billing_email']) : '';
        $email     = sanitize_email($email_raw);

        $min_length = (int) apply_filters('manaslu_inline_checkout_password_min_length', 6);

        if ($password === '') {
            wc_add_notice(__('Ingresa una clave para crear tu cuenta.', 'manaslu'), 'error');
            return;
        }

        if ($min_length > 0 && strlen($password) < $min_length) {
            wc_add_notice(
                sprintf(__('La clave debe tener al menos %d caracteres.', 'manaslu'), $min_length),
                'error'
            );
            return;
        }

        if ($email === '' || !is_email($email)) {
            wc_add_notice(__('Introduce un correo electrónico válido.', 'manaslu'), 'error');
            return;
        }

        if (email_exists($email)) {
            wc_add_notice(__('Ya existe una cuenta con este correo. Inicia sesión para continuar.', 'manaslu'), 'error');
            return;
        }

        if (wc_notice_count('error') > 0) {
            return;
        }

        $first_name = isset($_POST['billing_first_name']) ? sanitize_text_field(wp_unslash($_POST['billing_first_name'])) : '';
        $last_name  = isset($_POST['billing_last_name']) ? sanitize_text_field(wp_unslash($_POST['billing_last_name'])) : '';
        $display    = trim($first_name . ' ' . $last_name);
        if ($display === '') {
            $display = $email;
        }

        if (function_exists('wc_create_new_customer')) {
            $user_id = wc_create_new_customer($email, '', $password, [
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => $display,
            ]);
        } else {
            $username = sanitize_user(current(explode('@', $email)) ?: $email, true);
            if ($username === '') {
                $username = $email;
            }
            $base_username = $username;
            $suffix        = 1;
            while (username_exists($username)) {
                $username = $base_username . $suffix;
                $suffix++;
            }
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID'           => $user_id,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'display_name' => $display,
                ]);
            }
        }

        if (is_wp_error($user_id)) {
            $message = $user_id->get_error_message();
            if (!$message) {
                $message = __('No se pudo crear la cuenta. Intenta nuevamente.', 'manaslu');
            }
            wc_add_notice($message, 'error');
            return;
        }

        if (!$user_id || !is_int($user_id)) {
            wc_add_notice(__('No se pudo crear la cuenta. Intenta nuevamente.', 'manaslu'), 'error');
            return;
        }

        // Guardar nombres para futuras reservas.
        wp_update_user([
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $display,
        ]);

        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);

        if (function_exists('wc_set_customer_auth_cookie')) {
            wc_set_customer_auth_cookie($user_id);
        } else {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        if (function_exists('WC') && class_exists('WC_Customer')) {
            WC()->customer = new WC_Customer($user_id);
        }
    }
}
add_action('woocommerce_checkout_process', 'manaslu_checkout_handle_inline_registration', 20);

if (!function_exists('manaslu_checkout_terms_markup')) {
    /**
     * Devuelve el marcado HTML del checkbox de aceptación.
     */
    function manaslu_checkout_terms_markup(): string
    {
        ob_start();
        ?>
        <section class="mvcf-section mvcf-section--terms" data-mv-terms>
            <div class="mvcf-checkbox">
                <label class="mvcf-checkbox-label">
                    <input type="checkbox" name="mv_accept_terms" value="1" required />
                    <span><?php echo esc_html__('Acepta las condiciones del viaje, políticas generales de cancelación y tratamiento de imágenes', 'manaslu'); ?></span>
                </label>
            </div>
        </section>
        <?php
        return trim((string) ob_get_clean());
    }
}

if (!function_exists('manaslu_checkout_render_payment_with_terms')) {
    /**
     * Renderiza el bloque de pago nativo añadiendo el checkbox antes del texto legal.
     */
    function manaslu_checkout_render_payment_with_terms(): void
    {
        if (!function_exists('wc_get_template') || !function_exists('WC')) {
            return;
        }

        $checkout = WC()->checkout();
        if (!$checkout) {
            return;
        }

        ob_start();
        wc_get_template('checkout/payment.php', ['checkout' => $checkout]);
        $payment_html = (string) ob_get_clean();
        if ($payment_html === '') {
            echo $payment_html;
            return;
        }

        $terms_html = manaslu_checkout_terms_markup();

        if ($terms_html !== '' && strpos($payment_html, 'name="mv_accept_terms"') === false) {
            if (preg_match('/<div class="woocommerce-privacy-policy-text[^>]*>/', $payment_html, $matches)) {
                $payment_html = preg_replace(
                    '/<div class="woocommerce-privacy-policy-text[^>]*>/',
                    $terms_html . $matches[0],
                    $payment_html,
                    1
                );
            } else {
                $payment_html = str_replace(
                    '<div class="form-row place-order">',
                    '<div class="form-row place-order">' . $terms_html,
                    $payment_html
                );
            }
        }

        echo $payment_html;
    }
}

remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
add_action('woocommerce_checkout_order_review', 'manaslu_checkout_render_payment_with_terms', 20);

$is_user_logged_in = is_user_logged_in();

$checkout = WC()->checkout();

do_action('woocommerce_before_checkout_form', $checkout);

if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce')));
    return;
}

$holder_fields = [
    'billing_first_name' => [
        'label'       => __('Nombre', 'manaslu'),
        'type'        => 'text',
        'autocomplete'=> 'given-name',
        'required'    => true,
    ],
    'billing_last_name' => [
        'label'       => __('Apellidos', 'manaslu'),
        'type'        => 'text',
        'autocomplete'=> 'family-name',
        'required'    => true,
    ],
    'billing_document' => [
        'label'    => __('DNI / Pasaporte', 'manaslu'),
        'type'     => 'text',
        'required' => true,
    ],
    'billing_phone' => [
        'label'       => __('Teléfono', 'manaslu'),
        'type'        => 'tel',
        'autocomplete'=> 'tel',
        'inputmode'   => 'tel',
        'required'    => true,
    ],
    'billing_email' => [
        'label'       => __('Correo', 'manaslu'),
        'type'        => 'email',
        'autocomplete'=> 'email',
        'required'    => true,
    ],
    'billing_address_1' => [
        'label'       => __('Dirección', 'manaslu'),
        'type'        => 'text',
        'autocomplete'=> 'address-line1',
        'required'    => true,
        'full_width'  => true,
    ],
    'billing_city' => [
        'label'       => __('Ciudad', 'manaslu'),
        'type'        => 'text',
        'autocomplete'=> 'address-level2',
        'required'    => true,
    ],
    'billing_postcode' => [
        'label'       => __('Código postal', 'manaslu'),
        'type'        => 'text',
        'autocomplete'=> 'postal-code',
        'inputmode'   => 'numeric',
        'required'    => true,
    ],
    'billing_state' => [
        'label'       => __('Provincia', 'manaslu'),
        'type'        => 'text',
        'autocomplete'=> 'address-level1',
        'required'    => true,
    ],
    'billing_country' => [
        'label'       => __('País', 'manaslu'),
        'type'        => 'text',
        'autocomplete'=> 'country-name',
        'placeholder' => 'ES',
        'pattern'     => '[A-Za-z]{2}',
        'required'    => true,
    ],
];

$traveler_fields = [
    'first_name' => [
        'label' => __('Nombre', 'manaslu'),
    ],
    'last_name' => [
        'label' => __('Apellidos', 'manaslu'),
    ],
    'document' => [
        'label' => __('DNI / Pasaporte', 'manaslu'),
    ],
];

$traveler_title_pattern = __('Persona %d', 'manaslu');
$remove_title_pattern    = __('Eliminar persona %d', 'manaslu');

if (!function_exists('manaslu_checkout_render_traveler_card')) {
    /**
     * Renderiza la tarjeta de un viajero.
     */
    function manaslu_checkout_render_traveler_card(int $index, array $traveler_fields, string $title_pattern, string $remove_pattern, bool $is_template = false): string {
        $display_index = $index + 1;
        $title_text    = $is_template
            ? __('Persona', 'manaslu')
            : sprintf($title_pattern, $display_index);

        ob_start();
        ?>
        <article class="mvcf-traveler-card" data-traveler>
            <header class="mvcf-traveler-card__header">
                <span
                    class="mvcf-traveler-card__title"
                    data-traveler-title
                    data-title-pattern="<?php echo esc_attr($title_pattern); ?>"
                >
                    <?php echo esc_html($title_text); ?>
                </span>
            </header>
            <div class="mvcf-grid">
                <?php foreach ($traveler_fields as $key => $config) : ?>
                    <?php
                    $input_id   = $is_template ? '' : 'mv_traveler_' . $index . '_' . $key;
                    $input_name = $is_template ? '' : 'mv_travelers[' . $index . '][' . $key . ']';
                    ?>
                    <div class="mvcf-field" data-field-wrapper="<?php echo esc_attr($key); ?>">
                        <label class="mvcf-field-label" <?php echo $is_template ? '' : 'for="' . esc_attr($input_id) . '"'; ?>>
                            <?php echo esc_html($config['label']); ?>
                        </label>
                        <input
                            class="mvcf-input"
                            type="text"
                            data-field="<?php echo esc_attr($key); ?>"
                            <?php
                            if (!$is_template) {
                                echo ' id="' . esc_attr($input_id) . '"';
                                echo ' name="' . esc_attr($input_name) . '"';
                            }
                            ?>
                            required
                        />
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
        <?php
        return trim((string) ob_get_clean());
    }
}

$min_travelers = max(1, (int) apply_filters('manaslu_checkout_form_min_travelers', 1));
$max_travelers = max($min_travelers, (int) apply_filters('manaslu_checkout_form_max_travelers', 10));

$initial_travelers = isset($_POST['mv_travelers_total'])
    ? (int) $_POST['mv_travelers_total']
    : $min_travelers;

if ($initial_travelers < $min_travelers) {
    $initial_travelers = $min_travelers;
}
if ($initial_travelers > $max_travelers) {
    $initial_travelers = $max_travelers;
}

$style_handle  = 'manaslu-checkout-form-template';
$script_handle = 'manaslu-checkout-form-template';

$inline_styles = trim(<<<'CSS'
.manaslu-checkout-form {
    max-width: 1200px;
    margin: 2rem auto;
}
.manaslu-checkout-grid {
    display: grid;
    gap: 2rem;
}
@media (min-width: 992px) {
    .manaslu-checkout-grid {
        grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
        align-items: flex-start;
    }
}
.manaslu-checkout-primary,
.manaslu-checkout-summary {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
}
.manaslu-checkout-primary {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}
.screen-reader-text {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.manaslu-checkout-summary {
    display: flex;
    flex-direction: column;
    gap: 1.8rem;
    position: sticky;
    top: 2rem;
    align-self: flex-start;
    max-height: calc(100vh - 3rem);
    overflow-y: auto;
}
@media (max-width: 991px) {
    .manaslu-checkout-summary {
        position: static;
        max-height: none;
    }
}
.manaslu-checkout-summary h2 {
    margin: 0;
    font-size: 1.4rem;
    color: #0f172a;
}
.mvcf-section-header h2 {
    margin: 0;
    font-size: 1.35rem;
    line-height: 1.3;
    color: #0f172a;
}
.mvcf-section-header p {
    margin: 0.35rem 0 0;
    color: #475569;
    font-size: 0.95rem;
}
.mvcf-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
    margin-top: 1.5rem;
}
.mvcf-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.mvcf-field-note {
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.4;
    margin: 0.15rem 0 0;
}
.mvcf-field.is-full {
    grid-column: 1 / -1;
}
.mvcf-field-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: #1e293b;
}
.mvcf-input {
    border: 1px solid #cbd5f5;
    border-radius: 10px;
    background-color: #f8fafc;
    padding: 0.7rem 0.85rem;
    font-size: 0.95rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.mvcf-input:focus {
    border-color: #2563eb;
    outline: 0;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    background-color: #ffffff;
}
.mvcf-input--number {
    max-width: 120px;
    text-align: center;
}
.mvcf-traveler-count {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1.5rem;
}
.mvcf-count-display {
    display: inline-flex;
    align-items: baseline;
    gap: 0.65rem;
    background: #eef2ff;
    border-radius: 999px;
    padding: 0.6rem 1rem;
    font-weight: 600;
    color: #1e3a8a;
    width: fit-content;
}
.mvcf-count-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #0f172a;
}
.mvcf-count-note {
    font-size: 0.85rem;
    color: #475569;
}
.mvcf-count-controls {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.mvcf-count-button {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 1px solid rgba(30, 58, 138, 0.2);
    background: #e0e7ff;
    color: #1e3a8a;
    font-size: 1.1rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.15s ease, color 0.15s ease;
}
.mvcf-count-button:hover {
    background: #c7d2fe;
    transform: translateY(-1px);
}
.mvcf-count-button:focus-visible {
    outline: 3px solid rgba(251, 34, 64, 0.35);
    outline-offset: 2px;
}
.mvcf-count-button:disabled,
.mvcf-count-button[aria-disabled="true"] {
    background: #e2e8f0;
    color: #94a3b8;
    border-color: rgba(148, 163, 184, 0.5);
    cursor: not-allowed;
    transform: none;
}
.mvcf-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.65rem 1.6rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.mvcf-button--primary {
    background: #fb2240;
    color: #ffffff;
    box-shadow: 0 10px 20px rgba(251, 34, 64, 0.25);
}
.mvcf-button--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px rgba(251, 34, 64, 0.28);
}
.mvcf-button--secondary {
    background: #e0e7ff;
    color: #1e3a8a;
    border: 1px solid rgba(30, 58, 138, 0.2);
}
.mvcf-button--secondary:hover {
    background: #c7d2fe;
    transform: translateY(-1px);
}
.mvcf-travelers {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-top: 1.75rem;
}
.mvcf-traveler-card {
    border: 1px solid rgba(148, 163, 184, 0.4);
    border-radius: 14px;
    padding: 1.5rem;
    position: relative;
    background: #ffffff;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
}
.mvcf-traveler-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.2rem;
}
.mvcf-traveler-card__title {
    font-weight: 700;
    color: #1e293b;
    background: #eef2ff;
    border-radius: 999px;
    padding: 0.4rem 1rem;
    font-size: 0.9rem;
}
.mvcf-remove {
    background: transparent;
    border: none;
    color: #ef4444;
    font-size: 1.4rem;
    line-height: 1;
    cursor: pointer;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    transition: background 0.2s ease, transform 0.2s ease;
}
.mvcf-remove:hover:not([disabled]) {
    background: rgba(239, 68, 68, 0.08);
    transform: scale(1.05);
}
.mvcf-remove[disabled],
.mvcf-remove[aria-disabled="true"] {
    opacity: 0.4;
    cursor: not-allowed;
}
.mvcf-checkbox {
    margin-top: 1rem;
}
.mvcf-checkbox-label {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.75rem;
    align-items: center;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.45);
    border-radius: 12px;
    padding: 1rem 1.2rem;
    font-size: 0.95rem;
    color: #1f2937;
}
.mvcf-checkbox-label input[type="checkbox"] {
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 4px;
    border: 1px solid #94a3b8;
}
.mvcf-summary-card {
    background: #f8fafc;
    border-radius: 14px;
    padding: 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.35);
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}
.mvcf-summary-block {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.mvcf-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.95rem;
    color: #1f2937;
}
.mvcf-summary-row strong {
    color: #0f172a;
}
.mvcf-summary-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    font-size: 0.9rem;
    color: #475569;
}
.mvcf-summary-list li span {
    display: inline-flex;
    justify-content: space-between;
    width: 100%;
    gap: 0.75rem;
}
.manaslu-checkout-summary #order_review_heading {
    display: none;
}
.manaslu-checkout-summary #order_review {
    background: #ffffff;
}
.woocommerce-checkout-review-order-table {
    width: 100%;
    margin-bottom: 1.5rem;
    border-collapse: separate;
    border-spacing: 0;
    background: #ffffff;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
}
.woocommerce-checkout-review-order-table thead th {
    padding: 0.9rem 1.2rem;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    color: #475569;
    background: #f1f5f9;
    border-bottom: 1px solid rgba(148, 163, 184, 0.35);
}
.woocommerce-checkout-review-order-table tbody td,
.woocommerce-checkout-review-order-table tfoot th,
.woocommerce-checkout-review-order-table tfoot td {
    padding: 1rem 1.2rem;
    font-size: 0.95rem;
    color: #0f172a;
}
.woocommerce-checkout-review-order-table tbody tr + tr td {
    border-top: 1px solid rgba(148, 163, 184, 0.25);
}
.woocommerce-checkout-review-order-table .product-name {
    font-weight: 600;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.woocommerce-checkout-review-order-table .product-quantity {
    font-weight: 700;
    color: #1d4ed8;
}
.woocommerce-checkout-review-order-table .variation {
    margin: 0;
    display: grid;
    gap: 0.3rem;
    font-size: 0.85rem;
    color: #475569;
}
.woocommerce-checkout-review-order-table .variation dt {
    font-weight: 600;
}
.woocommerce-checkout-review-order-table .variation dd {
    margin: 0;
}
.woocommerce-checkout-review-order-table .variation dd p {
    margin: 0;
}
.woocommerce-checkout-review-order-table tfoot tr {
    background: #f8fafc;
}
.woocommerce-checkout-review-order-table .order-total th,
.woocommerce-checkout-review-order-table .order-total td {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
}
#payment.woocommerce-checkout-payment {
    background-color: #f8fafc;
    border-radius: 14px;
    padding: 1.6rem 1.5rem;
    border: 1px solid rgba(148, 163, 184, 0.45);
    color: #1e293b;
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}
#payment.woocommerce-checkout-payment ul.payment_methods {
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
#payment.woocommerce-checkout-payment ul.payment_methods li {
    list-style: none;
    background: #ffffff;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 12px;
    padding: 0.9rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    color: #1e293b;
    box-shadow: 0 10px 18px rgba(15, 23, 42, 0.05);
}
#payment.woocommerce-checkout-payment ul.payment_methods li label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    color: #0f172a;
    cursor: pointer;
}
#payment.woocommerce-checkout-payment ul.payment_methods li input[type="radio"] {
    accent-color: #1d4ed8;
}
#payment.woocommerce-checkout-payment ul.payment_methods li .payment_box {
    margin: 0;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    color: #334155;
    font-size: 0.9rem;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
}
#payment.woocommerce-checkout-payment ul.payment_methods li .payment_box::before {
    display: none;
}
#payment.woocommerce-checkout-payment .woocommerce-privacy-policy-text,
#payment.woocommerce-checkout-payment .woocommerce-privacy-policy-text p,
#payment.woocommerce-checkout-payment .woocommerce-terms-and-conditions-wrapper,
#payment.woocommerce-checkout-payment .woocommerce-terms-and-conditions-wrapper p {
    color: #475569;
}
#payment.woocommerce-checkout-payment .woocommerce-privacy-policy-text a,
#payment.woocommerce-checkout-payment .woocommerce-terms-and-conditions-wrapper a {
    color: #1d4ed8;
    font-weight: 600;
    text-decoration: none;
}
#payment.woocommerce-checkout-payment .woocommerce-privacy-policy-text a:hover,
#payment.woocommerce-checkout-payment .woocommerce-terms-and-conditions-wrapper a:hover {
    text-decoration: underline;
}
#payment.woocommerce-checkout-payment .woocommerce-terms-and-conditions-wrapper label {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    background: #ffffff;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    color: #1e293b;
}
#payment.woocommerce-checkout-payment .place-order {
    margin-top: 0.2rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
#payment.woocommerce-checkout-payment .place-order noscript {
    order: 1;
}
#payment.woocommerce-checkout-payment .place-order .mvcf-section--terms {
    order: 2;
    margin: 0;
}
#payment.woocommerce-checkout-payment .place-order .woocommerce-privacy-policy-text,
#payment.woocommerce-checkout-payment .place-order .woocommerce-terms-and-conditions-wrapper {
    order: 3;
}
#payment.woocommerce-checkout-payment .place-order #place_order {
    order: 4;
}
#payment.woocommerce-checkout-payment #place_order {
    width: 100%;
    border-radius: 999px;
    padding: 0.85rem 1.6rem;
    font-weight: 600;
    background: #fb2240;
    color: #ffffff;
    box-shadow: 0 10px 20px rgba(251, 34, 64, 0.25);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
#payment.woocommerce-checkout-payment #place_order:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px rgba(251, 34, 64, 0.28);
}
@media (max-width: 768px) {
    .manaslu-checkout-form {
        margin: 1.5rem auto;
    }
    .manaslu-checkout-primary,
    .manaslu-checkout-summary {
        padding: 1.5rem;
    }
    .mvcf-grid {
        grid-template-columns: 1fr;
    }
    #payment.woocommerce-checkout-payment #place_order {
        width: 100%;
    }
}
CSS);

$inline_script = trim(<<<'JS'
(function () {
    function initCheckoutForm(form) {
        var wrapper = form.querySelector('[data-travelers-wrapper]');
        var template = form.querySelector('template[data-traveler-template]');
        var countInput = form.querySelector('[data-traveler-count]');
        var decrementButton = form.querySelector('[data-decrement-traveler]');
        var incrementButton = form.querySelector('[data-increment-traveler]');

        if (!wrapper || !template || !template.content) {
            return;
        }

        var min = parseInt(form.getAttribute('data-min-travelers'), 10);
        if (Number.isNaN(min) || min < 1) {
            min = 1;
        }

        var max = parseInt(form.getAttribute('data-max-travelers'), 10);
        if (Number.isNaN(max) || max < min) {
            max = min;
        }

        function createTravelerCard() {
            return template.content.firstElementChild.cloneNode(true);
        }

        function updateCountControls(count) {
            if (countInput) {
                countInput.value = count;
            }

            if (decrementButton) {
                var disableDecrease = count <= min;
                decrementButton.disabled = disableDecrease;
                decrementButton.setAttribute('aria-disabled', disableDecrease ? 'true' : 'false');
            }

            if (incrementButton) {
                var disableIncrease = count >= max;
                incrementButton.disabled = disableIncrease;
                incrementButton.setAttribute('aria-disabled', disableIncrease ? 'true' : 'false');
            }
        }

        function renumberCards() {
            var cards = Array.prototype.slice.call(wrapper.querySelectorAll('[data-traveler]'));

            cards.forEach(function (card, index) {
                card.setAttribute('data-traveler-index', String(index));

                var title = card.querySelector('[data-traveler-title]');
                if (title) {
                    var titlePattern = title.getAttribute('data-title-pattern') || 'Persona %d';
                    title.textContent = titlePattern.replace('%d', String(index + 1));
                }

                var removeButton = card.querySelector('[data-remove-traveler]');
                if (removeButton) {
                    var disableRemove = cards.length <= min;
                    removeButton.disabled = disableRemove;
                    removeButton.setAttribute('aria-disabled', disableRemove ? 'true' : 'false');

                    var removePattern = removeButton.getAttribute('data-remove-pattern') || 'Eliminar persona %d';
                    var label = removePattern.replace('%d', String(index + 1));
                    removeButton.setAttribute('aria-label', label);
                }

                Array.prototype.slice.call(card.querySelectorAll('[data-field-wrapper]')).forEach(function (fieldWrapper) {
                    var key = fieldWrapper.getAttribute('data-field-wrapper');
                    if (!key) {
                        return;
                    }

                    var input = fieldWrapper.querySelector('[data-field]');
                    if (!input) {
                        return;
                    }

                    var inputId = 'mv_traveler_' + index + '_' + key;
                    var inputName = 'mv_travelers[' + index + '][' + key + ']';

                    input.id = inputId;
                    input.name = inputName;

                    var label = fieldWrapper.querySelector('label');
                    if (label) {
                        label.setAttribute('for', inputId);
                    }
                });
            });

            updateCountControls(cards.length);
        }

        function ensureCardCount(target) {
            var desired = parseInt(target, 10);
            if (Number.isNaN(desired)) {
                desired = min;
            }

            desired = Math.min(Math.max(desired, min), max);

            var cards = wrapper.querySelectorAll('[data-traveler]');
            var current = cards.length;

            while (current < desired) {
                var newCard = createTravelerCard();
                if (!newCard) {
                    break;
                }
                wrapper.appendChild(newCard);
                current += 1;
            }

            while (current > desired && current > min) {
                var lastCard = wrapper.querySelector('[data-traveler]:last-of-type');
                if (!lastCard) {
                    break;
                }
                wrapper.removeChild(lastCard);
                current -= 1;
            }

            renumberCards();
        }

        if (decrementButton) {
            decrementButton.addEventListener('click', function (event) {
                event.preventDefault();
                var next = (parseInt(countInput && countInput.value, 10) || min) - 1;
                ensureCardCount(next);
            });
        }

        if (incrementButton) {
            incrementButton.addEventListener('click', function (event) {
                event.preventDefault();
                var next = (parseInt(countInput && countInput.value, 10) || min) + 1;
                ensureCardCount(next);
            });
        }

        if (countInput) {
            countInput.addEventListener('change', function () {
                ensureCardCount(countInput.value);
            });
            countInput.addEventListener('input', function () {
                var value = countInput.value;
                if (value === '') {
                    return;
                }
                ensureCardCount(value);
            });
        }

        wrapper.addEventListener('click', function (event) {
            var removeButton = event.target.closest('[data-remove-traveler]');
            if (!removeButton) {
                return;
            }
            event.preventDefault();
            if (removeButton.disabled || removeButton.getAttribute('aria-disabled') === 'true') {
                return;
            }
            var card = removeButton.closest('[data-traveler]');
            if (card && wrapper.contains(card)) {
                wrapper.removeChild(card);
                renumberCards();
            }
        });

        var initial = parseInt(form.getAttribute('data-initial-travelers'), 10);
        if (Number.isNaN(initial)) {
            initial = min;
        }

        ensureCardCount(initial);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('.manaslu-checkout-form');
        forms.forEach(function (form) {
            initCheckoutForm(form);
        });
    });
})();
JS);

if (function_exists('wp_register_style')) {
    if (!wp_style_is($style_handle, 'registered')) {
        wp_register_style($style_handle, false, [], '1.0.0');
    }
    wp_enqueue_style($style_handle);
    wp_add_inline_style($style_handle, $inline_styles);
} else {
    printf('<style>%s</style>', $inline_styles); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

if (function_exists('wp_register_script')) {
    if (!wp_script_is($script_handle, 'registered')) {
        wp_register_script($script_handle, false, [], '1.0.0', true);
    }
    wp_enqueue_script($script_handle);
    wp_add_inline_script($script_handle, $inline_script);
} else {
    printf('<script>%s</script>', $inline_script); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

$cart          = WC()->cart;
$cart_items    = $cart ? $cart->get_cart() : [];
$primary_item  = !empty($cart_items) ? reset($cart_items) : null;
$trip_name     = '';
$trip_date     = '';
$person_count  = $initial_travelers;
$person_rows   = [];
$extras_rows   = [];
$seguro_block  = ['label' => '', 'total' => ''];
$discount_rows = [];
$cart_person_count = 0;

if ($primary_item && isset($primary_item['data']) && is_object($primary_item['data']) && method_exists($primary_item['data'], 'get_name')) {
    $trip_name = $primary_item['data']->get_name();
}

if (function_exists('manaslu_collect_summary_data')) {
    $summary_data = manaslu_collect_summary_data(0);
    if (!empty($summary_data['fecha']['label'])) {
        $trip_date = $summary_data['fecha']['label'];
    }
    if (isset($summary_data['personas_count'])) {
        $cart_person_count = max(0, (int) $summary_data['personas_count']);
    }
    if (!empty($summary_data['personas']) && is_array($summary_data['personas'])) {
        $person_rows = $summary_data['personas'];
    }
    if (!empty($summary_data['extras']) && is_array($summary_data['extras'])) {
        $extras_rows = $summary_data['extras'];
    }
    if (!empty($summary_data['seguro']) && is_array($summary_data['seguro'])) {
        $seguro_block = $summary_data['seguro'];
    }
    if (!empty($summary_data['cart_totals_raw']['discount_total'])) {
        $discount_rows[] = [
            'label' => __('Cupones aplicados', 'manaslu'),
            'value' => $summary_data['coupon_total'],
        ];
    }
    if (!empty($summary_data['pax_discount_raw'])) {
        $discount_rows[] = [
            'label' => __('Descuento por personas', 'manaslu'),
            'value' => $summary_data['pax_discount_total'],
        ];
    }
}

if ($cart_person_count > 0) {
    $initial_travelers = $cart_person_count;
    if ($initial_travelers < $min_travelers) {
        $initial_travelers = $min_travelers;
    }
}

$person_count = $cart_person_count > 0 ? $cart_person_count : $initial_travelers;
if ($initial_travelers > $max_travelers) {
    $max_travelers = $initial_travelers;
}

if (!function_exists('manaslu_checkout_order_button_text')) {
    function manaslu_checkout_order_button_text(): string {
        return __('Confirmar reserva', 'manaslu');
    }
}

?>

<form
    name="checkout"
    method="post"
    class="checkout woocommerce-checkout manaslu-checkout-form"
    action="<?php echo esc_url(wc_get_checkout_url()); ?>"
    enctype="multipart/form-data"
    novalidate
    data-initial-travelers="<?php echo esc_attr($initial_travelers); ?>"
    data-min-travelers="<?php echo esc_attr($min_travelers); ?>"
    data-max-travelers="<?php echo esc_attr($max_travelers); ?>"
>
    <?php wp_nonce_field('manaslu_checkout_form', 'manaslu_checkout_form_nonce'); ?>

    <div class="manaslu-checkout-grid">
        <div class="manaslu-checkout-primary">
            <?php do_action('woocommerce_checkout_before_customer_details'); ?>

            <section class="mvcf-section">
                <header class="mvcf-section-header">
                    <h2><?php echo esc_html__('Titular de la reserva', 'manaslu'); ?></h2>
                    <p><?php echo esc_html__('Completa los datos de contacto y facturación del titular.', 'manaslu'); ?></p>
                </header>
                <div class="mvcf-grid">
                    <?php foreach ($holder_fields as $key => $config) : ?>
                        <?php
                        $field_id   = 'mv_' . $key;
                        $field_name = $key;
                        $classes    = ['mvcf-field'];
                        if (!empty($config['full_width'])) {
                            $classes[] = 'is-full';
                        }
                        $current_value = $checkout->get_value($key);
                        if ($key === 'billing_country' && $current_value === '') {
                            $current_value = WC()->customer ? WC()->customer->get_billing_country() : 'ES';
                        }
                        ?>
                        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
                            <label class="mvcf-field-label" for="<?php echo esc_attr($field_id); ?>">
                                <?php echo esc_html($config['label']); ?>
                            </label>
                            <input
                                class="mvcf-input"
                                type="<?php echo esc_attr($config['type']); ?>"
                                id="<?php echo esc_attr($field_id); ?>"
                                name="<?php echo esc_attr($field_name); ?>"
                                value="<?php echo esc_attr(is_string($current_value) ? $current_value : ''); ?>"
                                <?php
                                if (!empty($config['autocomplete'])) {
                                    echo ' autocomplete="' . esc_attr($config['autocomplete']) . '"';
                                }
                                if (!empty($config['inputmode'])) {
                                    echo ' inputmode="' . esc_attr($config['inputmode']) . '"';
                                }
                                if (!empty($config['pattern'])) {
                                    echo ' pattern="' . esc_attr($config['pattern']) . '"';
                                }
                                if (!empty($config['placeholder'])) {
                                    echo ' placeholder="' . esc_attr($config['placeholder']) . '"';
                                }
                                if (!empty($config['required'])) {
                                    echo ' required';
                                }
                                ?>
                            />
                        </div>
                        <?php if (!$is_user_logged_in && $key === 'billing_email') : ?>
                            <div class="mvcf-field is-full mvcf-field--account-password">
                                <label class="mvcf-field-label" for="mv_account_password">
                                    <?php echo esc_html__('Clave para tu cuenta', 'manaslu'); ?>
                                </label>
                                <input
                                    class="mvcf-input"
                                    type="password"
                                    id="mv_account_password"
                                    name="mv_account_password"
                                    autocomplete="new-password"
                                    required
                                />
                                <p class="mvcf-field-note">
                                    <?php echo esc_html__('La usarás para revisar tus reservas y futuras compras.', 'manaslu'); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mvcf-section">
                <header class="mvcf-section-header">
                    <h2><?php echo esc_html__('Personas que viajan', 'manaslu'); ?></h2>
                    <p><?php echo esc_html__('Añade la información de cada acompañante para poder emitir la experiencia.', 'manaslu'); ?></p>
                </header>

                <div class="mvcf-traveler-count">
                    <span class="mvcf-field-label">
                        <?php echo esc_html__('Número de personas', 'manaslu'); ?>
                    </span>
                    <div class="mvcf-count-display">
                        <span class="mvcf-count-value"><?php echo esc_html($initial_travelers); ?></span>
                        <span class="mvcf-count-note"><?php echo esc_html__('Personas añadidas en tu reserva', 'manaslu'); ?></span>
                    </div>
                    <input
                        type="hidden"
                        id="mv_travelers_total"
                        name="mv_travelers_total"
                        value="<?php echo esc_attr($initial_travelers); ?>"
                        data-traveler-count
                    />
                </div>

                <div class="mvcf-travelers" data-travelers-wrapper>
                    <?php for ($i = 0; $i < $initial_travelers; $i++) : ?>
                        <?php echo manaslu_checkout_render_traveler_card($i, $traveler_fields, $traveler_title_pattern, $remove_title_pattern); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endfor; ?>
                </div>

                <template data-traveler-template>
                    <?php echo manaslu_checkout_render_traveler_card(0, $traveler_fields, $traveler_title_pattern, $remove_title_pattern, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </template>
            </section>

            <?php do_action('woocommerce_checkout_after_customer_details'); ?>
        </div>

        <aside class="manaslu-checkout-summary">
            <h2><?php echo esc_html__('Resumen del pedido', 'manaslu'); ?></h2>

            <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>
            <h3 id="order_review_heading" class="screen-reader-text">
                <?php esc_html_e('Tu pedido', 'woocommerce'); ?>
            </h3>

            <div class="mvcf-summary-card">
                <div class="mvcf-summary-block">
                    <div class="mvcf-summary-row">
                        <strong><?php echo esc_html__('Nombre del viaje', 'manaslu'); ?></strong>
                        <span><?php echo esc_html($trip_name); ?></span>
                    </div>
                </div>

                <?php if ($trip_date !== '') : ?>
                    <div class="mvcf-summary-block">
                        <div class="mvcf-summary-row">
                            <strong><?php echo esc_html__('Fecha del viaje', 'manaslu'); ?></strong>
                            <span><?php echo esc_html($trip_date); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mvcf-summary-block">
                    <div class="mvcf-summary-row">
                        <strong><?php echo esc_html__('Número de personas', 'manaslu'); ?></strong>
                        <span><?php echo esc_html($person_count); ?></span>
                    </div>
                    <?php if (!empty($person_rows)) : ?>
                        <ul class="mvcf-summary-list">
                            <?php foreach ($person_rows as $row) : ?>
                                <li>
                                    <span>
                                        <span><?php echo esc_html($row['title'] . ' × ' . $row['qty']); ?></span>
                                        <span><?php echo wp_kses_post($row['total']); ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="mvcf-summary-block">
                    <div class="mvcf-summary-row">
                        <strong><?php echo esc_html__('Extras añadidos', 'manaslu'); ?></strong>
                        <span><?php echo empty($extras_rows) ? esc_html__('Sin extras', 'manaslu') : ''; ?></span>
                    </div>
                    <?php if (!empty($extras_rows)) : ?>
                        <ul class="mvcf-summary-list">
                            <?php foreach ($extras_rows as $row) : ?>
                                <li>
                                    <span>
                                        <span><?php echo esc_html($row['title'] . ' × ' . $row['qty']); ?></span>
                                        <span><?php echo wp_kses_post($row['total']); ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="mvcf-summary-block">
                    <div class="mvcf-summary-row">
                        <strong><?php echo esc_html__('Descuentos', 'manaslu'); ?></strong>
                        <span><?php echo empty($discount_rows) ? esc_html__('Sin descuentos', 'manaslu') : ''; ?></span>
                    </div>
                    <?php if (!empty($discount_rows)) : ?>
                        <ul class="mvcf-summary-list">
                            <?php foreach ($discount_rows as $row) : ?>
                                <li>
                                    <span>
                                        <span><?php echo esc_html($row['label']); ?></span>
                                        <span><?php echo wp_kses_post($row['value']); ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="mvcf-summary-block">
                    <div class="mvcf-summary-row">
                        <strong><?php echo esc_html__('Seguros', 'manaslu'); ?></strong>
                        <span>
                            <?php
                            if (!empty($seguro_block['label'])) {
                                echo esc_html($seguro_block['label']);
                            } else {
                                echo esc_html__('Sin seguros', 'manaslu');
                            }
                            ?>
                        </span>
                    </div>
                    <?php if (!empty($seguro_block['label'])) : ?>
                        <ul class="mvcf-summary-list">
                            <li>
                                <span>
                                    <span><?php echo esc_html__('Total seguro', 'manaslu'); ?></span>
                                    <span><?php echo wp_kses_post($seguro_block['total']); ?></span>
                                </span>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div id="order_review" class="woocommerce-checkout-review-order">
                <?php do_action('woocommerce_checkout_before_order_review'); ?>
                <?php add_filter('woocommerce_order_button_text', 'manaslu_checkout_order_button_text', 10, 0); ?>
                <?php do_action('woocommerce_checkout_order_review'); ?>
                <?php remove_filter('woocommerce_order_button_text', 'manaslu_checkout_order_button_text', 10); ?>
                <?php do_action('woocommerce_checkout_after_order_review'); ?>
            </div>
        </aside>
    </div>
</form>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>
