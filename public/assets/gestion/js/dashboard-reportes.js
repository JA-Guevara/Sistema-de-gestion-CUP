/* =========================================================================
   Dashboard analítico / Reportes — carrusel de 2 vistas:
   1) "Reportes": KPIs + gráficos, con sus slicers (carrera/materia/docente).
   2) "Detalle / Lista": tabla exportable con SUS PROPIOS filtros
      (carrera/materia/docente server-side; estado/búsqueda client-side).
   Cada vista mantiene su propio estado de filtros y su propio fetch al
   endpoint JSON. El cambio de gestión (compartido) recarga la página.

   Exportar / Imprimir (ambas vistas):
   - Lista  -> CSV del detalle filtrado + Imprimir / PDF.
   - Gráficos -> CSV con KPIs + datos de cada gráfico + Imprimir / PDF
     (la impresión captura los canvas tal como se ven en pantalla).
   ========================================================================= */
(function () {
    var payloadEl = document.querySelector('[data-rep-payload]');
    if (!payloadEl || typeof Chart === 'undefined') {
        return;
    }

    var initial = safeJson(payloadEl.textContent, {});
    if (!initial.meta) {
        return;
    }
    var cfgEl = document.querySelector('[data-rep-config]');
    var cfg = cfgEl ? safeJson(cfgEl.textContent, {}) : {};

    var COL = {
        ok: '#166534', bad: '#9b1c1c', warn: '#92580c',
        blue: '#1e3a8a', blueSoft: '#7c9bd6', grid: '#eef0f3', text: '#475569',
    };
    var ESTADO_BADGE = { APROBADO: 'activa', REPROBADO: 'inactiva', INCOMPLETO: 'borrador' };
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
    var ESTADO_INSCRIPCION_COLOR = {
        CONFIRMADA: COL.ok,
        COMPLETADA: COL.ok,
        VALIDADA: COL.blueSoft,
        PRESENTADA: COL.warn,
        PENDIENTE: '#94a3b8',
        BORRADOR: '#cbd5e1',
        RECHAZADA: COL.bad,
        ANULADA: '#7f1d1d',
    };

    Chart.defaults.font.family = "'Inter','Segoe UI',system-ui,sans-serif";
    Chart.defaults.color = COL.text;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.boxHeight = 12;
    Chart.defaults.animation.duration = 300;

    var charts = {};
    var chartsData = initial; // datos de la vista de reportes
    var listData = initial;   // datos de la vista de lista
    window.__repAssistantFlags = window.__repAssistantFlags || { soloOficiales: false, soloAsignados: false, soloSinAsignados: false, estadoPostulacion: '' };

    function safeJson(txt, fb) { try { return JSON.parse(txt || ''); } catch (e) { return fb; } }
    function esc(s) {
        s = s == null ? '' : String(s);
        return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
    }
    function cap(s) { s = String(s || '').toLowerCase(); return s.charAt(0).toUpperCase() + s.slice(1); }
    function canvas(name) { return document.querySelector('[data-chart="' + name + '"]'); }
    function val(sel) { var el = document.querySelector(sel); return el ? el.value : ''; }

    // Trunca etiquetas largas en el eje (con elipsis). El nombre completo igual
    // se ve en el tooltip, asi que no se pierde informacion.
    function truncTick(maxLen) {
        return function (value) {
            var lbl = this.getLabelForValue ? this.getLabelForValue(value) : value;
            lbl = String(lbl == null ? '' : lbl);
            return lbl.length > maxLen ? lbl.slice(0, maxLen - 1) + '…' : lbl;
        };
    }

    function bar(ctx, labels, datasets, opts) {
        opts = opts || {};
        // Alto dinamico para barras horizontales: ~30px por categoria, para que
        // no queden aplastadas cuando hay muchos docentes/grupos/carreras.
        if (opts.horizontal && ctx && ctx.parentNode) {
            ctx.parentNode.style.height = Math.max(220, labels.length * 30 + 48) + 'px';
        }
        // Eje de categoria (y si es horizontal, x si es vertical): mostrar TODAS
        // las etiquetas (autoSkip:false), sin rotar y truncadas.
        var catTicks = { autoSkip: false, maxRotation: 0, minRotation: 0, callback: truncTick(opts.horizontal ? 24 : 14), font: { size: 11 } };
        return new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: datasets },
            options: Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: !!opts.horizontal, color: COL.grid }, beginAtZero: true, ticks: opts.horizontal ? { precision: 0 } : catTicks },
                    y: { grid: { display: !opts.horizontal, color: COL.grid }, beginAtZero: true, ticks: opts.horizontal ? catTicks : { precision: 0 } },
                },
                plugins: {
                    legend: { display: !!opts.legend, position: 'bottom' },
                    tooltip: { callbacks: { title: function (items) { return items && items.length ? items[0].label : ''; } } },
                },
            }, opts.horizontal ? { indexAxis: 'y' } : {}, opts.extra || {}),
        });
    }

    function estadoInscripcionSeries(map) {
        var order = ['CONFIRMADA', 'COMPLETADA', 'VALIDADA', 'PRESENTADA', 'PENDIENTE', 'BORRADOR', 'RECHAZADA', 'ANULADA'];
        var labels = [];
        var values = [];
        var colors = [];
        order.forEach(function (estado) {
            var value = map && map[estado] ? Number(map[estado]) : 0;
            if (!value) { return; }
            labels.push(ESTADO_INSCRIPCION_LABEL[estado] || cap(estado));
            values.push(value);
            colors.push(ESTADO_INSCRIPCION_COLOR[estado] || COL.blue);
        });

        if (!labels.length) {
            labels.push('Sin registros');
            values.push(0);
            colors.push('#e2e8f0');
        }

        return { labels: labels, values: values, colors: colors };
    }

    function postulacionesChart(ctx, map) {
        var s = estadoInscripcionSeries(map || {});
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: s.labels,
                datasets: [{ label: 'Postulantes', data: s.values, backgroundColor: s.colors, borderRadius: 5 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, callback: truncTick(15), font: { size: 11 } } },
                    y: { beginAtZero: true, grid: { color: COL.grid }, ticks: { precision: 0 } },
                },
                plugins: { legend: { display: false } },
            },
        });
    }

    function renderKpis(k) {
        document.querySelectorAll('[data-kpi]').forEach(function (el) {
            var key = el.getAttribute('data-kpi');
            if (k[key] === undefined) { return; }
            el.textContent = key === 'pctAprobacion' ? (k[key] + '%') : String(k[key]);
        });
    }

    function destroyCharts() {
        Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].destroy(); } });
        charts = {};
    }

    function buildCharts(d) {
        destroyCharts();

        var minEl = document.querySelector('[data-rep-min]');
        if (minEl) { minEl.textContent = d.meta.notaMinima; }

        var ec = canvas('estado');
        if (ec) {
            charts.estado = new Chart(ec, {
                type: 'doughnut',
                data: {
                    labels: ['Aprobados', 'Reprobados', 'Incompletos'],
                    datasets: [{ data: [d.estadoAprobacion.aprobados, d.estadoAprobacion.reprobados, d.estadoAprobacion.incompletos], backgroundColor: [COL.ok, COL.bad, COL.warn], borderWidth: 0 }],
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right' }, tooltip: { callbacks: { label: function (c) { var data = (c.dataset && c.dataset.data) || []; var t = data.reduce(function (a, b) { return a + (b || 0); }, 0); var v = c.parsed || 0; var p = t ? Math.round(v * 100 / t) : 0; return (c.label || '') + ': ' + v + ' (' + p + '%)'; } } } } },
            });
        }

        var pe = canvas('postulacionesEstudiantes');
        if (pe) { charts.pe = postulacionesChart(pe, (d.postulaciones && d.postulaciones.estudiantes) || {}); }

        var pd = canvas('postulacionesDocentes');
        if (pd) { charts.pd = postulacionesChart(pd, (d.postulaciones && d.postulaciones.docentes) || {}); }

        var pm = canvas('promedioMateria');
        if (pm) {
            charts.pm = bar(pm, d.promedioPorMateria.map(function (x) { return x.materia; }), [{
                label: 'Promedio',
                data: d.promedioPorMateria.map(function (x) { return x.promedio; }),
                backgroundColor: d.promedioPorMateria.map(function (x) { return x.promedio >= d.meta.notaMinima ? COL.ok : COL.bad; }),
                borderRadius: 4,
            }], { extra: { scales: { y: { beginAtZero: true, max: 100, grid: { color: COL.grid }, ticks: { precision: 0 } }, x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, callback: truncTick(14), font: { size: 11 } } } }, plugins: { legend: { display: false }, tooltip: { callbacks: { title: function (i) { return i && i.length ? i[0].label : ''; } } } } } });
        }

        var am = canvas('aprobMateria');
        if (am) {
            charts.am = new Chart(am, {
                type: 'bar',
                data: {
                    labels: d.promedioPorMateria.map(function (x) { return x.materia; }),
                    datasets: [
                        { label: 'Aprobados', data: d.promedioPorMateria.map(function (x) { return x.aprobados; }), backgroundColor: COL.ok, borderRadius: 4 },
                        { label: 'Reprobados', data: d.promedioPorMateria.map(function (x) { return x.reprobados; }), backgroundColor: COL.bad, borderRadius: 4 },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { x: { stacked: true, grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, callback: truncTick(14), font: { size: 11 } } }, y: { stacked: true, beginAtZero: true, grid: { color: COL.grid }, ticks: { precision: 0 } } },
                    plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { title: function (i) { return i && i.length ? i[0].label : ''; } } } },
                },
            });
        }

        var tg = canvas('topGrupos');
        if (tg) { charts.tg = bar(tg, d.topGrupos.map(function (x) { return x.grupo; }), [{ label: 'Aprobados', data: d.topGrupos.map(function (x) { return x.aprobados; }), backgroundColor: COL.blue, borderRadius: 4 }], { horizontal: true }); }

        var cc = canvas('carrera');
        if (cc) { charts.cc = bar(cc, d.inscritosPorCarrera.map(function (x) { return x.carrera; }), [{ label: 'Inscritos', data: d.inscritosPorCarrera.map(function (x) { return x.total; }), backgroundColor: COL.blueSoft, borderRadius: 4 }], { horizontal: true }); }

        var dc = canvas('docentes');
        if (dc) { charts.dc = bar(dc, d.docentesPorGrupo.map(function (x) { return x.docente; }), [{ label: 'Grupos', data: d.docentesPorGrupo.map(function (x) { return x.grupos; }), backgroundColor: COL.blue, borderRadius: 4 }], { horizontal: true }); }
    }

    function renderTable(rows) {
        var tb = document.querySelector('[data-rep-tbody]');
        if (!tb) { return; }
        tb.innerHTML = '';
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td data-label="CI">' + esc(r.ci) + '</td>' +
                '<td data-label="Postulante">' + esc(r.nombre) + '</td>' +
                '<td data-label="Carrera">' + esc(r.carrera) + '</td>' +
                '<td class="gestion-col--num" data-label="Materias">' + (r.materias | 0) + '</td>' +
                '<td class="gestion-col--num" data-label="Promedio">' + (r.promedio === null || r.promedio === undefined ? '—' : r.promedio) + '</td>' +
                '<td class="gestion-col--center" data-label="Estado"><span class="gestion-badge gestion-badge--' + (ESTADO_BADGE[r.estado] || 'borrador') + '">' + cap(r.estado) + '</span></td>';
            tb.appendChild(tr);
        });
        var cnt = document.querySelector('[data-rep-count]');
        if (cnt) { cnt.textContent = rows.length + ' postulantes'; }
    }

    function renderDocentes(dd) {
        dd = dd || {};
        document.querySelectorAll('[data-doc-kpi]').forEach(function (el) {
            var k = el.getAttribute('data-doc-kpi');
            if (dd[k] !== undefined && dd[k] !== null) { el.textContent = String(dd[k]); }
        });
        var pe = document.querySelector('[data-doc-postulaciones]');
        if (pe) {
            var ps = (dd.postulaciones || [])
                .filter(function (p) { return Number(p.total || 0) > 0; })
                .map(function (p) { return (ESTADO_INSCRIPCION_LABEL[p.estado] || cap(p.estado)) + ': ' + p.total; });
            pe.textContent = ps.length ? ps.join(' · ') : 'Sin postulaciones de docente';
        }
        var tb = document.querySelector('[data-doc-tbody]');
        if (tb) {
            tb.innerHTML = '';
            var carga = dd.carga || [];
            if (!carga.length) {
                tb.innerHTML = '<tr><td colspan="3">No hay docentes con asignacion en esta gestion.</td></tr>';
                return;
            }
            carga.forEach(function (d) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td data-label="Docente">' + esc(d.docente) + '</td>' +
                    '<td class="gestion-col--num" data-label="Materias">' + (d.materias | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="Grupos">' + (d.grupos | 0) + '</td>';
                tb.appendChild(tr);
            });
        }
    }

    // ---- Comparativo entre gestiones (lazy: se carga al abrir la pestaña) ----
    var cmpLoaded = false;
    var cmpData = [];
    var cmpCharts = {};

    function cmpResize() {
        Object.keys(cmpCharts).forEach(function (k) { if (cmpCharts[k]) { cmpCharts[k].resize(); } });
    }

    function renderComparativo(rows) {
        var tb = document.querySelector('[data-cmp-tbody]');
        if (tb) {
            tb.innerHTML = '';
            if (!rows.length) {
                tb.innerHTML = '<tr><td colspan="10">No hay gestiones para comparar.</td></tr>';
            }
            rows.forEach(function (g) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td data-label="Gestion">' + esc(g.label || g.codigo) + (g.activa ? ' <span class="gestion-badge gestion-badge--activa">activa</span>' : '') + '</td>' +
                    '<td class="gestion-col--num" data-label="Inscritos">' + (g.inscritos | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="Evaluados">' + (g.evaluados | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="Aprobados">' + (g.aprobados | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="Reprobados">' + (g.reprobados | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="Incompletos">' + (g.incompletos | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="% aprob.">' + g.pctAprobacion + '%</td>' +
                    '<td class="gestion-col--num" data-label="Promedio">' + (g.promedioGeneral | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="Docentes">' + (g.docentes | 0) + '</td>' +
                    '<td class="gestion-col--num" data-label="Doc. postul.">' + (g.postulacionesDocentes | 0) + '</td>';
                tb.appendChild(tr);
            });
        }

        var labels = rows.map(function (g) { return g.label || g.codigo; });
        var max100 = { extra: { scales: { y: { beginAtZero: true, max: 100, grid: { color: COL.grid } }, x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, callback: truncTick(14), font: { size: 11 } } } } } };

        Object.keys(cmpCharts).forEach(function (k) { if (cmpCharts[k]) { cmpCharts[k].destroy(); } });
        cmpCharts = {};

        var ic = document.querySelector('[data-cmp-chart="inscritos"]');
        if (ic) { cmpCharts.ic = bar(ic, labels, [{ label: 'Inscritos', data: rows.map(function (g) { return g.inscritos; }), backgroundColor: COL.blue, borderRadius: 4 }]); }

        var rcv = document.querySelector('[data-cmp-chart="resultado"]');
        if (rcv) {
            cmpCharts.rc = bar(rcv, labels, [
                { label: 'Aprobados', data: rows.map(function (g) { return g.aprobados; }), backgroundColor: COL.ok, borderRadius: 4 },
                { label: 'Reprobados', data: rows.map(function (g) { return g.reprobados; }), backgroundColor: COL.bad, borderRadius: 4 },
                { label: 'Incompletos', data: rows.map(function (g) { return g.incompletos; }), backgroundColor: COL.warn, borderRadius: 4 },
            ], { legend: true });
        }

        var pc = document.querySelector('[data-cmp-chart="pct"]');
        if (pc) { cmpCharts.pc = bar(pc, labels, [{ label: '% aprobacion', data: rows.map(function (g) { return g.pctAprobacion; }), backgroundColor: COL.ok, borderRadius: 4 }], max100); }

        var prc = document.querySelector('[data-cmp-chart="promedio"]');
        if (prc) { cmpCharts.prc = bar(prc, labels, [{ label: 'Promedio', data: rows.map(function (g) { return g.promedioGeneral; }), backgroundColor: COL.blueSoft, borderRadius: 4 }], max100); }

        var dcv = document.querySelector('[data-cmp-chart="docentes"]');
        if (dcv) {
            cmpCharts.dc = bar(dcv, labels, [
                { label: 'Asignados', data: rows.map(function (g) { return g.docentes; }), backgroundColor: COL.blue, borderRadius: 4 },
                { label: 'Postulados', data: rows.map(function (g) { return g.postulacionesDocentes; }), backgroundColor: COL.blueSoft, borderRadius: 4 },
            ], { legend: true });
        }
    }

    function cmpSelectedIds() {
        var ids = [];
        document.querySelectorAll('[data-cmp-gestion]:checked').forEach(function (c) { ids.push(parseInt(c.value, 10)); });
        return ids;
    }

    function cmpFiltered() {
        var ids = cmpSelectedIds();
        return cmpData.filter(function (g) { return ids.indexOf(g.id) !== -1; });
    }

    function cmpApplyFilter() { renderComparativo(cmpFiltered()); }

    // "Todas" queda marcada solo si TODAS las gestiones estan marcadas.
    function cmpSyncAll() {
        var all = document.querySelector('[data-cmp-all]');
        if (!all) { return; }
        var boxes = document.querySelectorAll('[data-cmp-gestion]');
        var checked = document.querySelectorAll('[data-cmp-gestion]:checked');
        all.checked = boxes.length > 0 && boxes.length === checked.length;
    }

    function loadComparativo() {
        if (cmpLoaded) { cmpResize(); return; }
        if (!cfg.comparativoUrl) { return; }
        cmpLoaded = true;
        document.body.style.cursor = 'progress';
        fetch(cfg.comparativoUrl, { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { cmpData = (d && d.gestiones) || []; cmpApplyFilter(); })
            .catch(function () { cmpLoaded = false; })
            .then(function () { document.body.style.cursor = ''; });
    }

    // ---- Filtros ----
    function filtersOf(formSel) {
        var p = new URLSearchParams();
        p.set('gestion', initial.meta.gestionId);
        document.querySelectorAll(formSel + ' [data-field]').forEach(function (el) {
            var f = el.getAttribute('data-field');
            if ((f === 'carrera' || f === 'materia' || f === 'docente') && el.value) { p.set(f, el.value); }
        });
        return p;
    }

    function fetchData(params, cb) {
        if (!cfg.dataUrl) { return; }
        document.body.style.cursor = 'progress';
        fetch(cfg.dataUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d && d.meta) { cb(d); } })
            .catch(function () {})
            .then(function () { document.body.style.cursor = ''; });
    }

    // Vista Reportes
    function refreshCharts() {
        fetchData(filtersOf('[data-rep-form="charts"]'), function (d) { chartsData = d; renderKpis(d.kpis); buildCharts(d); renderDocentes(d.docentes); });
    }

    // Vista Lista (carrera/materia/docente => server; estado/texto => client)
    function listClientFilter(rows) {
        var estado = val('[data-rep-form="list"] [data-field="estado"]');
        var texto = (val('[data-rep-form="list"] [data-field="texto"]') || '').toLowerCase().trim();
        var flags = window.__repAssistantFlags || {};
        var soloOficiales = !!flags.soloOficiales;
        var soloAsignados = !!flags.soloAsignados;
        var soloSinAsignados = !!flags.soloSinAsignados;
        var estadoPostulacion = String(flags.estadoPostulacion || '').toUpperCase();
        return (rows || []).filter(function (r) {
            if (estado && r.estado !== estado) { return false; }
            if (texto && ((String(r.ci || '') + ' ' + String(r.nombre || '')).toLowerCase().indexOf(texto) === -1)) { return false; }
            var ei = String(r.estadoInscripcion || '').toUpperCase();
            var tieneAsignaciones = !!(r.tieneAsignaciones || Number(r.materias || 0) > 0);

            if (estadoPostulacion && ei !== estadoPostulacion) { return false; }
            if (soloOficiales) {
                if (!(ei === 'CONFIRMADA' || ei === 'VALIDADA' || ei === 'COMPLETADA')) { return false; }
            }
            if (soloAsignados && !tieneAsignaciones) { return false; }
            if (soloSinAsignados && tieneAsignaciones) { return false; }
            return true;
        });
    }
    function renderList() { renderTable(listClientFilter(listData.detalle)); }
    function refreshList() { fetchData(filtersOf('[data-rep-form="list"]'), function (d) { listData = d; renderList(); }); }

    // ---- Eventos ----
    document.querySelectorAll('[data-rep-form="charts"] [data-field]').forEach(function (s) { s.addEventListener('change', refreshCharts); });

    document.querySelectorAll('[data-rep-form="list"] [data-field]').forEach(function (el) {
        var f = el.getAttribute('data-field');
        if (f === 'carrera' || f === 'materia' || f === 'docente') {
            el.addEventListener('change', refreshList);
        } else {
            el.addEventListener('input', renderList);
            el.addEventListener('change', renderList);
        }
    });

    var clearCharts = document.querySelector('[data-rep-clear="charts"]');
    if (clearCharts) { clearCharts.addEventListener('click', function () { document.querySelectorAll('[data-rep-form="charts"] [data-field]').forEach(function (s) { s.value = ''; }); refreshCharts(); }); }

    var clearList = document.querySelector('[data-rep-clear="list"]');
    if (clearList) {
        clearList.addEventListener('click', function () {
            document.querySelectorAll('[data-rep-form="list"] [data-field]').forEach(function (s) { s.value = ''; });
            window.__repAssistantFlags = { soloOficiales: false, soloAsignados: false, soloSinAsignados: false, estadoPostulacion: '' };
            refreshList();
        });
    }

    window.addEventListener('rep:assistant-flags-changed', function () {
        renderList();
    });

    // Carrusel / tabs
    var topToolbar = document.querySelector('.rep-toolbar');
    document.querySelectorAll('[data-rep-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-rep-tab');
            document.querySelectorAll('[data-rep-tab]').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            document.querySelectorAll('[data-rep-panel]').forEach(function (p) {
                p.hidden = p.getAttribute('data-rep-panel') !== tab;
            });
            // El selector de UNA gestion no aplica al comparativo (multi-gestion).
            if (topToolbar) { topToolbar.style.display = (tab === 'comparativo') ? 'none' : ''; }
            if (tab === 'charts') {
                Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].resize(); } });
            } else if (tab === 'comparativo') {
                loadComparativo();
            }
        });
    });

    // Comparativo: multi-select de gestiones (filtra en cliente, sin recargar).
    document.querySelectorAll('[data-cmp-gestion]').forEach(function (c) {
        c.addEventListener('change', function () { cmpSyncAll(); cmpApplyFilter(); });
    });
    var cmpAllBox = document.querySelector('[data-cmp-all]');
    if (cmpAllBox) {
        cmpAllBox.addEventListener('change', function () {
            document.querySelectorAll('[data-cmp-gestion]').forEach(function (c) { c.checked = cmpAllBox.checked; });
            cmpApplyFilter();
        });
    }

    var gsel = document.querySelector('[data-rep-gestion]');
    if (gsel) { gsel.addEventListener('change', function () { window.location = window.location.pathname + '?gestion=' + encodeURIComponent(gsel.value); }); }

    // =====================================================================
    // Exportar (CSV, compatible con Excel) / Imprimir (PDF)
    // =====================================================================
    function toCsv(rows) {
        return rows.map(function (row) {
            return (row || []).map(function (c) { c = (c == null ? '' : String(c)); return '"' + c.replace(/"/g, '""') + '"'; }).join(',');
        }).join('\r\n');
    }
    function downloadCsv(name, rows) {
        // BOM (﻿) para que Excel respete los acentos UTF-8.
        var blob = new Blob(['﻿' + toCsv(rows)], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    // Imprimir / PDF — funciona en ambas vistas: la vista oculta del carrusel
    // no se imprime (CSS @media print), así que se imprime la que se ve.
    function appendEstadoRows(rows, title, map) {
        var serie = estadoInscripcionSeries(map || {});
        rows.push([title]);
        rows.push(['Estado', 'Cantidad']);
        serie.labels.forEach(function (label, idx) { rows.push([label, serie.values[idx]]); });
        rows.push([]);
    }

    document.querySelectorAll('[data-rep-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // Lista: CSV del detalle filtrado.
    var csvBtn = document.querySelector('[data-rep-export="csv"]');
    if (csvBtn) { csvBtn.addEventListener('click', function () {
        var rows = [['CI', 'Postulante', 'Carrera', 'Materias', 'Promedio', 'Estado']];
        listClientFilter(listData.detalle).forEach(function (r) {
            rows.push([r.ci, r.nombre, r.carrera, r.materias, (r.promedio === null || r.promedio === undefined ? '' : r.promedio), cap(r.estado)]);
        });
        downloadCsv('reporte_detalle_' + (listData.meta.gestionCodigo || 'cup') + '.csv', rows);
    }); }

    // Reportes (gráficos): CSV con KPIs + los datos detrás de cada gráfico.
    var chartsCsvBtn = document.querySelector('[data-rep-export="charts"]');
    if (chartsCsvBtn) { chartsCsvBtn.addEventListener('click', function () {
        var d = chartsData || initial;
        var rows = [];
        rows.push(['CUP FICCT - Dashboard & Reportes']);
        rows.push(['Gestion', d.meta.gestionCodigo || '']);
        rows.push([]);

        rows.push(['INDICADORES (KPIs)']);
        rows.push(['Indicador', 'Valor']);
        rows.push(['Estudiantes inscritos', d.kpis.inscritos]);
        rows.push(['Estudiantes aprobados', d.kpis.estudiantesAprobados]);
        rows.push(['Estudiantes rechazados', d.kpis.estudiantesRechazados]);
        rows.push(['Estudiantes en proceso', d.kpis.estudiantesEnProceso]);
        rows.push(['Docentes postulados', d.kpis.postulacionesDocentes]);
        rows.push(['Docentes aprobados', d.kpis.docentesAprobados]);
        rows.push(['Docentes rechazados', d.kpis.docentesRechazados]);
        rows.push(['Docentes en proceso', d.kpis.docentesEnProceso]);
        rows.push(['Evaluados con notas', d.kpis.evaluados]);
        rows.push(['Aprobados por notas', d.kpis.aprobados]);
        rows.push(['Reprobados por notas', d.kpis.reprobados]);
        rows.push(['Sin notas completas', d.kpis.incompletos]);
        rows.push(['% aprobacion por notas', d.kpis.pctAprobacion + '%']);
        rows.push(['Promedio general', d.kpis.promedioGeneral]);
        rows.push(['Grupos habilitados', d.kpis.grupos]);
        rows.push(['Docentes asignados', d.kpis.docentes]);
        rows.push([]);

        rows.push(['ESTADO DE APROBACION']);
        rows.push(['Estado', 'Cantidad']);
        rows.push(['Aprobados', d.estadoAprobacion.aprobados]);
        rows.push(['Reprobados', d.estadoAprobacion.reprobados]);
        rows.push(['Incompletos', d.estadoAprobacion.incompletos]);
        rows.push([]);

        appendEstadoRows(rows, 'POSTULANTES ESTUDIANTES POR ESTADO', d.postulaciones && d.postulaciones.estudiantes);
        appendEstadoRows(rows, 'POSTULANTES DOCENTES POR ESTADO', d.postulaciones && d.postulaciones.docentes);

        rows.push(['PROMEDIO Y RESULTADO POR MATERIA (min. ' + d.meta.notaMinima + ')']);
        rows.push(['Materia', 'Promedio', 'Aprobados', 'Reprobados']);
        (d.promedioPorMateria || []).forEach(function (x) { rows.push([x.materia, x.promedio, x.aprobados, x.reprobados]); });
        rows.push([]);

        rows.push(['INSCRITOS POR CARRERA']);
        rows.push(['Carrera', 'Inscritos']);
        (d.inscritosPorCarrera || []).forEach(function (x) { rows.push([x.carrera, x.total]); });
        rows.push([]);

        rows.push(['TOP GRUPOS POR APROBADOS']);
        rows.push(['Grupo', 'Aprobados']);
        (d.topGrupos || []).forEach(function (x) { rows.push([x.grupo, x.aprobados]); });
        rows.push([]);

        rows.push(['CARGA DOCENTE (materias y grupos por docente)']);
        rows.push(['Docente', 'Materias', 'Grupos']);
        ((d.docentes && d.docentes.carga) || []).forEach(function (x) { rows.push([x.docente, x.materias, x.grupos]); });
        rows.push([]);

        rows.push(['POSTULACIONES DE DOCENTE POR ESTADO']);
        rows.push(['Estado', 'Cantidad']);
        ((d.docentes && d.docentes.postulaciones) || []).forEach(function (x) { rows.push([x.estado, x.total]); });
        rows.push(['Entrevistas agendadas', (d.docentes && d.docentes.entrevistasAgendadas) || 0]);

        downloadCsv('reporte_dashboard_' + (d.meta.gestionCodigo || 'cup') + '.csv', rows);
    }); }

    // Comparativo: exportar CSV (de lo ya cargado).
    var cmpCsvBtn = document.querySelector('[data-cmp-export]');
    if (cmpCsvBtn) { cmpCsvBtn.addEventListener('click', function () {
        if (!cmpData.length) { loadComparativo(); return; }
        var rows = [['COMPARATIVO ENTRE GESTIONES']];
        rows.push(['Gestion', 'Inscritos', 'Evaluados', 'Aprobados', 'Reprobados', 'Incompletos', '% aprobacion', 'Promedio', 'Docentes asignados', 'Docentes postulados']);
        cmpFiltered().forEach(function (g) {
            rows.push([g.label || g.codigo, g.inscritos, g.evaluados, g.aprobados, g.reprobados, g.incompletos, g.pctAprobacion + '%', g.promedioGeneral, g.docentes, g.postulacionesDocentes]);
        });
        downloadCsv('reporte_comparativo_gestiones.csv', rows);
    }); }

    // ---- Render inicial ----
    renderKpis(initial.kpis);
    buildCharts(initial);
    renderList();
    renderDocentes(initial.docentes);
}());
