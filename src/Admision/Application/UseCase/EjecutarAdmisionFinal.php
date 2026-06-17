<?php

declare(strict_types=1);

namespace App\Admision\Application\UseCase;

use App\Admision\Application\Service\EvaluadorAcademico;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Domain\Catalog\ResultadoAdmision;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * Admision Final: adjudica carrera a los estudiantes que APROBARON el CUP,
 * ordenados por promedio (mayor primero), respetando el cupo por carrera. Intenta
 * la 1ra opcion; si no hay cupo, la 2da; si tampoco, queda en lista de espera.
 * Reprobados e incompletos no consumen cupo. Es re-ejecutable (sobrescribe).
 */
final readonly class EjecutarAdmisionFinal
{
    public function __construct(
        private GestionRepository $gestiones,
        private InscripcionRepository $inscripciones,
        private EvaluadorAcademico $evaluador,
        private RecordLogEntry $audit,
    ) {
    }

    /** @return array{admitidosPrimera:int, admitidosSegunda:int, listaEspera:int, reprobados:int, pendientes:int} */
    public function execute(int $gestionId, ?int $actorUserId): array
    {
        $gestion = $this->gestiones->findById($gestionId);
        if ($gestion === null) {
            throw new InscripcionException('La gestion seleccionada no existe.');
        }

        $evaluados = $this->evaluador->evaluar($gestion); // ya viene ordenado por promedio desc

        $cupoRestante = [];
        foreach ($gestion->carreras as $cg) {
            if ($cg->habilitada) {
                $cupoRestante[(int) $cg->carrera->id] = $cg->cupoCarrera;
            }
        }

        $resumen = ['admitidosPrimera' => 0, 'admitidosSegunda' => 0, 'listaEspera' => 0, 'reprobados' => 0, 'pendientes' => 0];

        foreach ($evaluados as $e) {
            $insc = $e['insc'];

            if ($e['estado'] === EvaluadorAcademico::PENDIENTE) {
                $insc->resultadoAdmision = ResultadoAdmision::PENDIENTE;
                $insc->carreraAdmitida = null;
                $resumen['pendientes']++;
                continue;
            }

            if ($e['estado'] === EvaluadorAcademico::REPROBADO) {
                $insc->resultadoAdmision = ResultadoAdmision::REPROBADO;
                $insc->carreraAdmitida = null;
                $resumen['reprobados']++;
                continue;
            }

            // APROBADO: adjudicar 1ra -> 2da -> lista de espera.
            $primera = $insc->carrera;
            $segunda = $insc->carreraSegunda;

            if ($primera !== null && ($cupoRestante[(int) $primera->id] ?? 0) > 0) {
                $cupoRestante[(int) $primera->id]--;
                $insc->resultadoAdmision = ResultadoAdmision::ADMITIDO_PRIMERA;
                $insc->carreraAdmitida = $primera;
                $resumen['admitidosPrimera']++;
            } elseif ($segunda !== null && ($cupoRestante[(int) $segunda->id] ?? 0) > 0) {
                $cupoRestante[(int) $segunda->id]--;
                $insc->resultadoAdmision = ResultadoAdmision::ADMITIDO_SEGUNDA;
                $insc->carreraAdmitida = $segunda;
                $resumen['admitidosSegunda']++;
            } else {
                $insc->resultadoAdmision = ResultadoAdmision::LISTA_ESPERA;
                $insc->carreraAdmitida = null;
                $resumen['listaEspera']++;
            }
        }

        $this->inscripciones->flush();

        $this->audit->execute(
            ActionCatalog::APPROVE,
            ModuleCatalog::ADMISION,
            sprintf(
                'Ejecuto la Admision Final de %s: %d admitidos 1ra, %d admitidos 2da, %d en lista de espera, %d reprobados, %d pendientes.',
                $gestion->codigo,
                $resumen['admitidosPrimera'],
                $resumen['admitidosSegunda'],
                $resumen['listaEspera'],
                $resumen['reprobados'],
                $resumen['pendientes'],
            ),
            $actorUserId,
        );

        return $resumen;
    }
}
