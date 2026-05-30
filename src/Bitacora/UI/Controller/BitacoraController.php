<?php

declare(strict_types=1);

namespace App\Bitacora\UI\Controller;

use App\Auth\Infrastructure\Persistence\UserRepository;
use App\Bitacora\Application\UseCase\QueryLog;
use App\Bitacora\Domain\Catalog\ActionCatalog;
use App\Bitacora\Domain\Catalog\ModuleCatalog;
use App\Bitacora\Infrastructure\Persistence\LogEntryRepository;
use App\Bitacora\UI\Request\BitacoraFilterRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class BitacoraController extends AbstractController
{
    private const SESSION_USER_KEY = 'auth_user_id';

    public function __construct(
        private readonly QueryLog $queryLog,
        private readonly LogEntryRepository $logRepository,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/bitacora', name: 'bitacora_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Requiere sesión (futuro: requerir rol admin).
        $userId = $request->getSession()->get(self::SESSION_USER_KEY);
        $user = is_int($userId) ? $this->users->findById($userId) : null;

        if ($user === null) {
            return $this->redirectToRoute('auth_login');
        }

        $filter = BitacoraFilterRequest::fromRequest($request);
        $result = $this->queryLog->execute($filter);

        return $this->render('@bitacora/lista.html.twig', [
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'totalPages' => $result['totalPages'],
            'filter' => $filter,
            'actions' => ActionCatalog::all(),
            'modules' => ModuleCatalog::all(),
            'userLabels' => $this->logRepository->distinctUserLabels(),
            'user' => $user,
        ]);
    }
}
