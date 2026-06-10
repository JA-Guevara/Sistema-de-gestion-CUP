<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\UseCase;

use App\Academico\Aula\Domain\Entity\Aula;
use App\Academico\Aula\Domain\Exception\AulaException;
use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;

final readonly class ShowAula
{
    public function __construct(private AulaRepository $aulas)
    {
    }

    public function execute(int $aulaId): Aula
    {
        $aula = $this->aulas->findById($aulaId);
        if ($aula === null) {
            throw new AulaException('El aula solicitada no existe.');
        }

        return $aula;
    }
}
