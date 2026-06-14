<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Catalog\EstadoDocumento;
use App\Inscripcion\Domain\Exception\DocumentoException;
use App\Inscripcion\Infrastructure\Persistence\DocumentoRepository;

final readonly class RevisarDocumento
{
    public function __construct(
        private DocumentoRepository $documentos,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $documentoId, string $estado, ?string $observacion, ?int $actorUserId): void
    {
        $documento = $this->documentos->findById($documentoId);
        if ($documento === null) {
            throw new DocumentoException('El documento no existe.');
        }

        if (!EstadoDocumento::isValid($estado)) {
            throw new DocumentoException('Estado de documento invalido.');
        }

        if ($estado === EstadoDocumento::OBSERVADO && ($observacion === null || trim($observacion) === '')) {
            throw new DocumentoException('Indica la observacion del documento.');
        }

        $documento->revisar($estado, $observacion);
        $this->documentos->save($documento);

        $this->events->documentoRevisado($documento->inscripcion->ci, $documento->nombre, $estado, $actorUserId);
    }
}
