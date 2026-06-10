<?php

declare(strict_types=1);

namespace App\Bitacora\Application\Audit;

use App\Bitacora\Application\UseCase\RecordLogEntry;
use App\Bitacora\Domain\Catalog\ActionCatalog;

/**
 * Fachada CENTRALIZADA de auditoria.
 *
 * Punto unico que cualquier modulo (presente o futuro) debe inyectar para
 * auditar. En vez de llamar a RecordLogEntry con strings sueltos, se llama a
 * un metodo semantico (created/updated/deleted/activated/...), o a log() para
 * casos a medida. Los campos enterprise (entidad, rol, resultado, valor
 * anterior/nuevo, observaciones) viajan dentro de `metadata` (JSON), por lo
 * que NO requiere cambios de esquema para empezar a usarse.
 *
 * Resultado por defecto: EXITO. Si una operacion falla y se quiere auditar el
 * intento, pasar result: AuditLogger::RESULT_ERROR.
 */
final readonly class AuditLogger
{
    public const RESULT_OK = 'EXITO';
    public const RESULT_ERROR = 'ERROR';
    public const RESULT_DENIED = 'DENEGADO';

    public function __construct(private RecordLogEntry $recorder)
    {
    }

    /**
     * Punto unico de auditoria a medida.
     *
     * @param array<string,mixed>|null $before Estado anterior (clave => valor).
     * @param array<string,mixed>|null $after  Estado nuevo (clave => valor).
     * @param array<string,mixed>      $extra  Contexto adicional libre.
     */
    public function log(
        string $module,
        string $action,
        string $description,
        ?string $entity = null,
        ?int $userId = null,
        ?string $userLabel = null,
        ?string $role = null,
        string $result = self::RESULT_OK,
        ?array $before = null,
        ?array $after = null,
        ?string $observations = null,
        array $extra = [],
    ): void {
        $metadata = $extra;
        $this->put($metadata, 'entidad', $entity);
        $this->put($metadata, 'rol', $role);
        $this->put($metadata, 'resultado', $result);
        $this->put($metadata, 'observaciones', $observations);
        $this->put($metadata, 'antes', $before);
        $this->put($metadata, 'despues', $after);

        $this->recorder->execute(
            action: $action,
            module: $module,
            description: $description,
            userId: $userId,
            userLabel: $userLabel,
            metadata: $metadata === [] ? null : $metadata,
        );
    }

    public function created(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null, ?array $after = null): void
    {
        $this->log($module, ActionCatalog::CREATE, $description, entity: $entity, userId: $userId, userLabel: $userLabel, after: $after);
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public function updated(string $module, string $entity, string $description, ?array $before = null, ?array $after = null, ?int $userId = null, ?string $userLabel = null): void
    {
        $this->log($module, ActionCatalog::UPDATE, $description, entity: $entity, userId: $userId, userLabel: $userLabel, before: $before, after: $after);
    }

    public function deleted(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null, ?array $before = null): void
    {
        $this->log($module, ActionCatalog::DELETE, $description, entity: $entity, userId: $userId, userLabel: $userLabel, before: $before);
    }

    public function activated(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null): void
    {
        $this->log($module, ActionCatalog::ACTIVATE, $description, entity: $entity, userId: $userId, userLabel: $userLabel);
    }

    public function deactivated(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null): void
    {
        $this->log($module, ActionCatalog::DEACTIVATE, $description, entity: $entity, userId: $userId, userLabel: $userLabel);
    }

    public function stateChanged(string $module, string $entity, string $description, ?string $from = null, ?string $to = null, ?int $userId = null, ?string $userLabel = null): void
    {
        $this->log(
            $module,
            ActionCatalog::STATE_CHANGE,
            $description,
            entity: $entity,
            userId: $userId,
            userLabel: $userLabel,
            before: $from !== null ? ['estado' => $from] : null,
            after: $to !== null ? ['estado' => $to] : null,
        );
    }

    public function generated(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null, array $extra = []): void
    {
        $this->log($module, ActionCatalog::GENERATE, $description, entity: $entity, userId: $userId, userLabel: $userLabel, extra: $extra);
    }

    public function assigned(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null): void
    {
        $this->log($module, ActionCatalog::ASSIGN, $description, entity: $entity, userId: $userId, userLabel: $userLabel);
    }

    public function viewed(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null): void
    {
        $this->log($module, ActionCatalog::VIEW, $description, entity: $entity, userId: $userId, userLabel: $userLabel);
    }

    public function downloaded(string $module, string $entity, string $description, ?int $userId = null, ?string $userLabel = null): void
    {
        $this->log($module, ActionCatalog::DOWNLOAD, $description, entity: $entity, userId: $userId, userLabel: $userLabel);
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function put(array &$metadata, string $key, mixed $value): void
    {
        if ($value === null || $value === [] || $value === '') {
            return;
        }

        $metadata[$key] = $value;
    }
}
