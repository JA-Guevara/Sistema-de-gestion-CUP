<?php

declare(strict_types=1);

namespace App\Academico\Horario\Application\UseCase;

use App\Academico\Horario\Domain\Entity\Horario;
use App\Academico\Horario\Infrastructure\Persistence\HorarioRepository;

/**
 * Devuelve el horario completo (todas las franjas) de un grupo,
 * ordenado por dia y hora. Alimenta la vista de detalle por grupo.
 */
final readonly class ListHorariosDeGrupo
{
    public function __construct(private HorarioRepository $horarios)
    {
    }

    /**
     * @return list<Horario>
     */
    public function execute(int $grupoId): array
    {
        return $this->horarios->listByGrupo($grupoId);
    }
}
