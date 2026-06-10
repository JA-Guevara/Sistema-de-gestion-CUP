<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\UseCase;

use App\Academico\Aula\Application\DTO\AulasBulkStateInput;
use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Domain\Exception\AulaException;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class UpdateAulasStateBulk
{
    public const ACTION_ACTIVATE = 'ACTIVATE';
    public const ACTION_DEACTIVATE = 'DEACTIVATE';

    public function __construct(private AulaRepository $aulas, private RecordLogEntry $audit)
    {
    }

    /**
     * @return list<Aula>
     */
    public function execute(AulasBulkStateInput $input): array
    {
        $aulas = $this->loadAulas($input);
        $this->validateBusinessRules($input, $aulas);
        $this->updateAulasState($aulas, $input);
        $this->saveAulas($aulas);
        $this->registerAudit($aulas, $input);
        $this->notifyAulasStateChanged($aulas);

        return $aulas;
    }

    /**
     * @return list<Aula>
     */
    private function loadAulas(AulasBulkStateInput $input): array
    {
        return $this->aulas->findByIds($input->aulaIds);
    }

    /**
     * @param list<Aula> $aulas
     */
    private function validateBusinessRules(AulasBulkStateInput $input, array $aulas): void
    {
        if ($input->aulaIds === []) {
            throw new AulaException('Debe seleccionar al menos un aula.');
        }

        if (!in_array($input->accion, [self::ACTION_ACTIVATE, self::ACTION_DEACTIVATE], true)) {
            throw new AulaException('La accion masiva de aulas no es valida.');
        }

        if (count($aulas) !== count(array_unique($input->aulaIds))) {
            throw new AulaException('Una o mas aulas seleccionadas no existen.');
        }
    }

    /**
     * @param list<Aula> $aulas
     */
    private function updateAulasState(array $aulas, AulasBulkStateInput $input): void
    {
        foreach ($aulas as $aula) {
            $input->accion === self::ACTION_ACTIVATE ? $aula->activate() : $aula->deactivate();
        }
    }

    /**
     * @param list<Aula> $aulas
     */
    private function saveAulas(array $aulas): void
    {
        $this->aulas->saveMany($aulas);
    }

    /**
     * @param list<Aula> $aulas
     */
    private function registerAudit(array $aulas, AulasBulkStateInput $input): void
    {
        $action = $input->accion === self::ACTION_ACTIVATE ? ActionCatalog::ACTIVATE : ActionCatalog::DEACTIVATE;
        $this->audit->execute($action, ModuleCatalog::AULAS, sprintf('Se actualizaron %d aulas de forma masiva.', count($aulas)), $input->actorUserId);
    }

    /**
     * @param list<Aula> $aulas
     */
    private function notifyAulasStateChanged(array $aulas): void
    {
        // Punto de extension.
    }
}
