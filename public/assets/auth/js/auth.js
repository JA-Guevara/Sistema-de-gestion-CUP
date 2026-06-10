(function () {
    'use strict';

    const MIN_PASSWORD_LENGTH = 8;

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
        const requiredFields = Array.prototype.slice.call(form.querySelectorAll('[required]'));
        const emailField = form.querySelector('input[name="email"]');
        const passwordField = form.querySelector('input[name="password"]');
        const confirmationField = form.querySelector('input[name="passwordConfirmation"]');

        requiredFields.forEach(function (field) {
            if (String(field.value || '').trim() === '') {
                errors.push({ field: field, message: 'Este campo es obligatorio.' });
            }
        });

        if (emailField && emailField.value.trim() !== '' && !isValidEmail(emailField.value)) {
            errors.push({ field: emailField, message: 'Ingresa un correo valido.' });
        }

        if (passwordField && passwordField.hasAttribute('data-strong-password') && passwordField.value.trim() !== '') {
            const passwordError = passwordStrengthError(passwordField.value);
            if (passwordError !== null) {
                errors.push({ field: passwordField, message: passwordError });
            }
        }

        if (passwordField && confirmationField && confirmationField.value !== '' && passwordField.value !== confirmationField.value) {
            errors.push({ field: confirmationField, message: 'Las contrasenas no coinciden.' });
        }

        return dedupeErrors(errors);
    }

    function passwordStrengthError(value) {
        if (value.length < MIN_PASSWORD_LENGTH) {
            return 'La contrasena debe tener al menos ' + MIN_PASSWORD_LENGTH + ' caracteres.';
        }

        if (!/[a-z]/.test(value)) {
            return 'La contrasena debe incluir una letra minuscula.';
        }

        if (!/[A-Z]/.test(value)) {
            return 'La contrasena debe incluir una letra mayuscula.';
        }

        if (!/\d/.test(value)) {
            return 'La contrasena debe incluir un numero.';
        }

        if (!/[^A-Za-z0-9]/.test(value)) {
            return 'La contrasena debe incluir un simbolo.';
        }

        return null;
    }

    function dedupeErrors(errors) {
        const seen = new Set();

        return errors.filter(function (error) {
            if (seen.has(error.field)) {
                return false;
            }

            seen.add(error.field);
            return true;
        });
    }

    function isValidEmail(value) {
        if (typeof value !== 'string') {
            return false;
        }

        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
    }

    function renderFieldError(field, message) {
        const hint = document.createElement('p');
        hint.className = 'auth-form__hint auth-form__hint--error';
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

    function bindPasswordToggle(button) {
        const field = button.closest('.auth-password-field');
        const input = field ? field.querySelector('input[type="password"], input[type="text"]') : null;

        if (!input) {
            return;
        }

        button.addEventListener('click', function () {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.setAttribute('aria-label', shouldShow ? 'Ocultar contrasena' : 'Mostrar contrasena');
            button.setAttribute('title', shouldShow ? 'Ocultar contrasena' : 'Mostrar contrasena');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-auth-form]').forEach(bindAuthForm);
        document.querySelectorAll('[data-password-toggle]').forEach(bindPasswordToggle);
    });
}());
