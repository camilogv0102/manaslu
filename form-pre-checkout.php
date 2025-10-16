<?php
/**
 * Plugin Name: Manaslu – Registro Previo al Checkout
 * Description: Solicita el registro del usuario antes de continuar al checkout en el flujo "comprando viaje".
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mv_pre_checkout_should_activate')) {
    /**
     * Determina si la lógica debe activarse en la petición actual.
     */
    function mv_pre_checkout_should_activate(): bool
    {
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }

        if (is_user_logged_in()) {
            return false;
        }

        if (function_exists('is_checkout') && is_checkout()) {
            return false;
        }

        if (function_exists('mv_checkout_is_comprando_page') && mv_checkout_is_comprando_page()) {
            return true;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ($request_uri && strpos($request_uri, 'comprando-viaje') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('mv_pre_checkout_output_styles')) {
    add_action('wp_head', 'mv_pre_checkout_output_styles');
    function mv_pre_checkout_output_styles(): void
    {
        if (!mv_pre_checkout_should_activate()) {
            return;
        }
        ?>
        <style>
            .mv-pre-checkout-modal {
                position: fixed;
                inset: 0;
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 100000;
                font-family: "Host Grotesk", system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, "Helvetica Neue", Arial, sans-serif;
            }
            .mv-pre-checkout-modal.is-open {
                display: flex;
            }
            .mv-pre-checkout-modal__backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.55);
            }
            .mv-pre-checkout-modal__dialog {
                position: relative;
                background: #ffffff;
                border-radius: 16px;
                padding: 28px 24px;
                max-width: 420px;
                width: calc(100% - 32px);
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
                z-index: 1;
                color: #111;
            }
            .mv-pre-checkout-modal__title {
                margin: 0 0 8px;
                font-size: 1.35rem;
                font-weight: 700;
                letter-spacing: 0.015em;
            }
            .mv-pre-checkout-modal__lead {
                margin: 0 0 20px;
                font-size: 0.95rem;
                line-height: 1.45;
                opacity: 0.85;
            }
            .mv-pre-checkout-form {
                display: grid;
                gap: 14px;
            }
            .mv-pre-checkout-form label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
                font-size: 0.9rem;
            }
            .mv-pre-checkout-form input {
                width: 100%;
                border-radius: 10px;
                border: 1px solid rgba(0, 0, 0, 0.12);
                padding: 10px 12px;
                font-size: 1rem;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }
            .mv-pre-checkout-form input:focus {
                border-color: #21a65b;
                box-shadow: 0 0 0 3px rgba(33, 166, 91, 0.25);
                outline: none;
            }
            .mv-pre-checkout-submit {
                background: #21a65b;
                color: #ffffff;
                font-weight: 700;
                border: none;
                border-radius: 999px;
                padding: 12px 20px;
                cursor: pointer;
                font-size: 1rem;
                transition: background 0.2s ease, transform 0.2s ease;
            }
            .mv-pre-checkout-submit:hover {
                background: #1b8a4c;
                transform: translateY(-1px);
            }
            .mv-pre-checkout-submit:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }
            .mv-pre-checkout-close {
                position: absolute;
                top: 12px;
                right: 12px;
                background: transparent;
                border: none;
                font-size: 1.5rem;
                line-height: 1;
                cursor: pointer;
                color: #666;
            }
            .mv-pre-checkout-feedback {
                font-size: 0.9rem;
                border-radius: 10px;
                padding: 10px 12px;
                display: none;
            }
            .mv-pre-checkout-feedback.is-error {
                background: rgba(227, 23, 45, 0.12);
                color: #c81d30;
            }
            .mv-pre-checkout-feedback.is-success {
                background: rgba(33, 166, 91, 0.12);
                color: #1b8a4c;
            }
            .mv-pre-checkout-extra {
                font-size: 0.85rem;
                opacity: 0.8;
                text-align: center;
            }
            .mv-pre-checkout-extra a {
                color: #21a65b;
                font-weight: 600;
                text-decoration: none;
            }
            .mv-pre-checkout-extra a:hover {
                text-decoration: underline;
            }
            body.mv-pre-checkout-open {
                overflow: hidden;
            }
        </style>
        <?php
    }
}

