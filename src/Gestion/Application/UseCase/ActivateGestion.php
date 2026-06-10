<?php

declare(strict_types=1);

namespace App\Gestion\Application\UseCase;

use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Gestion\Application\DTO\GestionActionInput;
use App\Gestion\Domain\Catalog\EstadoGestion;
use App\Gestion\Domain\Catalog\TipoAccionGestion;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Domain\Entity\HistorialGestion;
use App\Gestion\Domain\Exception\GestionException;
use App\Gestion\Infrastructure\Persistence\GestionRepository;

final readonly class ActivateGestion
{
    public function __construct(
        private GestionRepository $gestiones,
        private RecordLogEntry $audit,
    ) {
    }

    public function execute(GestionActionInput $input): Gestion
    {
        $gestion = $this->loadGestion($input);
        $this->validateGestionState($gestion);
        $this->validateConfiguration($gestion);
        $this->activateGestion($gestion, $input);
        $this->saveGestion($gestion);
        $this->registerAudit($gestion, $input);
        $this->notifyGestionActivated($gestion);

        return $gestion;
    }

    private function loadGestion(GestionActionInput $input): Gestion
    {
        $gestion = $this->gestiones->findById($input->gestionId);
        if ($gestion === null) {
            throw new GestionException('La gestion solicitada no existe.');
        }

        return $gestion;
    }

    private function validateGestionState(Gestion $gestion): void
    {
        if ($gestion->estado === EstadoGestion::FINALIZADA || $gestion->estado === EstadoGestion::CANCELADA) {
            throw new GestionException('No se puede activar una gestion finalizada o cancelada.');
        }
    }

    private function validateConfiguration(Gestion $gestion): void
    {
        if ($gestion->configuracion === null) {
            throw new GestionException('La gestion no tiene parametros academicos configurados.');
        }

        if ($gestion->periodos->isEmpty()) {
            throw new GestionException('La gestion no tiene cronograma academico.');
        }
    }

    private function activateGestion(Gestion $gestion, GestionActionInput $input): void
    {
        $this->gestiones->closeCurrentGestionesExcept($gestion);
        $gestion->activate($input->actorUserId ?? 0);
        $gestion->addHistory(HistorialGestion::create(
            TipoAccionGestion::ACTIVADA,
            'Gestion CUP activada.',
            $input->actorUserId,
        ));
    }

    private function saveGestion(Gestion $gestion): void
    {
        $this->gestiones->save($gestion);
    }

    private function registerAudit(Gestion $gestion, GestionActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::APPROVE, ModuleCatalog::GESTIONES_CUP, sprintf('Se activo la gestion %s.', $gestion->codigo), $input->actorUserId);
    }

    private function notifyGestionActivated(Gestion $gestion): void
    {
        // Punto de extension para notificar cambio de gestion activa.
    }
}
