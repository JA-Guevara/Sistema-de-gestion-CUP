<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;

/**
 * OBSOLETO: el pago ya NO se confirma de forma simulada aqui. El cobro real se
 * realiza por la pasarela (Stripe Checkout) en IniciarPagoInscripcion +
 * ConfirmarPagoStripe, que recien confirman la inscripcion al recibir el pago.
 *
 * Se conserva para que la ruta antigua (inscripcion_pagar) no confirme sin pago:
 * cualquier intento directo es rechazado. Pendiente: eliminar la ruta/clase
 * cuando el InscripcionController deje de estar en edicion.
 */
final readonly class PagarInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private ConfirmarInscripcion $confirmar,
    ) {
    }

    public function execute(int $inscripcionId, int $actorUserId): void
    {
        throw new InscripcionException(
            'El pago ahora se realiza por la pasarela. Abre tu inscripcion y usa el boton "Pagar arancel y confirmar inscripcion".',
        );
    }
}
