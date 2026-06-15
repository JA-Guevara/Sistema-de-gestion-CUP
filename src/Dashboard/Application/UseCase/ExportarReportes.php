<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

/**
 * Genera la exportación COMPLETA de los reportes (todo el payload de
 * GetReportes) en dos formatos, sin dependencias externas:
 *  - CSV con secciones (resumen + detalle con notas por materia + por materia
 *    + por carrera + grupos + docentes).
 *  - Excel multi-hoja en formato SpreadsheetML 2003 (.xls) — lo abre Excel con
 *    formato y varias pestañas, sin necesidad de PhpSpreadsheet ni ext-zip.
 *
 * @phpstan-type Contexto array{titulo: string, gestion: string, fecha: string, filtros: array<string, string>}
 */
final class ExportarReportes
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $contexto
     */
    public function csv(array $payload, array $contexto): string
    {
        $meta = $payload['meta'];
        $materias = $meta['materias'] ?? [];
        $out = [];

        $out[] = $this->csvLine([$contexto['titulo']]);
        $out[] = $this->csvLine(['Gestión', $contexto['gestion']]);
        $out[] = $this->csvLine(['Generado', $contexto['fecha']]);
        $out[] = $this->csvLine(['Nota mínima de aprobación', (string) $meta['notaMinima']]);
        foreach ($contexto['filtros'] as $k => $v) {
            $out[] = $this->csvLine([$k, $v]);
        }
        $out[] = '';

        $out[] = $this->csvLine(['INDICADORES']);
        $k = $payload['kpis'];
        $out[] = $this->csvLine(['Inscritos', (string) $k['inscritos']]);
        $out[] = $this->csvLine(['Evaluados', (string) $k['evaluados']]);
        $out[] = $this->csvLine(['Aprobados', (string) $k['aprobados']]);
        $out[] = $this->csvLine(['Reprobados', (string) $k['reprobados']]);
        $out[] = $this->csvLine(['Incompletos', (string) $k['incompletos']]);
        $out[] = $this->csvLine(['% Aprobación', $k['pctAprobacion'] . '%']);
        $out[] = $this->csvLine(['Promedio general', (string) $k['promedioGeneral']]);
        $out[] = $this->csvLine(['Grupos habilitados', (string) $k['grupos']]);
        $out[] = $this->csvLine(['Docentes', (string) $k['docentes']]);
        $out[] = '';

        $out[] = $this->csvLine(['DETALLE DE POSTULANTES (' . count($payload['detalle']) . ')']);
        $cab = array_merge(['CI', 'Postulante', 'Email', 'Carrera'], $materias, ['Promedio', 'Estado']);
        $out[] = $this->csvLine($cab);
        foreach ($payload['detalle'] as $r) {
            $fila = [$r['ci'], $r['nombre'], $r['email'] ?? '', $r['carrera']];
            foreach ($materias as $mat) {
                $fila[] = isset($r['notas'][$mat]) ? (string) $r['notas'][$mat] : '-';
            }
            $fila[] = $r['promedio'] === null ? '-' : (string) $r['promedio'];
            $fila[] = ucfirst(strtolower((string) $r['estado']));
            $out[] = $this->csvLine($fila);
        }
        $out[] = '';

        $out[] = $this->csvLine(['ESTADÍSTICAS POR MATERIA']);
        $out[] = $this->csvLine(['Materia', 'Promedio', 'Aprobados', 'Reprobados']);
        foreach ($payload['promedioPorMateria'] as $m) {
            $out[] = $this->csvLine([$m['materia'], (string) $m['promedio'], (string) $m['aprobados'], (string) $m['reprobados']]);
        }
        $out[] = '';

        $out[] = $this->csvLine(['INSCRITOS POR CARRERA']);
        $out[] = $this->csvLine(['Carrera', 'Inscritos']);
        foreach ($payload['inscritosPorCarrera'] as $c) {
            $out[] = $this->csvLine([$c['carrera'], (string) $c['total']]);
        }
        $out[] = '';

        $out[] = $this->csvLine(['GRUPOS']);
        $out[] = $this->csvLine(['Grupo', 'Total', 'Aprobados', 'Reprobados', 'Incompletos']);
        foreach (($payload['grupos'] ?? []) as $g) {
            $out[] = $this->csvLine([$g['grupo'], (string) $g['total'], (string) $g['aprobados'], (string) $g['reprobados'], (string) $g['incompletos']]);
        }
        $out[] = '';

        $out[] = $this->csvLine(['CARGA DOCENTE']);
        $out[] = $this->csvLine(['Docente', 'Grupos a cargo']);
        foreach ($payload['docentesPorGrupo'] as $d) {
            $out[] = $this->csvLine([$d['docente'], (string) $d['grupos']]);
        }

        return implode("\r\n", $out) . "\r\n";
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $contexto
     */
    public function excel(array $payload, array $contexto): string
    {
        $meta = $payload['meta'];
        $materias = $meta['materias'] ?? [];
        $k = $payload['kpis'];

        // ---- Hoja Resumen ----
        $resumen = [];
        $resumen[] = $this->xRow([$this->xCell($contexto['titulo'], 'String', 'title')]);
        $resumen[] = $this->xRow([$this->xCell('Gestión', 'String', 'sub'), $this->xCell($contexto['gestion'])]);
        $resumen[] = $this->xRow([$this->xCell('Generado', 'String', 'sub'), $this->xCell($contexto['fecha'])]);
        $resumen[] = $this->xRow([$this->xCell('Nota mínima', 'String', 'sub'), $this->xCell((int) $meta['notaMinima'], 'Number')]);
        foreach ($contexto['filtros'] as $kk => $vv) {
            $resumen[] = $this->xRow([$this->xCell($kk, 'String', 'sub'), $this->xCell($vv)]);
        }
        $resumen[] = $this->xRow([]);
        $resumen[] = $this->xRow([$this->xCell('INDICADOR', 'String', 'head'), $this->xCell('VALOR', 'String', 'head')]);
        $kpis = [
            ['Inscritos', $k['inscritos']], ['Evaluados', $k['evaluados']],
            ['Aprobados', $k['aprobados']], ['Reprobados', $k['reprobados']],
            ['Incompletos', $k['incompletos']], ['% Aprobación', $k['pctAprobacion']],
            ['Promedio general', $k['promedioGeneral']], ['Grupos habilitados', $k['grupos']],
            ['Docentes', $k['docentes']],
        ];
        foreach ($kpis as $kp) {
            $resumen[] = $this->xRow([$this->xCell($kp[0]), $this->xCell((float) $kp[1], 'Number')]);
        }

        // ---- Hoja Postulantes ----
        $cab = array_merge(['CI', 'Postulante', 'Email', 'Carrera'], $materias, ['Promedio', 'Estado']);
        $post = [$this->xRow(array_map(fn ($h) => $this->xCell($h, 'String', 'head'), $cab))];
        foreach ($payload['detalle'] as $r) {
            $cells = [$this->xCell($r['ci']), $this->xCell($r['nombre']), $this->xCell($r['email'] ?? ''), $this->xCell($r['carrera'])];
            foreach ($materias as $mat) {
                $cells[] = isset($r['notas'][$mat]) ? $this->xCell((int) $r['notas'][$mat], 'Number') : $this->xCell('-');
            }
            $cells[] = $r['promedio'] === null ? $this->xCell('-') : $this->xCell((int) $r['promedio'], 'Number');
            $cells[] = $this->xCell(ucfirst(strtolower((string) $r['estado'])));
            $post[] = $this->xRow($cells);
        }

        // ---- Hoja Por materia ----
        $porMat = [$this->xRow([$this->xCell('Materia', 'String', 'head'), $this->xCell('Promedio', 'String', 'head'), $this->xCell('Aprobados', 'String', 'head'), $this->xCell('Reprobados', 'String', 'head')])];
        foreach ($payload['promedioPorMateria'] as $m) {
            $porMat[] = $this->xRow([$this->xCell($m['materia']), $this->xCell((int) $m['promedio'], 'Number'), $this->xCell((int) $m['aprobados'], 'Number'), $this->xCell((int) $m['reprobados'], 'Number')]);
        }

        // ---- Hoja Por carrera ----
        $porCarr = [$this->xRow([$this->xCell('Carrera', 'String', 'head'), $this->xCell('Inscritos', 'String', 'head')])];
        foreach ($payload['inscritosPorCarrera'] as $c) {
            $porCarr[] = $this->xRow([$this->xCell($c['carrera']), $this->xCell((int) $c['total'], 'Number')]);
        }

        // ---- Hoja Grupos ----
        $grp = [$this->xRow([$this->xCell('Grupo', 'String', 'head'), $this->xCell('Total', 'String', 'head'), $this->xCell('Aprobados', 'String', 'head'), $this->xCell('Reprobados', 'String', 'head'), $this->xCell('Incompletos', 'String', 'head')])];
        foreach (($payload['grupos'] ?? []) as $g) {
            $grp[] = $this->xRow([$this->xCell($g['grupo']), $this->xCell((int) $g['total'], 'Number'), $this->xCell((int) $g['aprobados'], 'Number'), $this->xCell((int) $g['reprobados'], 'Number'), $this->xCell((int) $g['incompletos'], 'Number')]);
        }

        // ---- Hoja Docentes ----
        $doc = [$this->xRow([$this->xCell('Docente', 'String', 'head'), $this->xCell('Grupos a cargo', 'String', 'head')])];
        foreach ($payload['docentesPorGrupo'] as $d) {
            $doc[] = $this->xRow([$this->xCell($d['docente']), $this->xCell((int) $d['grupos'], 'Number')]);
        }

        $sheets = $this->xSheet('Resumen', $resumen)
            . $this->xSheet('Postulantes', $post)
            . $this->xSheet('Por materia', $porMat)
            . $this->xSheet('Por carrera', $porCarr)
            . $this->xSheet('Grupos', $grp)
            . $this->xSheet('Docentes', $doc);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n"
            . '<?mso-application progid="Excel.Sheet"?>' . "\r\n"
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
            . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\r\n"
            . '<Styles>'
            . '<Style ss:ID="title"><Font ss:Bold="1" ss:Size="13" ss:Color="#1E3A8A"/></Style>'
            . '<Style ss:ID="head"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E3A8A" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>'
            . '<Style ss:ID="sub"><Font ss:Bold="1"/><Interior ss:Color="#EEF2FB" ss:Pattern="Solid"/></Style>'
            . '</Styles>'
            . $sheets
            . '</Workbook>';
    }

    /** @param list<string> $cells */
    private function csvLine(array $cells): string
    {
        return implode(',', array_map(static function (string $c): string {
            return '"' . str_replace('"', '""', $c) . '"';
        }, $cells));
    }

    private function xCell(string|int|float $value, string $type = 'String', ?string $style = null): string
    {
        $styleAttr = $style !== null ? ' ss:StyleID="' . $style . '"' : '';
        $data = $type === 'Number' ? (string) $value : $this->xe((string) $value);

        return '<Cell' . $styleAttr . '><Data ss:Type="' . $type . '">' . $data . '</Data></Cell>';
    }

    /** @param list<string> $cellsXml */
    private function xRow(array $cellsXml): string
    {
        return '<Row>' . implode('', $cellsXml) . '</Row>';
    }

    /** @param list<string> $rowsXml */
    private function xSheet(string $name, array $rowsXml): string
    {
        return '<Worksheet ss:Name="' . $this->xe($name) . '"><Table>' . implode('', $rowsXml) . '</Table></Worksheet>';
    }

    private function xe(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
