<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Exception\DocumentoException;
use App\Inscripcion\Infrastructure\Persistence\DocumentoRepository;

final readonly class EliminarDocumento
{
    public function __construct(
        private DocumentoRepository $documentos,
        private InscripcionEvents $events,
    ) {
    }

    public function execute(int $documentoId, ?int $actorUserId): void
    {
        $documento = $this->documentos->findById($documentoId);
        if ($documento === null) {
            throw new DocumentoException('Documento no encontrado.');
        }

        $ci = $documento->inscripcion->ci;
        $nombreDoc = $documento->nombre;

        $ruta = $documento->archivoRuta;
        $rutaAbsoluta = dirname(__DIR__, 4) . '/public/' . $ruta;
        if (is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }

        $this->documentos->remove($documento);
        $this->events->documentoEliminado($ci, $nombreDoc, $actorUserId);
    }
}
