<?php

declare(strict_types=1);

namespace App\Bitacora\Application\UseCase;

use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Entity\LogEntry;
use App\Bitacora\Infrastructure\Persistence\LogEntryRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Caso de uso: registrar un evento en la bitácora.
 *
 * Es el servicio que cualquier módulo inyecta y llama después de una
 * acción importante. La idea es no llamarlo "crudo" desde el módulo:
 * mejor a través de helpers semánticos en Application/EventLog (ej.
 * AuthEvents::loginExitoso()) que arman el contexto y llaman acá.
 *
 * Defensivo: si falla la inserción, NO levanta excepción al caller
 * (no queremos que el login falle porque no se pudo loguear el log).
 * El error se ignora silenciosamente.
 */
final readonly class RecordLogEntry
{
    public function __construct(
        private LogEntryRepository $repository,
        private RequestStack $requestStack,
    ) {
    }

    public function execute(
        string $action,
        string $module,
        string $description,
        ?int $userId = null,
        ?string $userLabel = null,
        ?array $metadata = null,
    ): void {
        try {
            $request = $this->requestStack->getCurrentRequest();

            $entry = new LogEntry();
            $entry->userId = $userId;
            $entry->userLabel = $userLabel ?? '(anónimo)';
            $entry->action = $action;
            $entry->module = $module;
            $entry->description = trim($description);
            $entry->level = ActionCatalog::level($action);
            $entry->metadata = $metadata;
            $entry->ip = $request?->getClientIp();
            $entry->userAgent = $request?->headers->get('User-Agent');
            $entry->createdAt = new \DateTimeImmutable();

            $this->repository->save($entry);
        } catch (\Throwable $e) {
            // Auditoría no debe romper el flujo principal.
            // En producción esto se podría enviar a un error tracker.
        }
    }
}
