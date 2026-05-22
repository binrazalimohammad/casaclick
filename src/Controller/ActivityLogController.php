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
        $standardActions = [
            'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT',
            'MOBILE_LOGIN', 'MOBILE_LOGOUT', 'MOBILE_REGISTER', 'MOBILE_VIEW',
            'MOBILE_APPLY', 'MOBILE_PAYMENT',
        ];
        
        // Get distinct actions from actual logs (actions that have been used)
        $usedActions = $logRepository->findDistinctActions();
        
        // Combine standard actions with used actions, removing duplicates and sorting
        $allActions = array_unique(array_merge($standardActions, $usedActions));
        sort($allActions);

        $newestAt = !empty($logs)
            ? $logs[0]->getCreatedAt()->format(\DateTimeInterface::ATOM)
            : (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        return $this->render('admin/logs/index.html.twig', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $allActions,
            'newestAt' => $newestAt,
            'filters' => [
                'user' => $userId ?: '',
                'action' => $action ?: '',
                'from' => $from ? $from->format('Y-m-d') : '',
                'to' => $to ? $to->format('Y-m-d') : '',
            ],
        ]);
    }

    /** Live feed for admin Activity Logs page (poll every few seconds). */
    #[Route('/feed', name: 'app_admin_logs_feed', methods: ['GET'])]
    public function feed(Request $request, ActivityLogRepository $logRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $sinceParam = $request->query->get('since', '');
        try {
            $since = !empty($sinceParam) ? new \DateTimeImmutable($sinceParam) : new \DateTimeImmutable('-1 minute');
        } catch (\Exception) {
            $since = new \DateTimeImmutable('-1 minute');
        }

        $logs = $logRepository->findSince($since, 50);
        $data = array_map(static function ($log) {
            return [
                'id' => $log->getId(),
                'userId' => $log->getUser()?->getId(),
                'username' => $log->getUsername() ?? $log->getUser()?->getEmail() ?? 'System',
                'role' => $log->getRole() ?? 'ROLE_TENANT',
                'action' => $log->getAction(),
                'targetData' => $log->getTargetData() ?? '',
                'createdAt' => $log->getCreatedAt()->format('Y-m-d g:i:s A'),
                'createdAtIso' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $logs);

        return $this->json([
            'success' => true,
            'data' => $data,
            'serverTime' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}


