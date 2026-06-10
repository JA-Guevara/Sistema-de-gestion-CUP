<?php

declare(strict_types=1);

namespace App\Academico\Horario\UI\Controller;

use App\Academico\Aula\Infrastructure\Persistence\AulaRepository;
use App\Academico\Grupo\Infrastructure\Persistence\GrupoRepository;
use App\Academico\Horario\Application\DTO\HorarioActionInput;
use App\Academico\Horario\Application\UseCase\AgruparHorariosPorMateria;
use App\Academico\Horario\Application\UseCase\ConstruirGrillaDeGrupo;
use App\Academico\Horario\Application\UseCase\DeleteHorario;
use App\Academico\Horario\Application\UseCase\DeleteHorariosDeGrupo;
use App\Academico\Horario\Application\UseCase\DeleteHorariosDeGrupos;
use App\Academico\Horario\Application\UseCase\DeleteHorariosDeMateria;
use App\Academico\Horario\Application\UseCase\GenerarHorarioMasivo;
use App\Academico\Horario\Application\UseCase\GenerarHorarioMultiGrupo;
use App\Academico\Horario\Application\UseCase\ListHorarios;
use App\Academico\Horario\Application\UseCase\ListHorariosDeGrupo;
use App\Academico\Horario\Application\UseCase\ResumirHorariosPorGrupo;
use App\Academico\Horario\Application\UseCase\ShowHorario;
use App\Academico\Horario\Application\UseCase\UpdateHorario;
use App\Academico\Horario\Domain\Exception\HorarioException;
use App\Academico\Horario\UI\Request\HorarioRequest;
use App\Academico\Materia\Infrastructure\Persistence\MateriaRepository;
use App\Academico\Turno\Infrastructure\Persistence\TurnoRepository;
use App\Auth\Entity\User;
use App\Auth\Infrastructure\Persistence\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/horarios')]
final class HorarioController extends AbstractController
{
    public function __construct(
        private readonly ListHorarios $listHorarios,
        private readonly ResumirHorariosPorGrupo $resumirHorariosPorGrupo,
        private readonly ListHorariosDeGrupo $listHorariosDeGrupo,
        private readonly ConstruirGrillaDeGrupo $construirGrillaDeGrupo,
        private readonly AgruparHorariosPorMateria $agruparHorariosPorMateria,
        private readonly DeleteHorariosDeMateria $deleteHorariosDeMateria,
        private readonly ShowHorario $showHorario,
        private readonly UpdateHorario $updateHorario,
        private readonly DeleteHorario $deleteHorario,
        private readonly DeleteHorariosDeGrupo $deleteHorariosDeGrupo,
        private readonly DeleteHorariosDeGrupos $deleteHorariosDeGrupos,
        private readonly GenerarHorarioMasivo $generarHorarioMasivo,
        private readonly GenerarHorarioMultiGrupo $generarHorarioMultiGrupo,
        private readonly GrupoRepository $grupos,
        private readonly MateriaRepository $materias,
        private readonly AulaRepository $aulas,
        private readonly TurnoRepository $turnos,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('', name: 'horario_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser($request);

        return $this->render('@horario/lista.html.twig', [
            'resumen' => $this->resumirHorariosPorGrupo->execute(),
            'grupos' => $this->grupos->listAll(),
            'materias' => $this->materias->listActive(),
            'aulas' => $this->aulas->listActive(),
            'turnos' => $this->turnos->listActive(),
            'user' => $user,
        ]);
    }

    #[Route('/multigrupo', name: 'horario_multigrupo', methods: ['POST'])]
    public function generarMultiGrupo(Request $request): Response
    {
        $this->requireUser($request);

        try {
            $horarios = $this->generarHorarioMultiGrupo->execute(HorarioRequest::multiGrupoRequest($request));
            $this->addFlash('success', sprintf('Se generaron %d horarios para los grupos seleccionados.', count($horarios)));
        } catch (HorarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('horario_index');
    }

    #[Route('/grupo/{grupoId}', name: 'horario_grupo_show', methods: ['GET'], requirements: ['grupoId' => '\d+'])]
    public function showGrupo(Request $request, int $grupoId): Response
    {
        $user = $this->requireUser($request);

        $grupo = $this->grupos->findById($grupoId);
        if ($grupo === null) {
            throw $this->createNotFoundException('El grupo solicitado no existe.');
        }

        $horarios = $this->listHorariosDeGrupo->execute($grupoId);

        return $this->render('@horario/detalle-grupo.html.twig', [
            'grupo' => $grupo,
            'horarios' => $horarios,
            'grilla' => $this->construirGrillaDeGrupo->execute($horarios),
            'porMateria' => $this->agruparHorariosPorMateria->execute($horarios),
            'user' => $user,
        ]);
    }

    #[Route('/grupo/{grupoId}/materia/{materiaId}/eliminar', name: 'horario_grupo_materia_delete', methods: ['POST'], requirements: ['grupoId' => '\d+', 'materiaId' => '\d+'])]
    public function deleteMateria(Request $request, int $grupoId, int $materiaId): Response
    {
        $this->requireUser($request);

        try {
            $eliminados = $this->deleteHorariosDeMateria->execute($grupoId, $materiaId, HorarioRequest::actorUserIdFromSession($request));
            $this->addFlash('success', sprintf('Se elimino la materia del horario (%d franjas).', $eliminados));
        } catch (HorarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('horario_grupo_show', ['grupoId' => $grupoId]);
    }

    #[Route('/grupo/{grupoId}/eliminar', name: 'horario_grupo_delete', methods: ['POST'], requirements: ['grupoId' => '\d+'])]
    public function deleteGrupo(Request $request, int $grupoId): Response
    {
        $this->requireUser($request);

        try {
            $eliminados = $this->deleteHorariosDeGrupo->execute($grupoId, HorarioRequest::actorUserIdFromSession($request));
            $this->addFlash('success', sprintf('Se elimino el horario del grupo (%d franjas).', $eliminados));
        } catch (HorarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('horario_index');
    }

    #[Route('/grupos/eliminar', name: 'horario_grupos_delete', methods: ['POST'])]
    public function deleteGrupos(Request $request): Response
    {
        $this->requireUser($request);

        try {
            $eliminados = $this->deleteHorariosDeGrupos->execute(HorarioRequest::grupoBulkDeleteRequest($request));
            $this->addFlash('success', sprintf('Se eliminaron %d franjas de horario.', $eliminados));
        } catch (HorarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('horario_index');
    }

    #[Route('/masivo', name: 'horario_masivo', methods: ['POST'])]
    public function generarMasivo(Request $request): Response
    {
        $this->requireUser($request);

        try {
            $horarios = $this->generarHorarioMasivo->execute(HorarioRequest::masivoRequest($request));
            $this->addFlash('success', sprintf('Se generaron %d horarios para el grupo en una sola operacion.', count($horarios)));
        } catch (HorarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('horario_index');
    }

    #[Route('/{id}', name: 'horario_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): Response
    {
        $user = $this->requireUser($request);

        return $this->render('@horario/detalle.html.twig', [
            'horario' => $this->showHorario->execute($id),
            'user' => $user,
        ]);
    }

    #[Route('/{id}/editar', name: 'horario_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->requireUser($request);
        $horario = $this->showHorario->execute($id);

        if ($request->isMethod('POST')) {
            try {
                $this->updateHorario->execute($id, HorarioRequest::fromRequest($request));
                $this->addFlash('success', 'Horario actualizado correctamente.');

                return $this->redirectToRoute('horario_index');
            } catch (HorarioException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('@horario/form.html.twig', [
            'horario' => $horario,
            'grupos' => $this->grupos->listAll(),
            'materias' => $this->materias->listActive(),
            'aulas' => $this->aulas->listActive(),
            'user' => $user,
        ]);
    }

    #[Route('/{id}/eliminar', name: 'horario_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        $this->requireUser($request);

        try {
            $this->deleteHorario->execute(new HorarioActionInput($id, HorarioRequest::actorUserIdFromSession($request)));
            $this->addFlash('success', 'Horario eliminado correctamente.');
        } catch (HorarioException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('horario_index');
    }

    private function requireUser(Request $request): User
    {
        $userId = $request->getSession()->get('auth_user_id');
        $user = is_int($userId) ? $this->users->findById($userId) : null;
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
