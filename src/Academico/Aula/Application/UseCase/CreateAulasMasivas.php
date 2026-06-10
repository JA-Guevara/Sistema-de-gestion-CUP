<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\UseCase;

use App\Academico\Aula\Application\DTO\AulasMasivasInput;
use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Domain\Exception\AulaException;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;

final readonly class CreateAulasMasivas
{
    private const MAX_AULAS_POR_LOTE = 80;

    public function __construct(private AulaRepository $aulas, private RecordLogEntry $audit)
    {
    }

    /** @return list<Aula> */
    public function execute(AulasMasivasInput $input): array
    {
        $codes = $this->loadRequestedCodes($input);
        $existingCodes = $this->loadExistingCodes($codes);
        $this->validateRange($input);
        $this->validateConfiguration($input);
        $this->validateBusinessRules($existingCodes);
        $aulas = $this->createAulas($input, $codes);
        $this->saveAulas($aulas);
        $this->registerAudit($aulas, $input);
        $this->notifyAulasCreated($aulas);

        return $aulas;
    }

    /** @return list<string> */
    private function loadRequestedCodes(AulasMasivasInput $input): array
    {
        $codes = [];
        for ($number = $input->numeroInicio; $number <= $input->numeroFin; $number++) {
            $codes[] = $this->buildCodigo($input->prefijoCodigo, $number);
        }

        return $codes;
    }

    /** @param list<string> $codes @return list<string> */
    private function loadExistingCodes(array $codes): array
    {
        return $this->aulas->findExistingCodes($codes);
    }

    private function validateRange(AulasMasivasInput $input): void
    {
        if ($input->numeroInicio <= 0 || $input->numeroFin <= 0 || $input->numeroInicio > $input->numeroFin) {
            throw new AulaException('El rango de aulas no es valido.');
        }

        if (($input->numeroFin - $input->numeroInicio + 1) > self::MAX_AULAS_POR_LOTE) {
            throw new AulaException('No se pueden crear mas de 80 aulas por lote.');
        }
    }

    private function validateConfiguration(AulasMasivasInput $input): void
    {
        if (trim($input->prefijoCodigo) === '') {
            throw new AulaException('El prefijo del codigo es obligatorio.');
        }

        if ($input->piso < 0 || $input->capacidad <= 0) {
            throw new AulaException('El piso no puede ser negativo y la capacidad debe ser mayor a cero.');
        }
    }

    /** @param list<string> $existingCodes */
    private function validateBusinessRules(array $existingCodes): void
    {
        if ($existingCodes !== []) {
            throw new AulaException(sprintf('Ya existen aulas con estos codigos: %s.', implode(', ', $existingCodes)));
        }
    }

    /** @param list<string> $codes @return list<Aula> */
    private function createAulas(AulasMasivasInput $input, array $codes): array
    {
        $aulas = [];
        foreach ($codes as $index => $code) {
            $number = $input->numeroInicio + $index;
            $aula = new Aula();
            $aula->updateData($code, sprintf('Aula %d', $number), $input->piso, $input->capacidad, $input->ubicacion);
            $aulas[] = $aula;
        }

        return $aulas;
    }

    /** @param list<Aula> $aulas */
    private function saveAulas(array $aulas): void
    {
        $this->aulas->saveMany($aulas);
    }

    /** @param list<Aula> $aulas */
    private function registerAudit(array $aulas, AulasMasivasInput $input): void
    {
        $this->audit->execute(ActionCatalog::CREATE, ModuleCatalog::AULAS, sprintf('Se crearon %d aulas masivamente para el piso %d.', count($aulas), $input->piso), $input->actorUserId);
    }

    /** @param list<Aula> $aulas */
    private function notifyAulasCreated(array $aulas): void
    {
        // Punto de extension para notificaciones administrativas.
    }

    private function buildCodigo(string $prefix, int $number): string
    {
        return mb_strtoupper(trim($prefix)) . '-' . $number;
    }
}
