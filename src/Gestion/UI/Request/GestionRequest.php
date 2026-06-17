<?php

declare(strict_types=1);

namespace App\Gestion\UI\Request;

use App\Gestion\Application\DTO\CarreraGestionInput;
use App\Gestion\Application\DTO\GestionInput;
use App\Gestion\Application\DTO\PeriodoGestionInput;
use Symfony\Component\HttpFoundation\Request;

final class GestionRequest
{
    public static function fromRequest(Request $request): GestionInput
    {
        return new GestionInput(
            codigo: trim((string) $request->request->get('codigo', '')),
            nombre: trim((string) $request->request->get('nombre', '')),
            descripcion: self::nullableString($request->request->get('descripcion')),
            cupoTotal: (int) $request->request->get('cupoTotal', 0),
            maxEstudiantesPorGrupo: (int) $request->request->get('maxEstudiantesPorGrupo', 0),
            maxGruposPorDocente: (int) $request->request->get('maxGruposPorDocente', 0),
            notaMinimaAprobacion: (int) $request->request->get('notaMinimaAprobacion', 0),
            cantidadExamenes: (int) $request->request->get('cantidadExamenes', 0),
            ponderacionesExamenes: self::ponderacionesFromRequest($request),
            permiteReinscripcion: $request->request->has('permiteReinscripcion'),
            permiteCambioGrupo: $request->request->has('permiteCambioGrupo'),
            generarBitacora: $request->request->has('generarBitacora'),
            periodos: self::periodsFromRequest($request),
            carreras: self::careersFromRequest($request),
            actorUserId: self::actorUserId($request),
        );
    }

    /** @return list<PeriodoGestionInput> */
    private static function periodsFromRequest(Request $request): array
    {
        $submittedPeriods = $request->request->all('periodos');
        $periods = [];

        foreach ($submittedPeriods as $row) {
            if (!is_array($row)) {
                continue;
            }

            $actividad = trim((string) ($row['actividad'] ?? ''));
            if ($actividad === '' && empty($row['inicio']) && empty($row['fin'])) {
                continue;
            }

            $periods[] = new PeriodoGestionInput(
                $actividad,
                self::nullableDate($row['inicio'] ?? null),
                self::nullableDate($row['fin'] ?? null),
            );
        }

        return $periods;
    }

    /** @return list<CarreraGestionInput> */
    private static function careersFromRequest(Request $request): array
    {
        $submittedCareers = $request->request->all('carreras');
        $careers = [];

        foreach ($submittedCareers as $row) {
            if (!is_array($row) || !isset($row['habilitada'])) {
                continue;
            }

            $careers[] = new CarreraGestionInput(
                (int) ($row['id'] ?? 0),
                true,
                (int) ($row['cupo'] ?? 0),
            );
        }

        return $careers;
    }

    /**
     * Pesos por examen (examen_1..N). Solo se devuelven los numericos; si quedan
     * vacios, el promedio sera simple. La suma (=100) se valida en el use case.
     *
     * @return array<string,float>
     */
    private static function ponderacionesFromRequest(Request $request): array
    {
        $submitted = $request->request->all('ponderaciones');
        $ponderaciones = [];

        foreach ($submitted as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            if (is_numeric($value)) {
                $ponderaciones[(string) $key] = (float) $value;
            }
        }

        return $ponderaciones;
    }

    private static function nullableDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return new \DateTimeImmutable(trim($value));
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function actorUserId(Request $request): ?int
    {
        $userId = $request->getSession()->get('auth_user_id');

        return is_int($userId) ? $userId : null;
    }
}
