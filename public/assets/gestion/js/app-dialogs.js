/* =========================================================================
   Diálogos de la app (estilo propio, NO los nativos del navegador).
   - Confirmaciones: cualquier <form> o <a> con atributo data-confirm="mensaje".
   - Avisos: window.appAlert('mensaje').
   Se carga globalmente desde app_base, así todos los módulos lo heredan.
   ========================================================================= */
(function () {
    var overlay = null;
    var titleEl, msgEl, okBtn, cancelBtn, footEl;
    var onConfirm = null;

    function build() {
        if (overlay) {
            return;
        }
        overlay = document.createElement('div');
        overlay.className = 'ds-modal';
        overlay.hidden = true;
        overlay.innerHTML =
            '<div class="ds-modal__card" role="dialog" aria-modal="true">' +
            '  <div class="ds-modal__strip"></div>' +
            '  <div class="ds-modal__body">' +
            '    <h3 class="ds-modal__title" data-ds-title>Confirmar</h3>' +
            '    <p class="ds-modal__msg" data-ds-msg></p>' +
            '  </div>' +
            '  <div class="ds-modal__foot" data-ds-foot>' +
            '    <button type="button" class="gestion-button" data-ds-cancel>Cancelar</button>' +
            '    <button type="button" class="gestion-button gestion-button--danger" data-ds-ok>Confirmar</button>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(overlay);

        titleEl = overlay.querySelector('[data-ds-title]');
        msgEl = overlay.querySelector('[data-ds-msg]');
        okBtn = overlay.querySelector('[data-ds-ok]');
        cancelBtn = overlay.querySelector('[data-ds-cancel]');
        footEl = overlay.querySelector('[data-ds-foot]');

        okBtn.addEventListener('click', function () {
            var cb = onConfirm;
            close();
            if (typeof cb === 'function') { cb(); }
        });
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !overlay.hidden) { close(); }
        });
    }

    function close() {
        onConfirm = null;
        if (overlay) { overlay.hidden = true; }
    }

    function openConfirm(opts) {
        build();
        titleEl.textContent = opts.title || 'Confirmar acción';
        msgEl.textContent = opts.message || '¿Deseás continuar?';
        cancelBtn.style.display = '';
        okBtn.textContent = opts.okText || 'Confirmar';
        okBtn.className = 'gestion-button ' + (opts.variant === 'primary' ? 'gestion-button--primary' : 'gestion-button--danger');
        onConfirm = opts.onConfirm || null;
        overlay.hidden = false;
        okBtn.focus();
    }

    // Aviso simple (un solo botón).
    window.appAlert = function (message, title) {
        build();
        titleEl.textContent = title || 'Aviso';
        msgEl.textContent = message || '';
        cancelBtn.style.display = 'none';
        okBtn.textContent = 'Entendido';
        okBtn.className = 'gestion-button gestion-button--primary';
        onConfirm = null;
        overlay.hidden = false;
        okBtn.focus();
    };

    window.appConfirm = openConfirm;

    // Intercepta formularios con data-confirm (en el <form> o en el boton que envia).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute) {
            return;
        }
        if (form.dataset.dsConfirmed === '1') {
            return; // ya confirmado, dejar pasar
        }
        var submitter = e.submitter || null; // preserva el boton (p.ej. formaction de acciones masivas)
        // El mensaje del boton tiene prioridad sobre el del form: permite
        // confirmaciones distintas por boton en un mismo form (Aprobar/Rechazar).
        var message = (submitter && submitter.getAttribute ? submitter.getAttribute('data-confirm') : null)
            || (form.hasAttribute('data-confirm') ? form.getAttribute('data-confirm') : null);
        if (!message) {
            return;
        }
        e.preventDefault();
        openConfirm({
            message: message,
            onConfirm: function () {
                form.dataset.dsConfirmed = '1';
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(submitter); } else { form.submit(); }
            }
        });
    }, true);

    // Intercepta enlaces con data-confirm.
    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a[data-confirm]') : null;
        if (!link) {
            return;
        }
        e.preventDefault();
        openConfirm({
            message: link.getAttribute('data-confirm'),
            onConfirm: function () { window.location.href = link.href; }
        });
    }, true);

    window.addEventListener('DOMContentLoaded', build);
}());
