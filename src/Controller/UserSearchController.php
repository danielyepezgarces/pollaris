<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserSearchController extends BaseController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/users/search', name: 'users_search')]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $query = trim($request->query->getString('q'));

        if ($query === '') {
            return new JsonResponse([]);
        }

        $users = $this->userRepository->searchByUsernameOrRealName($query, 20);

        $results = array_map(static function ($user): array {
            return [
                'username' => $user->getUserIdentifier(),
                'displayName' => $user->getDisplayName(),
            ];
        }, $users);

        return new JsonResponse($results);
    }
}
