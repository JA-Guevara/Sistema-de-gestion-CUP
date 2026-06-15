<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Inscripcion\Domain\Exception\InscripcionException;

/**
 * Registra la respuesta final de una postulacion ya validada.
 *
 * APROBADO confirma la postulacion y asigna el rol correspondiente.
 * DESCARTADO/RECHAZADO cierra la postulacion como rechazada.
 */
final readonly class ResolverResultadoAdmision
{
    private const APROBADO = 'APROBADO';
    private const DESCARTADO = 'DESCARTADO';
    private const RECHAZADO = 'RECHAZADO';

    public function __construct(
        private ConfirmarInscripcion $confirmarInscripcion,
        private RechazarInscripcion $rechazarInscripcion,
    ) {
    }

    public function execute(int $inscripcionId, string $resultado, ?string $motivo, ?int $actorUserId): void
    {
        $resultado = $this->normalizeResultado($resultado);

        if ($resultado === self::APROBADO) {
            $this->confirmarInscripcion->execute($inscripcionId, $actorUserId);

            return;
        }

        $this->rechazarInscripcion->execute($inscripcionId, $this->motivoDescartado($motivo), $actorUserId);
    }

    private function normalizeResultado(string $resultado): string
    {
        $resultado = strtoupper(trim($resultado));
        if (!in_array($resultado, [self::APROBADO, self::DESCARTADO, self::RECHAZADO], true)) {
            throw new InscripcionException('Selecciona una respuesta valida para la postulacion.');
        }

        return $resultado;
    }

    private function motivoDescartado(?string $motivo): string
    {
        $motivo = $motivo !== null ? trim($motivo) : '';

        return $motivo !== '' ? $motivo : 'Descartado en revision administrativa.';
    }
}
