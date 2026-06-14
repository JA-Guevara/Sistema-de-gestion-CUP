<?php

declare(strict_types=1);

namespace App\Asignacion\Application\UseCase;

use App\Academico\Grupo\Domain\Entity\Grupo;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Materia\Domain\Entity\Materia;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Gestion\Domain\Entity\Gestion;
use App\Gestion\Infrastructure\Persistence\GestionRepository;
use App\Inscripcion\Infrastructure\Persistence\InscripcionRepository;
use App\Notas\Domain\Entity\AsignacionDocente;
use App\Notas\Infrastructure\Persistence\AsignacionDocenteRepository;
use App\Usuario\Domain\Entity\Role;
use App\Usuario\Infrastructure\Persistence\RoleRepository;

final readonly class ShowAsignacionDashboard
{
    public function __construct(
        private GestionRepository $gestiones,
        private InscripcionRepository $inscripciones,
        private GrupoRepository $grupos,
        private MateriaRepository $materias,
        private UserRepository $users,
        private RoleRepository $roles,
        private AsignacionDocenteRepository $asignacionesDocente,
    ) {
    }

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $gestion = $this->loadActiveGestion();
        $users = $this->loadUsers();
        $roles = $this->loadRoles();
        $materias = $this->loadMaterias();
        $grupos = $this->loadGrupos($gestion);
        $docentes = $this->loadDocentes();
        $asignacionesDocente = $this->loadAsignacionesDocente($gestion);
        $stats = $this->loadStats($gestion, $users, $grupos, $docentes, $asignacionesDocente);

        return compact(
            'gestion',
            'users',
            'roles',
            'materias',
            'grupos',
            'docentes',
            'asignacionesDocente',
            'stats',
        );
    }

    private function loadActiveGestion(): ?Gestion
    {
        return $this->gestiones->findActive();
    }

    /** @return list<User> */
    private function loadUsers(): array
    {
        return $this->users->listAll();
    }

    /** @return list<Role> */
    private function loadRoles(): array
    {
        return $this->roles->listActive();
    }

    /** @return list<Materia> */
    private function loadMaterias(): array
    {
        return $this->materias->listActive();
    }

    /** @return list<Grupo> */
    private function loadGrupos(?Gestion $gestion): array
    {
        return $gestion === null ? [] : $this->grupos->listByGestion((int) $gestion->id);
    }

    /** @return list<User> */
    private function loadDocentes(): array
    {
        return $this->asignacionesDocente->listDocentes();
    }

    /** @return list<AsignacionDocente> */
    private function loadAsignacionesDocente(?Gestion $gestion): array
    {
        return $gestion === null ? [] : $this->asignacionesDocente->listByGestion((int) $gestion->id);
    }

    /**
     * @param list<User> $users
     * @param list<Grupo> $grupos
     * @param list<User> $docentes
     * @param list<AsignacionDocente> $asignacionesDocente
     * @return array{inscritos:int, usuarios:int, docentes:int, grupos:int, asignacionesDocente:int}
     */
    private function loadStats(?Gestion $gestion, array $users, array $grupos, array $docentes, array $asignacionesDocente): array
    {
        return [
            'inscritos' => $gestion === null ? 0 : $this->inscripciones->countByGestion((int) $gestion->id),
            'usuarios' => count($users),
            'docentes' => count($docentes),
            'grupos' => count($grupos),
            'asignacionesDocente' => count($asignacionesDocente),
        ];
    }
}
