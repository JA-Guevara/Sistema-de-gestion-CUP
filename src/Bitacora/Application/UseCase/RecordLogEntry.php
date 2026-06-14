<?php

declare(strict_types=1);

namespace App\Bitacora\Application\UseCase;

use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Entity\LogEntry;
use App\Bitacora\Infrastructure\Persistence\LogEntryRepository;
use Symfony\Component\HttpFoundation\Request;
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
        private UserRepository $users,
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
            $entry->userLabel = $userLabel ?? $this->resolveUserLabel($userId);
            $entry->action = $action;
            $entry->module = $module;
            $entry->description = trim($description);
            $entry->level = ActionCatalog::level($action);
            $entry->metadata = $metadata;
            $entry->ip = $request !== null ? $this->resolveClientIp($request) : null;
            $entry->userAgent = $request?->headers->get('User-Agent');
            $entry->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));

            $this->repository->save($entry);
        } catch (\Throwable $e) {
            // Auditoría no debe romper el flujo principal.
            // En producción esto se podría enviar a un error tracker.
        }
    }

    /**
     * Resuelve un nombre legible para la bitácora a partir del id de usuario.
     * Si el módulo no proporcionó un userLabel explícito, buscamos el usuario
     * y mostramos su nombre completo (o su email). Así la bitácora identifica
     * correctamente al actor en todos los módulos sin tener que pasar el nombre.
     */
    private function resolveUserLabel(?int $userId): string
    {
        if ($userId === null) {
            return '(anonimo)';
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return sprintf('Usuario #%d', $userId);
        }

        $nombre = trim($user->firstName . ' ' . $user->lastName);

        return $nombre !== '' ? $nombre : $user->email;
    }

    private function resolveClientIp(Request $request): ?string
    {
        $forwardedFor = $request->headers->get('x-forwarded-for');
        if (is_string($forwardedFor) && trim($forwardedFor) !== '') {
            $ips = array_filter(array_map('trim', explode(',', $forwardedFor)));
            $firstIp = reset($ips);

            if (is_string($firstIp) && filter_var($firstIp, FILTER_VALIDATE_IP)) {
                return $firstIp;
            }
        }

        $realIp = $request->headers->get('x-real-ip');
        if (is_string($realIp) && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }

        return $request->getClientIp();
    }
}
