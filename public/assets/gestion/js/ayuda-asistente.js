/* =========================================================================
   Asistente de AYUDA global — chat flotante de onboarding presente en todas las
   paginas (excepto el Dashboard, que tiene su propio asistente de reportes).
   Posta al endpoint ayuda_asistente, que llama a la IA con un prompt que conoce
   los modulos del sistema. Solo explica como usar el sistema (texto plano).
   ========================================================================= */
(function () {
    var cfgEl = document.querySelector('[data-ayuda-config]');
    var launcher = document.querySelector('[data-ayuda-launcher]');
    var panel = document.querySelector('[data-ayuda-panel]');
    var messages = document.querySelector('[data-ayuda-messages]');
    var input = document.querySelector('[data-ayuda-input]');
    var sendBtn = document.querySelector('[data-ayuda-send]');
    var closeBtn = document.querySelector('[data-ayuda-close]');
    if (!cfgEl || !launcher || !panel || !messages) { return; }

    var cfg = safeJson(cfgEl.textContent, {});
    if (!cfg.url) { return; }
    var busy = false;

    function safeJson(t, fb) { try { return JSON.parse(t || ''); } catch (e) { return fb; } }
    function esc(s) { s = s == null ? '' : String(s); return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    function open() { panel.hidden = false; launcher.setAttribute('aria-expanded', 'true'); if (input) { input.focus(); } }
    function close() { panel.hidden = true; launcher.setAttribute('aria-expanded', 'false'); }

    launcher.addEventListener('click', function () { if (panel.hidden) { open(); } else { close(); } });
    if (closeBtn) { closeBtn.addEventListener('click', close); }

    function addMsg(text, who) {
        var div = document.createElement('div');
        div.className = 'ayuda-msg ayuda-msg--' + who;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    function send(text) {
        text = (text || '').trim();
        if (!text || busy) { return; }
        addMsg(text, 'user');
        if (input) { input.value = ''; }
        busy = true;
        var thinking = addMsg('…', 'bot');
        thinking.classList.add('ayuda-msg--thinking');

        var form = new FormData();
        form.append('consulta', text);

        fetch(cfg.url, { method: 'POST', body: form, headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                thinking.classList.remove('ayuda-msg--thinking');
                thinking.textContent = (res && res.respuesta) || 'Listo.';
            })
            .catch(function () {
                thinking.classList.remove('ayuda-msg--thinking');
                thinking.textContent = 'No pude responder en este momento. Probá de nuevo.';
            })
            .then(function () { busy = false; messages.scrollTop = messages.scrollHeight; });
    }

    if (sendBtn) { sendBtn.addEventListener('click', function () { send(input ? input.value : ''); }); }
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); send(input.value); }
        });
    }

    // Preguntas sugeridas (delegacion: los botones se renderizan en el saludo).
    messages.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ayuda-q]');
        if (b) { send(b.getAttribute('data-ayuda-q')); }
    });
}());
