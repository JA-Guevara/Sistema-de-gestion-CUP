<?php

declare(strict_types=1);

namespace App\Gestion\Domain\Catalog;

final class TipoAccionGestion
{
    public const CREADA = 'GESTION_CREADA';
    public const ACTUALIZADA = 'GESTION_ACTUALIZADA';
    public const ACTIVADA = 'GESTION_ACTIVADA';
    public const INSCRIPCION_ABIERTA = 'INSCRIPCION_ABIERTA';
    public const INSCRIPCION_CERRADA = 'INSCRIPCION_CERRADA';
    public const FINALIZADA = 'GESTION_FINALIZADA';
    public const CANCELADA = 'GESTION_CANCELADA';
}
