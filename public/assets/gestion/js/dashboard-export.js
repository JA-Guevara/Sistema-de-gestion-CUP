/* =========================================================================
   Exportación completa de reportes.
   - "Exportar Excel": genera un .xlsx REAL con SheetJS (multi-hoja, con
     autofiltro/tablas y anchos de columna), trayendo los datos con los filtros
     activos de esa vista. Si SheetJS no está, cae al .xls del servidor.
   - "Exportar CSV completo": va al endpoint del servidor (CSV con secciones).
   Autónomo: no toca dashboard-reportes.js.
   ========================================================================= */
(function () {
    var payloadEl = document.querySelector('[data-rep-payload]');
    if (!payloadEl) { return; }

    var meta = (safeJson(payloadEl.textContent, {}) || {}).meta || {};
    if (!meta.gestionId) { return; }

    var cfgEl = document.querySelector('[data-rep-config]');
    var cfg = cfgEl ? safeJson(cfgEl.textContent, {}) : {};
    var EXPORT_URL = '/admin/dashboard/export';
    var DATA_URL = cfg.dataUrl || '/admin/dashboard/data';

    function safeJson(t, fb) { try { return JSON.parse(t || ''); } catch (e) { return fb; } }
    function cap(s) { s = String(s || '').toLowerCase(); return s.charAt(0).toUpperCase() + s.slice(1); }
    function fieldVal(form, f) { var el = form.querySelector('[data-field="' + f + '"]'); return el ? el.value : ''; }

    function filtersOf(form) {
        var o = {};
        ['carrera', 'materia', 'docente', 'estado', 'texto'].forEach(function (f) { o[f] = fieldVal(form, f); });
        return o;
    }

    function query(f, extra) {
        var p = new URLSearchParams();
        p.set('gestion', meta.gestionId);
        ['carrera', 'materia', 'docente', 'estado', 'texto'].forEach(function (k) { if (f[k]) { p.set(k, f[k]); } });
        if (extra) { Object.keys(extra).forEach(function (k) { p.set(k, extra[k]); }); }
        return p.toString();
    }

    function filtrarDetalle(rows, f) {
        var estado = f.estado || '';
        var texto = (f.texto || '').toLowerCase().trim();
        return (rows || []).filter(function (r) {
            if (estado && r.estado !== estado) { return false; }
            if (texto && ((String(r.ci || '') + ' ' + String(r.nombre || '')).toLowerCase().indexOf(texto) === -1)) { return false; }
            return true;
        });
    }

    // ---- CSV completo (servidor) ----
    function csvServer(form) {
        window.location.href = EXPORT_URL + '?' + query(filtersOf(form), { formato: 'csv' });
    }

    // ---- Excel real .xlsx (cliente, SheetJS) ----
    function excelClient(form) {
        var f = filtersOf(form);
        if (typeof XLSX === 'undefined') {
            window.location.href = EXPORT_URL + '?' + query(f, { formato: 'xlsx' });
            return;
        }
        document.body.style.cursor = 'progress';
        fetch(DATA_URL + '?' + query(f), { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d && d.meta) { buildXlsx(d, f); } })
            .catch(function () { window.location.href = EXPORT_URL + '?' + query(f, { formato: 'xlsx' }); })
            .then(function () { document.body.style.cursor = ''; });
    }

    function aoaSheet(wb, name, aoa, cols, dataRows) {
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        if (cols) { ws['!cols'] = cols; }
        if (dataRows > 0 && aoa.length > 0) {
            ws['!autofilter'] = {
                ref: XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: aoa.length - 1, c: aoa[0].length - 1 } }),
            };
        }
        XLSX.utils.book_append_sheet(wb, ws, name);
    }

    function buildXlsx(d, f) {
        var m = d.meta;
        var materias = m.materias || [];
        var k = d.kpis;
        var wb = XLSX.utils.book_new();

        // Resumen
        var res = [
            ['CUP FICCT — Reporte de admisión'],
            ['Gestión', m.gestionCodigo + ' — ' + m.gestionNombre],
            ['Generado', new Date().toLocaleString()],
            ['Nota mínima', m.notaMinima],
            [],
            ['Indicador', 'Valor'],
            ['Inscritos', k.inscritos], ['Evaluados', k.evaluados],
            ['Aprobados', k.aprobados], ['Reprobados', k.reprobados],
            ['Incompletos', k.incompletos], ['% Aprobación', k.pctAprobacion],
            ['Promedio general', k.promedioGeneral], ['Grupos habilitados', k.grupos],
            ['Docentes', k.docentes],
        ];
        aoaSheet(wb, 'Resumen', res, [{ wch: 22 }, { wch: 36 }], 0);

        // Postulantes (columnas por materia) con autofiltro
        var det = filtrarDetalle(d.detalle, f);
        var head = ['CI', 'Postulante', 'Email', 'Carrera'].concat(materias).concat(['Promedio', 'Estado']);
        var rows = [head];
        det.forEach(function (r) {
            var row = [r.ci, r.nombre, r.email || '', r.carrera];
            materias.forEach(function (mat) { row.push(r.notas && r.notas[mat] != null ? r.notas[mat] : '-'); });
            row.push(r.promedio == null ? '-' : r.promedio);
            row.push(cap(r.estado));
            rows.push(row);
        });
        var cols = [{ wch: 12 }, { wch: 26 }, { wch: 26 }, { wch: 22 }];
        materias.forEach(function () { cols.push({ wch: 11 }); });
        cols.push({ wch: 10 }, { wch: 12 });
        aoaSheet(wb, 'Postulantes', rows, cols, det.length);

        // Por materia
        var pm = [['Materia', 'Promedio', 'Aprobados', 'Reprobados']];
        d.promedioPorMateria.forEach(function (x) { pm.push([x.materia, x.promedio, x.aprobados, x.reprobados]); });
        aoaSheet(wb, 'Por materia', pm, [{ wch: 24 }, { wch: 10 }, { wch: 10 }, { wch: 10 }], d.promedioPorMateria.length);

        // Por carrera
        var pc = [['Carrera', 'Inscritos']];
        d.inscritosPorCarrera.forEach(function (x) { pc.push([x.carrera, x.total]); });
        aoaSheet(wb, 'Por carrera', pc, [{ wch: 30 }, { wch: 10 }], d.inscritosPorCarrera.length);

        // Grupos
        var gr = [['Grupo', 'Total', 'Aprobados', 'Reprobados', 'Incompletos']];
        (d.grupos || []).forEach(function (g) { gr.push([g.grupo, g.total, g.aprobados, g.reprobados, g.incompletos]); });
        aoaSheet(wb, 'Grupos', gr, [{ wch: 16 }, { wch: 8 }, { wch: 10 }, { wch: 10 }, { wch: 11 }], (d.grupos || []).length);

        // Docentes
        var dc = [['Docente', 'Grupos a cargo']];
        d.docentesPorGrupo.forEach(function (x) { dc.push([x.docente, x.grupos]); });
        aoaSheet(wb, 'Docentes', dc, [{ wch: 28 }, { wch: 14 }], d.docentesPorGrupo.length);

        var fname = 'reporte_cup_' + String(m.gestionCodigo || 'cup').replace(/[^A-Za-z0-9]+/g, '-') + '.xlsx';
        XLSX.writeFile(wb, fname);
    }

    function mkBtn(label, soft) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'gestion-button' + (soft ? ' gestion-button--soft' : '');
        b.textContent = label;
        return b;
    }

    document.querySelectorAll('[data-rep-form]').forEach(function (form) {
        var excel = mkBtn('Exportar Excel', true);
        excel.addEventListener('click', function () { excelClient(form); });

        // Un solo botón CSV: reusamos el "Exportar CSV" existente y lo apuntamos
        // al CSV COMPLETO del servidor (quitando su handler anterior).
        var csvBtn = form.querySelector('[data-rep-export]');
        if (csvBtn) {
            var clone = csvBtn.cloneNode(true);
            clone.removeAttribute('data-rep-export');
            clone.textContent = 'Exportar CSV';
            csvBtn.parentNode.replaceChild(clone, csvBtn);
            clone.addEventListener('click', function () { csvServer(form); });
            clone.parentNode.insertBefore(excel, clone);
        } else {
            var csv = mkBtn('Exportar CSV', false);
            csv.addEventListener('click', function () { csvServer(form); });
            form.appendChild(excel);
            form.appendChild(csv);
        }
    });
}());
