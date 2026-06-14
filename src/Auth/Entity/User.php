<?php

namespace App\Auth\Entity;

use App\Usuario\Domain\Entity\Role;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    public string $email;

    #[ORM\Column(length: 80)]
    public string $firstName;

    #[ORM\Column(length: 80)]
    public string $lastName;

    #[ORM\Column]
    public string $passwordHash;

    /** ID de la sesión activa (single-session enforcement). */
    #[ORM\Column(length: 128, nullable: true)]
    public ?string $currentSessionId = null;

    /** Timestamp del último login exitoso. */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * Contador de intentos fallidos consecutivos de login.
     * Se resetea a 0 con un login exitoso.
     */
    #[ORM\Column(options: ['default' => 0])]
    public int $failedLoginAttempts = 0;

    /** Cuenta bloqueada por superar el umbral de intentos fallidos. */
    #[ORM\Column(options: ['default' => false])]
    public bool $locked = false;

    #[ORM\Column(options: ['default' => true])]
    public bool $active = true;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $roles;

    /** Código de 6 dígitos para desbloquear la cuenta. */
    #[ORM\Column(length: 6, nullable: true)]
    public ?string $unlockCode = null;

    /** Cuándo expira el código de desbloqueo. */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $unlockCodeExpiresAt = null;

    /** Timestamp del último envío del código (para el cooldown de reenvío). */
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $unlockCodeLastSentAt = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = new ArrayCollection();
    }

    /** @return list<Role> */
    public function roles(): array
    {
        return $this->roles->toArray();
    }

    /** @param list<Role> $roles */
    public function syncRoles(array $roles): void
    {
        $this->roles->clear();

        foreach ($roles as $role) {
            $this->roles->add($role);
        }

        $this->touch();
    }

    public function roleNames(): string
    {
        $names = array_map(static fn (Role $role): string => $role->name, $this->roles());

        return $names === [] ? 'Sin rol' : implode(', ', $names);
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->roles as $role) {
            if (mb_strtolower($role->name) === mb_strtolower(trim($roleName))) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permissionCode): bool
    {
        foreach ($this->roles as $role) {
            if ($role->active && $role->hasPermission($permissionCode)) {
                return true;
            }
        }

        return false;
    }

    public function activate(): void
    {
        $this->active = true;
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->currentSessionId = null;
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
    }
}
