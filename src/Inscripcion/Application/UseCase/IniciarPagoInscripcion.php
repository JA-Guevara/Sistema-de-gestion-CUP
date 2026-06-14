<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\PagoEvents;
use App\Inscripcion\Domain\Entity\Pago;
use App\Inscripcion\Domain\Exception\InscripcionException;
use App\Inscripcion\Infrastructure\Payment\StripeGateway;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Inscripcion\Infrastructure\Persistence\PagoRepository;

/**
 * Inicia el pago del arancel de una postulacion VALIDADA de estudiante:
 * crea un Pago PENDIENTE y una sesion de Stripe Checkout, y devuelve la URL de
 * pago a la que se redirige al postulante. La confirmacion real (y la asignacion
 * del rol) ocurre en ConfirmarPagoStripe via webhook o pagina de exito.
 */
final readonly class IniciarPagoInscripcion
{
    public function __construct(
        private InscripcionRepository $inscripciones,
        private PagoRepository $pagos,
        private StripeGateway $stripe,
        private PagoEvents $events,
        private string $stripeInscripcionFeeBob,
        private string $stripeCurrency,
    ) {
    }

    public function execute(int $inscripcionId, int $actorUserId, string $successUrl, string $cancelUrl): string
    {
        $inscripcion = $this->inscripciones->findById($inscripcionId);
        if ($inscripcion === null) {
            throw new InscripcionException('La postulacion no existe.');
        }

        if ($inscripcion->user->id !== $actorUserId) {
            throw new InscripcionException('No puedes pagar una postulacion que no es tuya.');
        }

        if (!$inscripcion->esEstudiante()) {
            throw new InscripcionException('El pago solo aplica a postulaciones de estudiante.');
        }

        if ($inscripcion->isConfirmada()) {
            throw new InscripcionException('Esta postulacion ya esta confirmada.');
        }

        if (!$inscripcion->isValidada()) {
            throw new InscripcionException('El pago se habilita cuando tu documentacion fue validada (aprobada).');
        }

        if ($this->pagos->findPagadoByInscripcion($inscripcionId) !== null) {
            throw new InscripcionException('Esta inscripcion ya fue pagada.');
        }

        if (!$this->stripe->isConfigured()) {
            throw new InscripcionException('La pasarela de pago no esta disponible en este momento. Intenta mas tarde.');
        }

        $pago = new Pago();
        $pago->inscripcion = $inscripcion;
        $pago->userId = $actorUserId;
        $pago->email = $inscripcion->user->email;
        $pago->amount = $this->feeEnCentavos();
        $pago->currency = strtoupper(trim($this->stripeCurrency) !== '' ? trim($this->stripeCurrency) : 'BOB');
        $this->pagos->save($pago);

        $descripcion = sprintf(
            'Arancel de inscripcion CUP - %s %s (CI %s)',
            $inscripcion->nombres,
            $inscripcion->apellidos,
            $inscripcion->ci,
        );

        try {
            $session = $this->stripe->createCheckoutSession($pago, $successUrl, $cancelUrl, $descripcion);
        } catch (\Throwable $e) {
            throw new InscripcionException('No se pudo iniciar el pago con la pasarela. ' . $e->getMessage());
        }

        $pago->stripeSessionId = $session->id;
        $this->pagos->save($pago);

        $this->events->pagoIniciado($inscripcion->ci, $pago->montoDecimal(), $pago->currency, $actorUserId);

        $url = $session->url;
        if (!is_string($url) || $url === '') {
            throw new InscripcionException('La pasarela no devolvio una URL de pago valida.');
        }

        return $url;
    }

    private function feeEnCentavos(): int
    {
        $fee = (float) str_replace(',', '.', trim($this->stripeInscripcionFeeBob));
        if ($fee <= 0) {
            $fee = 100.0; // Valor por defecto defensivo si no se configuro el arancel.
        }

        return (int) round($fee * 100);
    }
}
