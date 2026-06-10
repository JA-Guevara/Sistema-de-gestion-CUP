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

final readonly class OpenInscription
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
        $this->validatePeriods($gestion);
        $this->validateCareers($gestion);
        $this->openInscription($gestion, $input);
        $this->saveGestion($gestion);
        $this->registerAudit($gestion, $input);
        $this->notifyInscriptionOpened($gestion);

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
        if (!$gestion->isActive()) {
            throw new GestionException('Solo la gestion activa puede abrir inscripciones.');
        }

        if (!in_array($gestion->estado, [EstadoGestion::CONFIGURACION, EstadoGestion::BORRADOR], true)) {
            throw new GestionException('La gestion no esta en estado configurable para abrir inscripcion.');
        }
    }

    private function validateConfiguration(Gestion $gestion): void
    {
        if ($gestion->configuracion === null || $gestion->configuracion->cupoTotal <= 0) {
            throw new GestionException('Configure cupos y parametros antes de abrir inscripcion.');
        }
    }

    private function validatePeriods(Gestion $gestion): void
    {
        foreach ($gestion->periodos as $periodo) {
            if (str_contains($this->normalizeActivity($periodo->tipoPeriodo), 'INSCRIPCION')) {
                return;
            }
        }

        throw new GestionException('Debe configurar el periodo de inscripcion.');
    }

    private function normalizeActivity(string $activity): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $activity);

        return mb_strtoupper($normalized !== false ? $normalized : $activity);
    }

    private function validateCareers(Gestion $gestion): void
    {
        foreach ($gestion->carreras as $carrera) {
            if ($carrera->habilitada && $carrera->cupoCarrera > 0) {
                return;
            }
        }

        throw new GestionException('Debe habilitar al menos una carrera con cupo.');
    }

    private function openInscription(Gestion $gestion, GestionActionInput $input): void
    {
        $gestion->openInscription();
        $gestion->addHistory(HistorialGestion::create(
            TipoAccionGestion::INSCRIPCION_ABIERTA,
            'Inscripcion CUP abierta.',
            $input->actorUserId,
        ));
    }

    private function saveGestion(Gestion $gestion): void
    {
        $this->gestiones->save($gestion);
    }

    private function registerAudit(Gestion $gestion, GestionActionInput $input): void
    {
        $this->audit->execute(ActionCatalog::APPROVE, ModuleCatalog::GESTIONES_CUP, sprintf('Se abrio inscripcion para %s.', $gestion->codigo), $input->actorUserId);
    }

    private function notifyInscriptionOpened(Gestion $gestion): void
    {
        // Punto de extension para notificar apertura de inscripciones.
    }
}
