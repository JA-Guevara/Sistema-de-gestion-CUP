<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\DTO;

final readonly class ActaRecepcionInput
{
    /**
     * @param array<string,string> $estados      codigo de requisito => estado
     * @param array<string,?string> $observaciones codigo de requisito => observacion
     */
    public function __construct(
        public int $inscripcionId,
        public array $estados,
        public array $observaciones,
        public ?int $actorUserId,
    ) {
    }
}
