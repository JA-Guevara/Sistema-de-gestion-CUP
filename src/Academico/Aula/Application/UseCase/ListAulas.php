<?php

declare(strict_types=1);

namespace App\Academico\Aula\Application\UseCase;

use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;

final readonly class ListAulas
{
    public function __construct(private AulaRepository $aulas)
    {
    }

    public function execute(): array
    {
        return $this->aulas->listAll();
    }
}