if (!function_exists('mv_pre_checkout_output_modal')) {
    add_action('wp_footer', 'mv_pre_checkout_output_modal', 20);
    function mv_pre_checkout_output_modal(): void
    {
        if (!mv_pre_checkout_should_activate()) {
            return;
        }

        $login_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
        $nonce        = wp_create_nonce('mv_pre_checkout');

        $settings = [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => $nonce,
            'checkoutUrl' => $checkout_url,
            'loginUrl'    => $login_url,
            'isLoggedIn'  => is_user_logged_in(),
            'messages'    => [
                'required'     => __('Por favor completa todos los campos.', 'manaslu'),
                'invalidEmail' => __('Introduce un correo electrónico válido.', 'manaslu'),
                'weakPassword' => __('La clave debe tener al menos 6 caracteres.', 'manaslu'),
                'genericError' => __('No se pudo crear la cuenta. Intenta nuevamente.', 'manaslu'),
                'loading'      => __('Creando cuenta…', 'manaslu'),
                'success'      => __('Cuenta creada correctamente. Redirigiendo…', 'manaslu'),
            ],
        ];

        $settings_json = wp_json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $login_hint = sprintf(
            /* translators: %s: my account url */
            __('¿Ya tienes cuenta? <a href="%s">Inicia sesión</a>.', 'manaslu'),
            esc_url($login_url)
        );

        $login_hint = wp_kses($login_hint, ['a' => ['href' => []]]);
        ?>
        <div
            id="mv-pre-checkout-modal"
            class="mv-pre-checkout-modal"
            role="dialog"
            aria-modal="true"
            aria-hidden="true"
            aria-labelledby="mv-pre-checkout-title"
        >
            <div class="mv-pre-checkout-modal__backdrop" data-mv-pre-close></div>
            <div class="mv-pre-checkout-modal__dialog">
                <button type="button" class="mv-pre-checkout-close" data-mv-pre-close aria-label="<?php echo esc_attr__('Cerrar', 'manaslu'); ?>">&times;</button>
                <h2 id="mv-pre-checkout-title" class="mv-pre-checkout-modal__title"><?php echo esc_html__('Crea tu cuenta para continuar', 'manaslu'); ?></h2>
                <p class="mv-pre-checkout-modal__lead"><?php echo esc_html__('Necesitamos tu registro para finalizar la reserva del viaje.', 'manaslu'); ?></p>
                <div class="mv-pre-checkout-feedback is-error" data-mv-pre-error></div>
                <div class="mv-pre-checkout-feedback is-success" data-mv-pre-success></div>
                <form class="mv-pre-checkout-form" data-mv-pre-form novalidate>
                    <div>
                        <label for="mv-pre-checkout-name"><?php echo esc_html__('Nombre completo', 'manaslu'); ?></label>
                        <input type="text" id="mv-pre-checkout-name" name="name" autocomplete="name" required />
                    </div>
                    <div>
                        <label for="mv-pre-checkout-email"><?php echo esc_html__('Correo electrónico', 'manaslu'); ?></label>
                        <input type="email" id="mv-pre-checkout-email" name="email" autocomplete="email" required />
                    </div>
                    <div>
                        <label for="mv-pre-checkout-password"><?php echo esc_html__('Clave', 'manaslu'); ?></label>
                        <input type="password" id="mv-pre-checkout-password" name="password" autocomplete="new-password" required minlength="6" />
                    </div>
                    <button type="submit" class="mv-pre-checkout-submit" data-mv-pre-submit>
                        <?php echo esc_html__('Crear cuenta y continuar', 'manaslu'); ?>
                    </button>
                </form>
                <p class="mv-pre-checkout-extra"><?php echo $login_hint; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
            </div>
        </div>
        <script>
            window.mvPreCheckoutSettings = <?php echo $settings_json; ?>;
        </script>
        <script>
            (function(){
                var settings = window.mvPreCheckoutSettings || {};
                if (settings.isLoggedIn) {
                    return;
                }

                var modal = document.getElementById('mv-pre-checkout-modal');
                if (!modal) {
                    return;
                }

                var form = modal.querySelector('[data-mv-pre-form]');
                var submitBtn = modal.querySelector('[data-mv-pre-submit]');
                var errorBox = modal.querySelector('[data-mv-pre-error]');
                var successBox = modal.querySelector('[data-mv-pre-success]');
                var closeNodes = modal.querySelectorAll('[data-mv-pre-close]');
                var isOpen = false;
                var inflight = false;
                var resumeAction = null;

                function cleanUrl(url) {
                    if (!url) {
                        return '';
                    }
                    try {
                        var anchor = document.createElement('a');
                        anchor.href = url;
                        var normalized = anchor.href.split('#')[0].split('?')[0];
                        if (normalized.slice(-1) === '/') {
                            normalized = normalized.slice(0, -1);
                        }
                        return normalized;
                    } catch (e) {
                        return url;
                    }
                }

                var checkoutTarget = cleanUrl(settings.checkoutUrl || '');

                function isCheckoutIntent(url) {
                    var normalized = cleanUrl(url);
                    if (!normalized) {
                        return false;
                    }
                    if (checkoutTarget && normalized.indexOf(checkoutTarget) === 0) {
                        return true;
                    }
                    return normalized.indexOf('/checkout') !== -1;
                }

                function insideModal(node) {
                    return !!(node && modal.contains(node));
                }

                function resetFeedback() {
                    if (errorBox) {
                        errorBox.style.display = 'none';
                        errorBox.innerHTML = '';
                    }
                    if (successBox) {
                        successBox.style.display = 'none';
                        successBox.textContent = '';
                    }
                }

                function showError(message) {
                    if (!errorBox) {
                        return;
                    }
                    errorBox.innerHTML = message || settings.messages.genericError || '';
                    errorBox.style.display = 'block';
                    if (successBox) {
                        successBox.style.display = 'none';
                    }
                }

                function showSuccess(message) {
                    if (!successBox) {
                        return;
                    }
                    successBox.textContent = message || '';
                    successBox.style.display = message ? 'block' : 'none';
                    if (errorBox) {
                        errorBox.style.display = 'none';
                    }
                }

                function openModal(callback) {
                    if (isOpen) {
                        resumeAction = callback || null;
                        return;
                    }
                    resetFeedback();
                    resumeAction = callback || null;
                    modal.classList.add('is-open');
                    document.body.classList.add('mv-pre-checkout-open');
                    modal.setAttribute('aria-hidden', 'false');
                    isOpen = true;
                    var firstInput = modal.querySelector('#mv-pre-checkout-name');
                    if (firstInput) {
                        setTimeout(function(){ firstInput.focus(); }, 50);
                    }
                }

                function closeModal() {
                    if (!isOpen) {
                        resumeAction = null;
                        return;
                    }
                    modal.classList.remove('is-open');
                    document.body.classList.remove('mv-pre-checkout-open');
                    modal.setAttribute('aria-hidden', 'true');
                    isOpen = false;
                    resumeAction = null;
                }

                closeNodes.forEach(function(node){
                    node.addEventListener('click', function(event){
                        event.preventDefault();
                        closeModal();
                    });
                });

                modal.addEventListener('click', function(event){
                    if (event.target === modal || event.target.classList.contains('mv-pre-checkout-modal__backdrop')) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function(event){
                    if (event.key === 'Escape' && isOpen) {
                        closeModal();
                    }
                });

                function finishRequest() {
                    inflight = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        if (submitBtn.dataset.originalText) {
                            submitBtn.textContent = submitBtn.dataset.originalText;
                        }
                    }
                }

                function handleSuccess(redirectUrl) {
                    showSuccess(settings.messages.success || '');
                    settings.isLoggedIn = true;
                    window.mvPreCheckoutSettings = settings;
                    setTimeout(function(){
                        closeModal();
                        var action = resumeAction;
                        resumeAction = null;
                        if (typeof action === 'function') {
                            action();
                        } else if (redirectUrl) {
                            window.location.href = redirectUrl;
                        } else if (settings.checkoutUrl) {
                            window.location.href = settings.checkoutUrl;
                        }
                    }, 350);
                }

                if (form) {
                    form.addEventListener('submit', function(event){
                        event.preventDefault();
                        if (inflight) {
                            return;
                        }
                        resetFeedback();

                        var formData = new FormData(form);
                        var name = (formData.get('name') || '').toString().trim();
                        var email = (formData.get('email') || '').toString().trim();
                        var password = (formData.get('password') || '').toString();

                        if (!name || !email || !password) {
                            showError(settings.messages.required || '');
                            return;
                        }
                        var atIndex = email.indexOf('@');
                        var dotIndex = email.lastIndexOf('.');
                        var isEmailValid = atIndex > 0 && dotIndex > atIndex + 1 && dotIndex < email.length - 1;
                        if (!isEmailValid) {
                            showError(settings.messages.invalidEmail || '');
                            return;
                        }
                        if (password.length < 6) {
                            showError(settings.messages.weakPassword || '');
                            return;
                        }

                        inflight = true;
                        if (submitBtn) {
                            submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent;
                            submitBtn.disabled = true;
                            submitBtn.textContent = settings.messages.loading || submitBtn.textContent;
                        }

                        var payload = new URLSearchParams();
                        payload.append('action', 'mv_pre_checkout_register');
                        payload.append('nonce', settings.nonce || '');
                        payload.append('name', name);
                        payload.append('email', email);
                        payload.append('password', password);

                        fetch(settings.ajaxUrl || '', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: payload.toString()
                        })
                            .then(function(response){
                                if (!response.ok) {
                                    throw new Error('http-error');
                                }
                                return response.json();
                            })
                            .then(function(body){
                                if (body && body.success) {
                                    var redirectUrl = body.data && body.data.redirect ? body.data.redirect : '';
                                    finishRequest();
                                    handleSuccess(redirectUrl);
                                    return;
                                }
                                var message = body && body.data && body.data.message ? body.data.message : (settings.messages.genericError || '');
                                finishRequest();
                                showError(message);
                            })
                            .catch(function(){
                                finishRequest();
                                showError(settings.messages.genericError || '');
                            });
                    });
                }

                function intercept(event, callback) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (event.stopImmediatePropagation) {
                        event.stopImmediatePropagation();
                    }
                    openModal(callback);
                }

                function shouldBypass(node) {
                    return !!(node && (node.hasAttribute('data-mv-pre-checkout-bypass') || insideModal(node)));
                }

                document.addEventListener('click', function(event){
                    var target = event.target;
                    if (!target) {
                        return;
                    }

                    var anchor = target.closest ? target.closest('a') : null;
                    if (anchor && !shouldBypass(anchor)) {
                        var href = anchor.getAttribute('href');
                        if (href && href !== '#' && isCheckoutIntent(href)) {
                            var finalUrl = anchor.href;
                            intercept(event, function(){
                                window.location.href = finalUrl;
                            });
                            return;
                        }
                    }

                    var button = target.closest ? target.closest("button, input[type='submit']") : null;
                    if (button && !shouldBypass(button)) {
                        if (insideModal(button)) {
                            return;
                        }
                        var datasetUrl = button.getAttribute('data-checkout-url') || button.getAttribute('data-href') || button.getAttribute('data-url');
                        if (datasetUrl && isCheckoutIntent(datasetUrl)) {
                            intercept(event, function(){
                                window.location.href = datasetUrl;
                            });
                            return;
                        }
                        var buttonType = (button.getAttribute('type') || '').toLowerCase();
                        if (buttonType === 'submit') {
                            var formEl = button.form;
                            if (formEl && !shouldBypass(formEl) && isCheckoutIntent(formEl.getAttribute('action') || '')) {
                                intercept(event, function(){
                                    formEl.setAttribute('data-mv-pre-checkout-bypass', '1');
                                    formEl.submit();
                                });
                            }
                        }
                    }
                }, true);

                document.addEventListener('submit', function(event){
                    var formEl = event.target;
                    if (!(formEl instanceof HTMLFormElement)) {
                        return;
                    }
                    if (shouldBypass(formEl)) {
                        return;
                    }
                    if (insideModal(formEl)) {
                        return;
                    }
                    if (isCheckoutIntent(formEl.getAttribute('action') || '')) {
                        intercept(event, function(){
                            formEl.setAttribute('data-mv-pre-checkout-bypass', '1');
                            formEl.submit();
                        });
                    }
                }, true);

                window.mvPreCheckoutOpen = openModal;
            })();
        </script>
        <?php
    }
}

