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

    Chart.defaults.font.family = "'Inter','Segoe UI',system-ui,sans-serif";
    Chart.defaults.color = COL.text;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.boxHeight = 12;
    Chart.defaults.animation.duration = 300;

    var charts = {};
    var chartsData = initial; // datos de la vista de reportes
    var listData = initial;   // datos de la vista de lista

    function safeJson(txt, fb) { try { return JSON.parse(txt || ''); } catch (e) { return fb; } }
    function esc(s) {
        s = s == null ? '' : String(s);
        return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
    }
    function cap(s) { s = String(s || '').toLowerCase(); return s.charAt(0).toUpperCase() + s.slice(1); }
    function canvas(name) { return document.querySelector('[data-chart="' + name + '"]'); }
    function val(sel) { var el = document.querySelector(sel); return el ? el.value : ''; }

    function bar(ctx, labels, datasets, opts) {
        opts = opts || {};
        return new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: datasets },
            options: Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: !!opts.horizontal, color: COL.grid }, beginAtZero: true, ticks: { precision: 0 } },
                    y: { grid: { display: !opts.horizontal, color: COL.grid }, beginAtZero: true, ticks: { precision: 0 } },
                },
                plugins: { legend: { display: !!opts.legend, position: 'bottom' } },
            }, opts.horizontal ? { indexAxis: 'y' } : {}, opts.extra || {}),
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
                options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right' } } },
            });
        }

        var pm = canvas('promedioMateria');
        if (pm) {
            charts.pm = bar(pm, d.promedioPorMateria.map(function (x) { return x.materia; }), [{
                label: 'Promedio',
                data: d.promedioPorMateria.map(function (x) { return x.promedio; }),
                backgroundColor: d.promedioPorMateria.map(function (x) { return x.promedio >= d.meta.notaMinima ? COL.ok : COL.bad; }),
                borderRadius: 4,
            }], { extra: { scales: { y: { beginAtZero: true, max: 100, grid: { color: COL.grid } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } } });
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
                    scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid: { color: COL.grid }, ticks: { precision: 0 } } },
                    plugins: { legend: { position: 'bottom' } },
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
        fetchData(filtersOf('[data-rep-form="charts"]'), function (d) { chartsData = d; renderKpis(d.kpis); buildCharts(d); });
    }

    // Vista Lista (carrera/materia/docente => server; estado/texto => client)
    function listClientFilter(rows) {
        var estado = val('[data-rep-form="list"] [data-field="estado"]');
        var texto = (val('[data-rep-form="list"] [data-field="texto"]') || '').toLowerCase().trim();
        return (rows || []).filter(function (r) {
            if (estado && r.estado !== estado) { return false; }
            if (texto && ((String(r.ci || '') + ' ' + String(r.nombre || '')).toLowerCase().indexOf(texto) === -1)) { return false; }
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
    if (clearList) { clearList.addEventListener('click', function () { document.querySelectorAll('[data-rep-form="list"] [data-field]').forEach(function (s) { s.value = ''; }); refreshList(); }); }

    // Carrusel / tabs
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
            if (tab === 'charts') {
                Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].resize(); } });
            }
        });
    });

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
        rows.push(['Inscritos', d.kpis.inscritos]);
        rows.push(['Aprobados', d.kpis.aprobados]);
        rows.push(['Reprobados', d.kpis.reprobados]);
        rows.push(['Incompletos', d.kpis.incompletos]);
        rows.push(['% Aprobacion', d.kpis.pctAprobacion + '%']);
        rows.push(['Promedio general', d.kpis.promedioGeneral]);
        rows.push(['Grupos habilitados', d.kpis.grupos]);
        rows.push(['Docentes', d.kpis.docentes]);
        rows.push([]);

        rows.push(['ESTADO DE APROBACION']);
        rows.push(['Estado', 'Cantidad']);
        rows.push(['Aprobados', d.estadoAprobacion.aprobados]);
        rows.push(['Reprobados', d.estadoAprobacion.reprobados]);
        rows.push(['Incompletos', d.estadoAprobacion.incompletos]);
        rows.push([]);

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

        rows.push(['CARGA DOCENTE (grupos por docente)']);
        rows.push(['Docente', 'Grupos']);
        (d.docentesPorGrupo || []).forEach(function (x) { rows.push([x.docente, x.grupos]); });

        downloadCsv('reporte_dashboard_' + (d.meta.gestionCodigo || 'cup') + '.csv', rows);
    }); }

    // ---- Render inicial ----
    renderKpis(initial.kpis);
    buildCharts(initial);
    renderList();
}());
