<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/logs')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'app_admin_logs', methods: ['GET'])]
    public function index(
        Request $request,
        ActivityLogRepository $logRepository,
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Safely get user ID - handle empty string or invalid values
        $userParam = $request->query->get('user', '');
        $userId = !empty($userParam) && is_numeric($userParam) ? (int)$userParam : null;
        
        $action = $request->query->get('action') ?: null;
        
        // Safely get date parameters
        $fromParam = $request->query->get('from', '');
        $from = !empty($fromParam) ? new \DateTime($fromParam) : null;
        
        $toParam = $request->query->get('to', '');
        $to = !empty($toParam) ? new \DateTime($toParam) : null;

        $logs = $logRepository->findFiltered($userId ?: null, $action, $from, $to, 100);
        $users = $userRepository->findBy([], ['email' => 'ASC']);

        // Standard actions that should always be available in the dropdown
        $standardActions = ['CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT'];
        
        // Get distinct actions from actual logs (actions that have been used)
        $usedActions = $logRepository->findDistinctActions();
        
        // Combine standard actions with used actions, removing duplicates and sorting
        $allActions = array_unique(array_merge($standardActions, $usedActions));
        sort($allActions);

        return $this->render('admin/logs/index.html.twig', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $allActions,
            'filters' => [
                'user' => $userId ?: '',
                'action' => $action ?: '',
                'from' => $from ? $from->format('Y-m-d') : '',
                'to' => $to ? $to->format('Y-m-d') : '',
            ],
        ]);
    }
}


