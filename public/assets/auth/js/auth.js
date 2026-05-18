(function () {
    'use strict';

    const MIN_PASSWORD_LENGTH = 6;

    function bindAuthForm(form) {
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            clearErrors(form);
            const errors = collectErrors(form);

            if (errors.length === 0) {
                return;
            }

            event.preventDefault();
            errors.forEach(function (error) {
                renderFieldError(error.field, error.message);
            });
            errors[0].field.focus();
        });
    }

    function collectErrors(form) {
        const errors = [];
        const emailField = form.querySelector('input[name="email"]');
        const nameField = form.querySelector('input[name="name"]');
        const passwordField = form.querySelector('input[name="password"]');

        if (emailField && !isValidEmail(emailField.value)) {
            errors.push({ field: emailField, message: 'Ingresá un correo válido.' });
        }

        if (nameField && nameField.value.trim() === '') {
            errors.push({ field: nameField, message: 'El nombre es obligatorio.' });
        }

        if (passwordField && passwordField.value.length < MIN_PASSWORD_LENGTH) {
            errors.push({
                field: passwordField,
                message: 'La contraseña debe tener al menos ' + MIN_PASSWORD_LENGTH + ' caracteres.',
            });
        }

        return errors;
    }

    function isValidEmail(value) {
        if (typeof value !== 'string') {
            return false;
        }

        // Pragmatic regex: matches the vast majority of real-world emails
        // without trying to be RFC-perfect. Server validates definitively.
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
    }

    function renderFieldError(field, message) {
        const hint = document.createElement('p');
        hint.className = 'auth-form__hint auth-form__hint--error';
        hint.style.color = 'var(--color-error-text)';
        hint.textContent = message;
        hint.dataset.authError = 'true';

        const parent = field.closest('.auth-form__field') || field.parentElement;
        if (parent) {
            parent.appendChild(hint);
        }
    }

    function clearErrors(form) {
        form.querySelectorAll('[data-auth-error="true"]').forEach(function (node) {
            node.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-auth-form]').forEach(bindAuthForm);
    });
})();
