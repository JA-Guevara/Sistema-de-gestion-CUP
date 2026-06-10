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

final readonly class CloseInscription
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
        $this->validateBusinessRules($gestion);
        $this->closeInscription($gestion, $input);
        $this->saveGestion($gestion);
        $this->registerAudit($gestion, $input);
        $this->notifyInscriptionClosed($gestion);

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
        if ($gestion->estado !== EstadoGestion::ABIERTA_INSCRIPCION) {
            throw new GestionException('Solo se puede cerrar una inscripcion abierta.');
        }
    }

    private function validateBusinessRules(Gestion $gestion): void
    {
        if (!$gestion->isActive()) {
            throw new GestionException('La gestion debe estar activa para cerrar inscripcion.');
        }
    }

    private function closeInscription(Gestion $gestion, GestionActionInput $input): void
    {
        $gestion->closeInscription();
        $gestion->addHistory(HistorialGestion::create(
            TipoAccionGestion::INSCRIPCION_CERRADA,
            'Inscripcion CUP cerrada.',
            $input->actorUserId,
        ));
    }

    private function saveGestion(Gestion $gestion): void
    {
        $this->gestiones->save($gestion);
    }

    private function registerAudit(Gestion $gestion, GestionActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::UPDATE, ModuleCatalog::GESTIONES_CUP, sprintf('Se cerro inscripcion para %s.', $gestion->codigo), $input->actorUserId);
    }

    private function notifyInscriptionClosed(Gestion $gestion): void
    {
        // Punto de extension para notificar cierre de inscripciones.
    }
}
