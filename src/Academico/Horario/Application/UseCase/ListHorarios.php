<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;

final readonly class ListHorarios
{
    public function __construct(private HorarioRepository $horarios)
    {
    }

    public function execute(): array
    {
        return $this->horarios->listAll();
    }
}
