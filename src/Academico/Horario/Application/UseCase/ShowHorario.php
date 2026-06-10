<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;

final readonly class ShowHorario
{
    public function __construct(private HorarioRepository $horarios)
    {
    }

    public function execute(int $horarioId): Horario
    {
        $horario = $this->horarios->findById($horarioId);
        if ($horario === null) {
            throw new HorarioException('El horario solicitado no existe.');
        }

        return $horario;
    }
}
