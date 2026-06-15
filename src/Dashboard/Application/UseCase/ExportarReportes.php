<?php

declare(strict_types=1);

namespace App\Dashboard\Application\UseCase;

use App\Inscripcion\Domain\Catalog\EstadoInscripcion;

/**
 * Exporta el payload del dashboard en CSV estructurado y Excel multi-hoja.
 * Separa claramente:
 *  - resumen ejecutivo,
 *  - postulaciones por estado (estudiantes y docentes),
 *  - resultados académicos,
 *  - detalle de postulantes,
 *  - datos por materia, carrera, grupo y docente.
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
        $sections = [];

        $sections[] = $this->section('REPORTE EJECUTIVO', [
            ['Campo', 'Valor'],
            ['Título', $contexto['titulo']],
            ['Gestión', $contexto['gestion']],
            ['Generado', $contexto['fecha']],
            ['Carrera', $contexto['filtros']['Carrera'] ?? 'Todas'],
            ['Materia', $contexto['filtros']['Materia'] ?? 'Todas'],
            ['Docente', $contexto['filtros']['Docente'] ?? 'Todos'],
        ]);

        $k = $this->array($payload, 'kpis');
        $sections[] = $this->section('POSTULACIONES Y RESULTADOS', [
            ['Indicador', 'Valor'],
            ['Estudiantes inscritos', (string) $k['postulacionesTotales']],
            ['Estudiantes aprobados', (string) $k['estudiantesAprobados']],
            ['Estudiantes rechazados', (string) $k['estudiantesRechazados']],
            ['Estudiantes en proceso', (string) $k['estudiantesEnProceso']],
            ['Docentes postulados', (string) $k['postulacionesDocentes']],
            ['Docentes aprobados', (string) $k['docentesAprobados']],
            ['Docentes rechazados', (string) $k['docentesRechazados']],
            ['Docentes en proceso', (string) $k['docentesEnProceso']],
            ['Aprobados por notas', (string) $k['aprobados']],
            ['Reprobados por notas', (string) $k['reprobados']],
            ['Sin notas completas', (string) $k['incompletos']],
            ['Evaluados con nota completa', (string) $k['evaluados']],
            ['Promedio general', (string) $k['promedioGeneral']],
            ['% aprobacion por notas', $k['pctAprobacion'] . '%'],
        ]);

        $sections[] = $this->section('POSTULACIONES DE ESTUDIANTES POR ESTADO', $this->estadoRows($payload['postulaciones']['estudiantes'] ?? []));
        $sections[] = $this->section('POSTULACIONES DE DOCENTES POR ESTADO', $this->estadoRows($payload['postulaciones']['docentes'] ?? []));

        $materias = $this->array($payload, 'promedioPorMateria');
        $rows = [['Materia', 'Promedio', 'Aprobados', 'Reprobados']];
        foreach ($materias as $m) {
            $rows[] = [$m['materia'], (string) $m['promedio'], (string) $m['aprobados'], (string) $m['reprobados']];
        }
        $sections[] = $this->section('RESULTADOS POR MATERIA', $rows);

        $carreras = $this->array($payload, 'inscritosPorCarrera');
        $rows = [['Carrera', 'Postulantes']];
        foreach ($carreras as $c) {
            $rows[] = [$c['carrera'], (string) $c['total']];
        }
        $sections[] = $this->section('POSTULANTES POR CARRERA', $rows);

        $detalle = $this->array($payload, 'detalle');
        $detalleRows = [['CI', 'Postulante', 'Carrera', 'Estado postulación', 'Estado académico', 'Promedio', 'Materias asignadas', 'Email']];
        foreach ($detalle as $r) {
            $detalleRows[] = [
                (string) $r['ci'],
                (string) $r['nombre'],
                (string) $r['carrera'],
                EstadoInscripcion::label((string) ($r['estadoInscripcion'] ?? '')),
                (string) $r['estado'],
                $r['promedio'] === null ? '-' : (string) $r['promedio'],
                (string) ($r['materias'] ?? 0),
                (string) ($r['email'] ?? ''),
            ];
        }
        $sections[] = $this->section('DETALLE DE POSTULANTES', $detalleRows);

        $academicoRows = [['CI', 'Postulante', 'Materia', 'Grupo', 'Docente', 'Promedio materia', 'Notas']];
        foreach ($detalle as $r) {
            foreach (($r['asignaciones'] ?? []) as $a) {
                $notaList = isset($a['notas']) && is_array($a['notas']) ? implode(' / ', array_map('strval', $a['notas'])) : '-';
                $academicoRows[] = [
                    (string) $r['ci'],
                    (string) $r['nombre'],
                    (string) ($a['materia'] ?? '-'),
                    (string) ($a['grupo'] ?? '-'),
                    (string) ($a['docente'] ?? '-'),
                    $notaList === '-' ? '-' : (string) round(array_sum(array_map('intval', (array) $a['notas'])) / max(1, count((array) $a['notas']))),
                    $notaList,
                ];
            }
        }
        $sections[] = $this->section('DETALLE ACADÉMICO POR MATERIA', $academicoRows);

        $grupos = $this->array($payload, 'grupos');
        $rows = [['Grupo', 'Total', 'Aprobados', 'Reprobados', 'Incompletos']];
        foreach ($grupos as $g) {
            $rows[] = [$g['grupo'], (string) $g['total'], (string) $g['aprobados'], (string) $g['reprobados'], (string) $g['incompletos']];
        }
        $sections[] = $this->section('GRUPOS', $rows);

        $docentes = $this->array($payload, 'docentesPorGrupo');
        $rows = [['Docente', 'Grupos a cargo']];
        foreach ($docentes as $d) {
            $rows[] = [$d['docente'], (string) $d['grupos']];
        }
        $sections[] = $this->section('CARGA DOCENTE', $rows);

        return implode("\r\n", array_filter($sections, static fn (string $s): bool => $s !== '')) . "\r\n";
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $contexto
     */
    public function excel(array $payload, array $contexto): string
    {
        $meta = $this->array($payload, 'meta');
        $k = $this->array($payload, 'kpis');
        $detalle = $this->array($payload, 'detalle');

        $resumen = $this->sheetRows([
            [$this->xCell($contexto['titulo'], 'String', 'title')],
            [$this->xCell('Gestión', 'String', 'sub'), $this->xCell($contexto['gestion'])],
            [$this->xCell('Generado', 'String', 'sub'), $this->xCell($contexto['fecha'])],
            [$this->xCell('Carrera', 'String', 'sub'), $this->xCell($contexto['filtros']['Carrera'] ?? 'Todas')],
            [$this->xCell('Materia', 'String', 'sub'), $this->xCell($contexto['filtros']['Materia'] ?? 'Todas')],
            [$this->xCell('Docente', 'String', 'sub'), $this->xCell($contexto['filtros']['Docente'] ?? 'Todos')],
            [],
            [$this->xCell('Indicador', 'String', 'head'), $this->xCell('Valor', 'String', 'head')],
            [$this->xCell('Estudiantes inscritos'), $this->xCell((int) $k['postulacionesTotales'], 'Number')],
            [$this->xCell('Estudiantes aprobados'), $this->xCell((int) $k['estudiantesAprobados'], 'Number')],
            [$this->xCell('Estudiantes rechazados'), $this->xCell((int) $k['estudiantesRechazados'], 'Number')],
            [$this->xCell('Estudiantes en proceso'), $this->xCell((int) $k['estudiantesEnProceso'], 'Number')],
            [$this->xCell('Docentes postulados'), $this->xCell((int) $k['postulacionesDocentes'], 'Number')],
            [$this->xCell('Docentes aprobados'), $this->xCell((int) $k['docentesAprobados'], 'Number')],
            [$this->xCell('Docentes rechazados'), $this->xCell((int) $k['docentesRechazados'], 'Number')],
            [$this->xCell('Docentes en proceso'), $this->xCell((int) $k['docentesEnProceso'], 'Number')],
            [$this->xCell('Evaluados con notas'), $this->xCell((int) $k['evaluados'], 'Number')],
            [$this->xCell('Aprobados por notas'), $this->xCell((int) $k['aprobados'], 'Number')],
            [$this->xCell('Reprobados por notas'), $this->xCell((int) $k['reprobados'], 'Number')],
            [$this->xCell('Sin notas completas'), $this->xCell((int) $k['incompletos'], 'Number')],
            [$this->xCell('Promedio general'), $this->xCell((int) $k['promedioGeneral'], 'Number')],
            [$this->xCell('% aprobacion por notas'), $this->xCell((float) $k['pctAprobacion'], 'Number')],
        ]);

        $postulacionesEst = $this->sheetRows($this->estadoRows($payload['postulaciones']['estudiantes'] ?? []), true);
        $postulacionesDoc = $this->sheetRows($this->estadoRows($payload['postulaciones']['docentes'] ?? []), true);

        $porMateria = $this->sheetRows($this->rowsFromKeyedTable('Materia', 'Promedio', 'Aprobados', 'Reprobados', $this->array($payload, 'promedioPorMateria')));
        $porCarrera = $this->sheetRows($this->rowsFromKeyedTable('Carrera', 'Postulantes', null, null, $this->array($payload, 'inscritosPorCarrera')));
        $grupos = $this->sheetRows($this->rowsFromKeyedTable('Grupo', 'Total', 'Aprobados', 'Reprobados', $this->array($payload, 'grupos'), 'Incompletos'));
        $docentes = $this->sheetRows($this->rowsFromKeyedTable('Docente', 'Grupos a cargo', null, null, $this->array($payload, 'docentesPorGrupo')));

        $postRows = [['CI', 'Postulante', 'Carrera', 'Estado postulación', 'Estado académico', 'Promedio', 'Materias asignadas', 'Email']];
        foreach ($detalle as $r) {
            $postRows[] = [
                $this->xCell((string) $r['ci']),
                $this->xCell((string) $r['nombre']),
                $this->xCell((string) $r['carrera']),
                $this->xCell(EstadoInscripcion::label((string) ($r['estadoInscripcion'] ?? ''))),
                $this->xCell((string) $r['estado']),
                $r['promedio'] === null ? $this->xCell('-') : $this->xCell((int) $r['promedio'], 'Number'),
                $this->xCell((int) ($r['materias'] ?? 0), 'Number'),
                $this->xCell((string) ($r['email'] ?? '')),
            ];
        }

        $academicoRows = [['CI', 'Postulante', 'Materia', 'Grupo', 'Docente', 'Promedio materia', 'Notas']];
        foreach ($detalle as $r) {
            foreach (($r['asignaciones'] ?? []) as $a) {
                $notas = isset($a['notas']) && is_array($a['notas']) ? array_map('intval', $a['notas']) : [];
                $academicoRows[] = [
                    $this->xCell((string) $r['ci']),
                    $this->xCell((string) $r['nombre']),
                    $this->xCell((string) ($a['materia'] ?? '-')),
                    $this->xCell((string) ($a['grupo'] ?? '-')),
                    $this->xCell((string) ($a['docente'] ?? '-')),
                    $notas !== [] ? $this->xCell((int) round(array_sum($notas) / count($notas)), 'Number') : $this->xCell('-'),
                    $this->xCell($notas !== [] ? implode(' / ', array_map('strval', $notas)) : '-'),
                ];
            }
        }

        $sheets = $this->xSheet('Resumen', $resumen)
            . $this->xSheet('Estados Estudiantes', $postulacionesEst)
            . $this->xSheet('Estados Docentes', $postulacionesDoc)
            . $this->xSheet('Postulantes', $this->sheetRows($postRows, true))
            . $this->xSheet('Detalle Academico', $this->sheetRows($academicoRows, true))
            . $this->xSheet('Por materia', $porMateria)
            . $this->xSheet('Por carrera', $porCarrera)
            . $this->xSheet('Grupos', $grupos)
            . $this->xSheet('Docentes', $docentes);

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

    /**
     * @param list<array{0:string,1:string}>|list<array<int, string>> $rows
     */
    private function section(string $title, array $rows): string
    {
        $lines = [$this->csvLine([$title])];
        foreach ($rows as $row) {
            $lines[] = $this->csvLine(array_map(static fn ($value): string => (string) $value, $row));
        }
        $lines[] = '';

        return implode("\r\n", $lines);
    }

    /** @return array<string, mixed> */
    private function array(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, int|string> $estadoMap
     * @return list<array<int, string>>
     */
    private function estadoRows(array $estadoMap): array
    {
        $order = [
            EstadoInscripcion::CONFIRMADA,
            EstadoInscripcion::VALIDADA,
            EstadoInscripcion::PRESENTADA,
            EstadoInscripcion::BORRADOR,
            EstadoInscripcion::RECHAZADA,
            EstadoInscripcion::ANULADA,
            EstadoInscripcion::PENDIENTE,
            EstadoInscripcion::COMPLETADA,
        ];

        $rows = [['Estado', 'Total']];
        foreach ($order as $estado) {
            if (!array_key_exists($estado, $estadoMap)) {
                continue;
            }
            $rows[] = [EstadoInscripcion::label($estado), (string) $estadoMap[$estado]];
        }

        return $rows;
    }

    /**
     * @param list<array{0:string,1:string}>|list<array<int, string>> $rows
     */
    private function sheetRows(array $rows, bool $hasHeader = false): array
    {
        $xml = [];
        foreach ($rows as $index => $row) {
            $cells = [];
            foreach ($row as $cell) {
                $cells[] = $hasHeader && $index === 0 ? $this->xCell((string) $cell, 'String', 'head') : $this->xCell((string) $cell);
            }
            $xml[] = $this->xRow($cells);
        }

        return $xml;
    }

    /**
     * @param array<string, mixed> $rows
     * @return list<array<int, string>>
     */
    private function rowsFromKeyedTable(string $col1, string $col2, ?string $col3, ?string $col4, array $rows, ?string $col5 = null): array
    {
        $out = [array_values(array_filter([$col1, $col2, $col3, $col4, $col5], static fn ($v) => $v !== null))];
        foreach ($rows as $row) {
            $line = [];
            $line[] = (string) ($row[$this->guessKey($row, $col1)] ?? $row[$this->guessKey($row, 'materia')] ?? $row[$this->guessKey($row, 'carrera')] ?? $row[$this->guessKey($row, 'grupo')] ?? $row[$this->guessKey($row, 'docente')] ?? '');
            $line[] = (string) ($row[$this->guessKey($row, $col2)] ?? $row['total'] ?? $row['promedio'] ?? $row['grupos'] ?? '0');
            if ($col3 !== null) {
                $line[] = (string) ($row['aprobados'] ?? '0');
            }
            if ($col4 !== null) {
                $line[] = (string) ($row['reprobados'] ?? '0');
            }
            if ($col5 !== null) {
                $line[] = (string) ($row['incompletos'] ?? '0');
            }
            $out[] = $line;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function guessKey(array $row, string $preferred): string
    {
        foreach ([$preferred, strtolower($preferred), ucfirst(strtolower($preferred)), 'materia', 'carrera', 'grupo', 'docente'] as $candidate) {
            if (array_key_exists($candidate, $row)) {
                return $candidate;
            }
        }

        return array_key_first($row) ?? $preferred;
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