if (!function_exists('mv_pre_checkout_handle_registration')) {
    add_action('wp_ajax_nopriv_mv_pre_checkout_register', 'mv_pre_checkout_handle_registration');
    add_action('wp_ajax_mv_pre_checkout_register', 'mv_pre_checkout_handle_registration');

    function mv_pre_checkout_handle_registration(): void
    {
        if (is_user_logged_in()) {
            $redirect = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
            wp_send_json_success([
                'already_logged_in' => true,
                'redirect'          => $redirect,
            ]);
        }

        check_ajax_referer('mv_pre_checkout', 'nonce');

        $raw_name  = isset($_POST['name']) ? wp_unslash($_POST['name']) : '';
        $raw_email = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $raw_pass  = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        $name     = sanitize_text_field($raw_name);
        $email    = sanitize_email($raw_email);
        $password = (string) $raw_pass;

        if ($name === '' || $email === '' || $password === '') {
            wp_send_json_error([
                'message' => __('Por favor completa todos los campos.', 'manaslu'),
            ]);
        }

        if (!is_email($email)) {
            wp_send_json_error([
                'message' => __('Introduce un correo electrónico válido.', 'manaslu'),
            ]);
        }

        if (strlen($password) < 6) {
            wp_send_json_error([
                'message' => __('La clave debe tener al menos 6 caracteres.', 'manaslu'),
            ]);
        }

        if (email_exists($email)) {
            $login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
            $message   = sprintf(
                /* translators: %s: login url */
                __('Ya existe una cuenta con ese correo. <a href="%s">Inicia sesión</a> para continuar.', 'manaslu'),
                esc_url($login_url)
            );

            wp_send_json_error([
                'message' => wp_kses($message, ['a' => ['href' => []]]),
                'code'    => 'email_exists',
            ]);
        }

        $first_name = $name;
        $last_name  = '';
        $parts      = preg_split('/\s+/', $name, 2);
        if (is_array($parts) && !empty($parts[0])) {
            $first_name = $parts[0];
            $last_name  = $parts[1] ?? '';
        }

        $user_id = 0;
        $user_error = null;

        if (function_exists('wc_create_new_customer')) {
            $user_id = wc_create_new_customer($email, '', $password, [
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => $name,
            ]);
            if (is_wp_error($user_id)) {
                $user_error = $user_id;
            }
        } else {
            $username = sanitize_user(current(explode('@', $email)) ?: $email, true);
            $base_username = $username;
            $suffix = 1;
            while (username_exists($username)) {
                $username = $base_username . $suffix;
                $suffix++;
            }
            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                $user_error = $user_id;
            }
        }

        if ($user_error instanceof WP_Error) {
            $message = $user_error->get_error_message();
            if (!$message) {
                $message = __('No se pudo crear la cuenta. Intenta nuevamente.', 'manaslu');
            }
            wp_send_json_error([
                'message' => esc_html($message),
            ]);
        }

        if (!$user_id || !is_int($user_id)) {
            wp_send_json_error([
                'message' => __('No se pudo crear la cuenta. Intenta nuevamente.', 'manaslu'),
            ]);
        }

        wp_update_user([
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $name,
        ]);

        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);

        if (function_exists('wc_set_customer_auth_cookie')) {
            wc_set_customer_auth_cookie($user_id);
        } else {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        do_action('mv_pre_checkout_user_registered', $user_id);

        $redirect = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');

        wp_send_json_success([
            'redirect' => $redirect,
        ]);
    }
}
