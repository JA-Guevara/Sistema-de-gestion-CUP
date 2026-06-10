<?php

declare(strict_types=1);

namespace App\Academico\Grupo\Application\UseCase;

use App\Academico\Grupo\Application\DTO\GruposBulkStateInput;
use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Domain\Exception\GrupoException;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class UpdateGruposStateBulk
{
    public const ACTION_OPEN = 'OPEN';
    public const ACTION_CLOSE = 'CLOSE';

    public function __construct(private GrupoRepository $grupos, private RecordLogEntry $audit)
    {
    }

    /**
     * @return list<Grupo>
     */
    public function execute(GruposBulkStateInput $input): array
    {
        $grupos = $this->loadGrupos($input);
        $this->validateBusinessRules($input, $grupos);
        $this->updateGruposState($grupos, $input);
        $this->saveGrupos($grupos);
        $this->registerAudit($grupos, $input);
        $this->notifyGruposStateChanged($grupos);

        return $grupos;
    }

    /**
     * @return list<Grupo>
     */
    private function loadGrupos(GruposBulkStateInput $input): array
    {
        return $this->grupos->findByIds($input->grupoIds);
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function validateBusinessRules(GruposBulkStateInput $input, array $grupos): void
    {
        if ($input->grupoIds === []) {
            throw new GrupoException('Debe seleccionar al menos un grupo.');
        }

        if (!in_array($input->accion, [self::ACTION_OPEN, self::ACTION_CLOSE], true)) {
            throw new GrupoException('La accion masiva de grupos no es valida.');
        }

        if (count($grupos) !== count(array_unique($input->grupoIds))) {
            throw new GrupoException('Uno o mas grupos seleccionados no existen.');
        }
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function updateGruposState(array $grupos, GruposBulkStateInput $input): void
    {
        foreach ($grupos as $grupo) {
            $input->accion === self::ACTION_OPEN ? $grupo->open() : $grupo->close();
        }
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function saveGrupos(array $grupos): void
    {
        $this->grupos->saveMany($grupos);
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function registerAudit(array $grupos, GruposBulkStateInput $input): void
    {
        $action = $input->accion === self::ACTION_OPEN ? ActionCatalog::ACTIVATE : ActionCatalog::DEACTIVATE;
        $this->audit->execute($action, ModuleCatalog::GRUPOS, sprintf('Se actualizaron %d grupos de forma masiva.', count($grupos)), $input->actorUserId);
    }

    /**
     * @param list<Grupo> $grupos
     */
    private function notifyGruposStateChanged(array $grupos): void
    {
        // Punto de extension.
    }
}
