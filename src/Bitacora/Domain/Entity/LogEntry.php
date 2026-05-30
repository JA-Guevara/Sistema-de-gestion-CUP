<?php

declare(strict_types=1);

namespace App\Bitacora\Domain\Entity;

use App\Bitacora\Domain\Catalog\ActionCatalog;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro de auditoría: una fila por cada evento importante del sistema.
 *
 * - No tiene FK dura a User: si borran al usuario, el log sobrevive con userLabel cacheado.
 * - level se calcula automáticamente del action al construir el registro.
 * - metadata es JSON nullable para contexto estructurado (ej. {old:..., new:...}).
 */
#[ORM\Entity]
#[ORM\Table(name: 'log_entries')]
#[ORM\Index(name: 'idx_log_entries_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_log_entries_action', columns: ['action'])]
#[ORM\Index(name: 'idx_log_entries_module', columns: ['module'])]
#[ORM\Index(name: 'idx_log_entries_created_at', columns: ['created_at'])]
class LogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?int $userId = null;

    /** Username/identificador del usuario cacheado al momento del evento. */
    #[ORM\Column(length: 180)]
    public string $userLabel;

    #[ORM\Column(length: 60)]
    public string $action;

    #[ORM\Column(length: 80)]
    public string $module;

    #[ORM\Column(type: 'text')]
    public string $description;

    /** Severidad calculada de la acción (INFO / ALERTA / CRITICO). */
    #[ORM\Column(length: 16)]
    public string $level;

    #[ORM\Column(length: 45, nullable: true)]
    public ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $userAgent = null;

    /** Contexto adicional estructurado (ej. {old:..., new:...}). */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $metadata = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->level = ActionCatalog::LEVEL_INFO;
        $this->createdAt = new \DateTimeImmutable();
    }
}
