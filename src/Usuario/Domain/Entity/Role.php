<?php

declare(strict_types=1);

namespace App\Usuario\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'roles')]
#[ORM\UniqueConstraint(name: 'uniq_roles_name', columns: ['name'])]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 80)]
    public string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    public bool $active = true;

    /** @var Collection<int, Permission> */
    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'role_permissions')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $permissions;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
    }

    public function configure(string $name, ?string $description, bool $active): void
    {
        $this->name = trim($name);
        $this->description = $description !== null && trim($description) !== '' ? trim($description) : null;
        $this->active = $active;
        $this->touch();
    }

    /** @param list<Permission> $permissions */
    public function syncPermissions(array $permissions): void
    {
        $this->permissions->clear();

        foreach ($permissions as $permission) {
            $this->permissions->add($permission);
        }

        $this->touch();
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return $this->permissions->toArray();
    }

    /** @return list<int> */
    public function permissionIds(): array
    {
        return array_map(
            static fn (Permission $permission): int => (int) $permission->id,
            $this->permissions()
        );
    }

    public function hasPermission(string $permissionCode): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->active && $permission->code === $permissionCode) {
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
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('America/La_Paz'));
    }
}
