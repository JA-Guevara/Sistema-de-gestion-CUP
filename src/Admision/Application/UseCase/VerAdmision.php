<?php

declare(strict_types=1);

namespace App\Admision\Application\UseCase;

use App\Admision\Application\Service\EvaluadorAcademico;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\EstadoInscripcion;
use App\Inscripcion\Domain\Catalog\ResultadoAdmision;
use App\Inscripcion\Domain\Catalog\TipoPostulacion;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Arma la vista de la Admision Final (carrusel Estudiantes + Docentes):
 * - Estudiantes: promedio, estado y carrera adjudicada + uso de cupo por carrera.
 * - Docentes: postulaciones docentes con su fase de contratacion (decision final).
 */
final readonly class VerAdmision
{
    public function __construct(
        private GestionRepository $gestiones,
        private EvaluadorAcademico $evaluador,
        private InscripcionRepository $inscripciones,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function execute(int $gestionId): ?array
    {
        $gestion = $this->gestiones->findById($gestionId);
        if ($gestion === null) {
            return null;
        }

        $evaluados = $this->evaluador->evaluar($gestion);

        $admitidosPorCarrera = [];
        $procesado = false;
        $filas = [];
        foreach ($evaluados as $e) {
            $insc = $e['insc'];
            if ($insc->resultadoAdmision !== null) {
                $procesado = true;
            }
            if ($insc->carreraAdmitida !== null) {
                $cid = (int) $insc->carreraAdmitida->id;
                $admitidosPorCarrera[$cid] = ($admitidosPorCarrera[$cid] ?? 0) + 1;
            }

            $filas[] = [
                'ci' => $insc->ci,
                'estudiante' => trim($insc->apellidos . ' ' . $insc->nombres),
                'promedio' => $e['promedio'],
                'estado' => $e['estado'],
                'carreraPrimera' => $insc->carrera?->nombre,
                'carreraSegunda' => $insc->carreraSegunda?->nombre,
                'resultado' => $insc->resultadoAdmision,
                'resultadoLabel' => ResultadoAdmision::label($insc->resultadoAdmision),
                'carreraAdmitida' => $insc->carreraAdmitida?->nombre,
            ];
        }

        $cupos = [];
        foreach ($gestion->carreras as $cg) {
            if (!$cg->habilitada) {
                continue;
            }
            $cid = (int) $cg->carrera->id;
            $cupos[] = [
                'carrera' => $cg->carrera->nombre,
                'cupo' => $cg->cupoCarrera,
                'admitidos' => $admitidosPorCarrera[$cid] ?? 0,
                'disponibles' => max(0, $cg->cupoCarrera - ($admitidosPorCarrera[$cid] ?? 0)),
            ];
        }

        return [
            'gestion' => $gestion,
            'filas' => $filas,
            'cupos' => $cupos,
            'procesado' => $procesado,
            'totalEstudiantes' => count($filas),
            'docentes' => $this->docentes($gestionId),
        ];
    }

    /**
     * Postulaciones de docentes con su fase de decision final (contratacion).
     *
     * @return list<array{insc: \App\Inscripcion\Domain\Entity\Inscripcion, fase: string, puedeResolver: bool}>
     */
    private function docentes(int $gestionId): array
    {
        $out = [];
        foreach ($this->inscripciones->listByGestionTipo($gestionId, TipoPostulacion::DOCENTE) as $insc) {
            $out[] = [
                'insc' => $insc,
                'fase' => $this->faseDocente($insc->estado),
                // Decision final solo si esta validado y con entrevista agendada.
                'puedeResolver' => $insc->estado === EstadoInscripcion::VALIDADA && $insc->fechaEntrevista !== null,
            ];
        }

        return $out;
    }

    private function faseDocente(string $estado): string
    {
        return match ($estado) {
            EstadoInscripcion::CONFIRMADA => 'Contratado',
            EstadoInscripcion::RECHAZADA => 'Descartado',
            EstadoInscripcion::VALIDADA => 'Pendiente de decision',
            EstadoInscripcion::ANULADA => 'Anulado',
            default => 'En revision',
        };
    }
}
