/* =========================================================================
   Asistente de IA del Dashboard — un chat flotante que interpreta lenguaje
   natural (texto o voz) y aplica los filtros de los reportes. Habla con el
   endpoint dashboard_asistente (que llama a Claude con salida estructurada) y
   luego maneja los controles existentes del tablero disparando sus eventos
   (queda 100% desacoplado de dashboard-reportes.js).
   ========================================================================= */
(function () {
    var cfgEl = document.querySelector('[data-rep-config]');
    var payloadEl = document.querySelector('[data-rep-payload]');
    if (!cfgEl || !payloadEl) { return; }

    var cfg = safeJson(cfgEl.textContent, {});
    var meta = (safeJson(payloadEl.textContent, {}) || {}).meta || {};
    if (!cfg.asistenteUrl || !meta.gestionId) { return; }

    var launcher = document.querySelector('[data-asis-launcher]');
    var panel = document.querySelector('[data-asis-panel]');
    var messages = document.querySelector('[data-asis-messages]');
    var input = document.querySelector('[data-asis-input]');
    var sendBtn = document.querySelector('[data-asis-send]');
    var micBtn = document.querySelector('[data-asis-mic]');
    var closeBtn = document.querySelector('[data-asis-close]');
    if (!launcher || !panel || !messages) { return; }

    var busy = false;

    function safeJson(t, fb) { try { return JSON.parse(t || ''); } catch (e) { return fb; } }
    function esc(s) { s = s == null ? '' : String(s); return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function cap(s) { s = String(s || '').toLowerCase(); return s.charAt(0).toUpperCase() + s.slice(1); }
    var ESTADO_INSCRIPCION_LABEL = {
        CONFIRMADA: 'Aprobada',
        COMPLETADA: 'Completada',
        VALIDADA: 'En pago/entrevista',
        PRESENTADA: 'En revision',
        PENDIENTE: 'Pendiente',
        BORRADOR: 'Borrador',
        RECHAZADA: 'Rechazada',
        ANULADA: 'Anulada',
    };

    function open() { panel.hidden = false; launcher.setAttribute('aria-expanded', 'true'); if (input) { input.focus(); } }
    function close() { panel.hidden = true; launcher.setAttribute('aria-expanded', 'false'); }

    launcher.addEventListener('click', function () { if (panel.hidden) { open(); } else { close(); } });
    if (closeBtn) { closeBtn.addEventListener('click', close); }

    function addMsg(text, who) {
        var div = document.createElement('div');
        div.className = 'asis-msg asis-msg--' + who;
        div.innerHTML = esc(text);
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
        thinking.classList.add('asis-msg--thinking');

        var form = new FormData();
        form.append('consulta', text);

        fetch(cfg.asistenteUrl + '?gestion=' + encodeURIComponent(meta.gestionId), {
            method: 'POST', body: form, headers: { 'X-Requested-With': 'fetch' },
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                thinking.classList.remove('asis-msg--thinking');
                thinking.innerHTML = esc(res.respuesta || 'Listo.');
                if (res && res.ok) { applyFilters(res); addChips(res); }
            })
            .catch(function () {
                thinking.classList.remove('asis-msg--thinking');
                thinking.innerHTML = 'No pude responder en este momento. Probá de nuevo.';
            })
            .then(function () { busy = false; });
    }

    if (sendBtn) { sendBtn.addEventListener('click', function () { send(input ? input.value : ''); }); }
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); send(input.value); }
        });
    }

    // ---- Aplicar filtros manejando los controles existentes del tablero ----
    function setVal(form, field, val) {
        var el = form.querySelector('[data-field="' + field + '"]');
        if (el && el.value !== val) { el.value = val; }
    }

    function setAssistantFlags(res) {
        window.__repAssistantFlags = {
            soloOficiales: !!(res && res.soloOficiales),
            soloAsignados: !!(res && res.soloAsignados),
            soloSinAsignados: !!(res && res.soloSinAsignados),
            estadoPostulacion: String((res && res.estadoPostulacion) || ''),
        };
        window.dispatchEvent(new CustomEvent('rep:assistant-flags-changed', { detail: window.__repAssistantFlags }));
    }

    function applyFilters(res) {
        var tab = res.vista === 'reportes' ? 'charts' : 'list';
        var tabBtn = document.querySelector('[data-rep-tab="' + tab + '"]');
        if (tabBtn) { tabBtn.click(); }

        setAssistantFlags(res);

        var form = document.querySelector('[data-rep-form="' + tab + '"]');
        if (!form) { return; }

        setVal(form, 'carrera', res.carrera ? String(res.carrera) : '');
        setVal(form, 'materia', res.materia ? String(res.materia) : '');
        setVal(form, 'docente', res.docente ? String(res.docente) : '');
        if (tab === 'list') {
            setVal(form, 'estado', res.estado || '');
            setVal(form, 'texto', res.texto || '');
        }

        // Un solo refetch: dispara "change" en un campo de servidor (relee todo).
        var trigger = form.querySelector('[data-field="carrera"]') || form.querySelector('[data-field]');
        if (trigger) { trigger.dispatchEvent(new Event('change', { bubbles: true })); }
    }

    // ---- Chips de confirmación (resuelve etiquetas desde los selects) ----
    function chip(form, field, id) {
        if (!form || !id) { return null; }
        var el = form.querySelector('[data-field="' + field + '"]');
        if (!el) { return null; }
        var opt = el.querySelector('option[value="' + id + '"]');
        return opt ? opt.textContent : null;
    }

    function addChips(res) {
        var form = document.querySelector('[data-rep-form="list"]');
        var parts = [];
        var c = chip(form, 'carrera', res.carrera); if (c) { parts.push({ t: c }); }
        var m = chip(form, 'materia', res.materia); if (m) { parts.push({ t: m }); }
        var d = chip(form, 'docente', res.docente); if (d) { parts.push({ t: d }); }
        if (res.estado) { parts.push({ t: cap(res.estado), cls: 'estado' }); }
        if (res.estadoPostulacion) { parts.push({ t: ESTADO_INSCRIPCION_LABEL[res.estadoPostulacion] || cap(res.estadoPostulacion) }); }
        if (res.soloOficiales) { parts.push({ t: 'Solo oficiales' }); }
        if (res.soloAsignados) { parts.push({ t: 'Solo con materias asignadas' }); }
        if (res.soloSinAsignados) { parts.push({ t: 'Solo sin materias asignadas' }); }
        if (res.texto) { parts.push({ t: '“' + res.texto + '”' }); }
        if (!parts.length) { return; }

        var wrap = document.createElement('div');
        wrap.className = 'asis-chips';
        parts.forEach(function (p) {
            var s = document.createElement('span');
            s.className = 'asis-chip' + (p.cls ? ' asis-chip--' + p.cls : '');
            s.textContent = p.t;
            wrap.appendChild(s);
        });
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    }

    // ---- Voz (Web Speech API) ----
    var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (Rec && micBtn) {
        var recog = new Rec();
        recog.lang = 'es-ES';
        recog.interimResults = false;
        recog.maxAlternatives = 1;
        var listening = false;

        micBtn.addEventListener('click', function () {
            if (panel.hidden) { open(); }
            if (listening) { recog.stop(); return; }
            try { recog.start(); } catch (e) { /* ya iniciado */ }
        });
        recog.addEventListener('start', function () { listening = true; micBtn.classList.add('is-listening'); });
        recog.addEventListener('end', function () { listening = false; micBtn.classList.remove('is-listening'); });
        recog.addEventListener('error', function () { listening = false; micBtn.classList.remove('is-listening'); });
        recog.addEventListener('result', function (e) {
            var t = '';
            for (var i = 0; i < e.results.length; i++) { t += e.results[i][0].transcript; }
            t = t.trim();
            if (t) { if (input) { input.value = t; } send(t); }
        });
    } else if (micBtn) {
        micBtn.style.display = 'none';
    }
}());
