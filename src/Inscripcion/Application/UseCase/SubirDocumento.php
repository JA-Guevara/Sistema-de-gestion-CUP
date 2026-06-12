<?php

declare(strict_types=1);

namespace App\Inscripcion\Application\UseCase;

use App\Bitacora\Application\EventLog\InscripcionEvents;
use App\Inscripcion\Domain\Entity\Documento;
use App\Inscripcion\Domain\Entity\Inscripcion;
use App\Inscripcion\Domain\Exception\DocumentoException;
use App\Inscripcion\Infrastructure\Persistence\DocumentoRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class SubirDocumento
{
    private const EXTS = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
    private const MAX_BYTES = 5242880; // 5 MB

    public function __construct(
        private DocumentoRepository $documentos,
        private InscripcionEvents $events,
        private string $uploadDir,
    ) {
    }

    public function execute(Inscripcion $inscripcion, UploadedFile $file, string $nombreDocumento, ?int $actorUserId): Documento
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, self::EXTS, true)) {
            throw new DocumentoException('Formato no permitido. Usa PDF, PNG, JPG, DOC o DOCX.');
        }

        if ($file->getSize() !== null && $file->getSize() > self::MAX_BYTES) {
            throw new DocumentoException('El archivo no puede superar los 5 MB.');
        }

        $dir = rtrim($this->uploadDir, '/\\') . '/inscripcion_documentos/' . ($inscripcion->id ?? 'tmp');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new DocumentoException('No se pudo preparar el directorio de documentos.');
        }

        $filename = sprintf('%s_%s.%s', $inscripcion->id ?? 'tmp', bin2hex(random_bytes(8)), $ext);
        try {
            $file->move($dir, $filename);
        } catch (\Throwable $e) {
            throw new DocumentoException('No se pudo guardar el archivo.');
        }

        $documento = new Documento();
        $documento->inscripcion = $inscripcion;
        $documento->nombre = trim($nombreDocumento);
        $documento->archivoRuta = sprintf('uploads/inscripcion_documentos/%s/%s', $inscripcion->id ?? 'tmp', $filename);
        $documento->archivoExtension = $ext;
        $documento->archivoTamanio = $file->getSize() ?? 0;

        $this->documentos->save($documento);
        $this->events->documentoSubido($inscripcion->ci, $documento->nombre, $actorUserId);

        return $documento;
    }
}
