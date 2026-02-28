<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Controllers;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Security\RbacManager;
use App\Core\Services\ProjectAccessService;
use App\Modules\Tickets\Repositories\TicketRepository;
use App\Modules\Webhooks\Services\WebhookService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

class TicketController
{
    public function __construct(
        private readonly TicketRepository $repository,
        private readonly ProjectAccessService $projectAccess,
        private readonly WebhookService $webhookService,
        private readonly RbacManager $rbacManager
    ) {}

    private function getRouteArg(ServerRequestInterface $request, string $name): ?string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        return $route ? $route->getArgument($name) : null;
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    // ========================================================================
    // Tickets - Protected Routes
    // ========================================================================

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        // Check project access for restricted users
        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = $isRestricted ? $this->projectAccess->getUserAccessibleProjectIds($userId) : [];

        // Validate requested project_id access
        $requestedProjectId = $params['project_id'] ?? null;
        if ($requestedProjectId && $isRestricted && !in_array($requestedProjectId, $accessibleProjectIds)) {
            return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
        }

        $filters = [];

        // Check if user can see all tickets or only their own
        $canViewAll = $this->canViewAllTickets($request);

        if (!$canViewAll) {
            // User can only see their own tickets or tickets assigned to them
            $filters['user_id'] = $userId;
        }

        // Filter by accessible projects for restricted users
        if ($isRestricted && !$requestedProjectId) {
            if (empty($accessibleProjectIds)) {
                return JsonResponse::success([
                    'tickets' => [],
                    'total' => 0,
                    'limit' => min((int) ($params['limit'] ?? 50), 100),
                    'offset' => (int) ($params['offset'] ?? 0),
                ]);
            }
            $filters['project_ids'] = $accessibleProjectIds;
        }

        if (!empty($params['status'])) {
            $filters['status'] = explode(',', $params['status']);
        }

        if (!empty($params['priority'])) {
            $filters['priority'] = $params['priority'];
        }

        if (!empty($params['category_id'])) {
            $filters['category_id'] = $params['category_id'];
        }

        if (!empty($params['project_id'])) {
            $filters['project_id'] = $params['project_id'];
        }

        if (!empty($params['assigned_to'])) {
            $filters['assigned_to'] = $params['assigned_to'];
        }

        if (!empty($params['search'])) {
            $filters['search'] = $params['search'];
        }

        $limit = min((int) ($params['limit'] ?? 50), 100);
        $offset = (int) ($params['offset'] ?? 0);

        $tickets = $this->repository->findAll($filters, $limit, $offset);
        $total = $this->repository->count($filters);

        return JsonResponse::success([
            'tickets' => $tickets,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $ticket = $this->repository->findById($id);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden', 404);
        }

        // Check access
        if (!$this->canAccessTicket($request, $ticket)) {
            return JsonResponse::error('Keine Berechtigung', 403);
        }

        // Get comments (internal only if staff)
        $canViewInternal = $this->canViewInternalComments($request);
        $comments = $this->repository->getComments($id, $canViewInternal);

        // Get status history for staff
        $history = $canViewInternal ? $this->repository->getStatusHistory($id) : [];

        return JsonResponse::success([
            'ticket' => $ticket,
            'comments' => $comments,
            'history' => $history,
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        // Validate project access for restricted users
        $projectId = $data['project_id'] ?? null;
        if ($projectId && $this->projectAccess->isUserRestricted($userId)) {
            $accessibleProjectIds = $this->projectAccess->getUserAccessibleProjectIds($userId);
            if (!in_array($projectId, $accessibleProjectIds)) {
                return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
            }
        }

        if (empty($data['title'])) {
            throw new ValidationException('Titel ist erforderlich');
        }

        if (empty($data['description'])) {
            throw new ValidationException('Beschreibung ist erforderlich');
        }

        $id = $this->generateUuid();

        $ticketData = [
            'id' => $id,
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'status' => 'open',
            'priority' => $data['priority'] ?? 'normal',
            'category_id' => $data['category_id'] ?? null,
            'project_id' => $projectId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ticket = $this->repository->create($ticketData);

        // Add initial status history
        $this->repository->addStatusHistory($id, null, 'open', $userId, 'Ticket erstellt');

        // Trigger webhook
        $this->webhookService->trigger($userId, 'ticket.created', [
            'id' => $id,
            'title' => $ticket['title'],
            'priority' => $ticket['priority'],
            'message' => 'Neues Ticket erstellt: ' . $ticket['title'],
        ]);

        return JsonResponse::created(['ticket' => $ticket], 'Ticket erstellt');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody();

        $ticket = $this->repository->findById($id);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden', 404);
        }

        if (!$this->canEditTicket($request, $ticket)) {
            return JsonResponse::error('Keine Berechtigung', 403);
        }

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = trim($data['title']);
        }

        if (isset($data['description'])) {
            $updateData['description'] = trim($data['description']);
        }

        if (isset($data['priority'])) {
            $updateData['priority'] = $data['priority'];
        }

        if (isset($data['category_id'])) {
            $updateData['category_id'] = $data['category_id'] ?: null;
        }

        if (isset($data['project_id'])) {
            $updateData['project_id'] = $data['project_id'] ?: null;
        }

        if (isset($data['due_date'])) {
            $updateData['due_date'] = $data['due_date'] ?: null;
        }

        if (!empty($updateData)) {
            $this->repository->update($id, $updateData);
        }

        $updatedTicket = $this->repository->findById($id);

        return JsonResponse::success(['ticket' => $updatedTicket]);
    }

    public function updateStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody();

        $ticket = $this->repository->findById($id);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden', 404);
        }

        if (!$this->canChangeStatus($request, $ticket)) {
            return JsonResponse::error('Keine Berechtigung', 403);
        }

        $newStatus = $data['status'] ?? null;
        $validStatuses = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];

        if (!in_array($newStatus, $validStatuses)) {
            throw new ValidationException('Ungültiger Status');
        }

        $oldStatus = $ticket['status'];

        $updateData = ['status' => $newStatus];

        // Set timestamps
        if ($newStatus === 'resolved' && !$ticket['resolved_at']) {
            $updateData['resolved_at'] = date('Y-m-d H:i:s');
        }

        if ($newStatus === 'closed' && !$ticket['closed_at']) {
            $updateData['closed_at'] = date('Y-m-d H:i:s');
        }

        // Track first response
        if ($oldStatus === 'open' && $newStatus !== 'open' && !$ticket['first_response_at']) {
            $updateData['first_response_at'] = date('Y-m-d H:i:s');
        }

        $this->repository->update($id, $updateData);
        $this->repository->addStatusHistory($id, $oldStatus, $newStatus, $userId, $data['comment'] ?? null);

        $updatedTicket = $this->repository->findById($id);

        // Trigger webhook for status change
        $this->webhookService->trigger($userId, 'ticket.status_changed', [
            'id' => $id,
            'title' => $ticket['title'],
            'status' => $newStatus,
            'old_status' => $oldStatus,
            'message' => 'Ticket-Status geändert: ' . $ticket['title'] . ' (' . $oldStatus . ' → ' . $newStatus . ')',
        ]);

        // Additional webhook for resolved status
        if ($newStatus === 'resolved') {
            $this->webhookService->trigger($userId, 'ticket.resolved', [
                'id' => $id,
                'title' => $ticket['title'],
                'message' => 'Ticket gelöst: ' . $ticket['title'],
            ]);
        }

        return JsonResponse::success(['ticket' => $updatedTicket]);
    }

    public function assign(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody();

        $ticket = $this->repository->findById($id);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden', 404);
        }

        if (!$this->canAssignTicket($request)) {
            return JsonResponse::error('Keine Berechtigung', 403);
        }

        $assignTo = $data['assigned_to'] ?? null;

        $this->repository->update($id, ['assigned_to' => $assignTo]);

        // If assigning and status is open, move to in_progress
        if ($assignTo && $ticket['status'] === 'open') {
            $this->repository->update($id, ['status' => 'in_progress']);
            $this->repository->addStatusHistory($id, 'open', 'in_progress', $userId, 'Ticket zugewiesen');
        }

        $updatedTicket = $this->repository->findById($id);

        return JsonResponse::success(['ticket' => $updatedTicket]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $id = $this->getRouteArg($request, 'id');

        $ticket = $this->repository->findById($id);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden', 404);
        }

        if (!$this->canDeleteTicket($request)) {
            return JsonResponse::error('Keine Berechtigung', 403);
        }

        $this->repository->delete($id);

        return JsonResponse::success(null, 'Ticket gelöscht');
    }

    // ========================================================================
    // Comments
    // ========================================================================

    public function addComment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $ticketId = $this->getRouteArg($request, 'id');
        $data = $request->getParsedBody();

        $ticket = $this->repository->findById($ticketId);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden', 404);
        }

        if (!$this->canAccessTicket($request, $ticket)) {
            return JsonResponse::error('Keine Berechtigung', 403);
        }

        if (empty($data['content'])) {
            throw new ValidationException('Kommentar ist erforderlich');
        }

        $isInternal = !empty($data['is_internal']) && $this->canViewInternalComments($request);

        $commentData = [
            'id' => $this->generateUuid(),
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'content' => trim($data['content']),
            'is_internal' => $isInternal ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $comment = $this->repository->addComment($commentData);

        // Update ticket timestamp
        $this->repository->update($ticketId, ['updated_at' => date('Y-m-d H:i:s')]);

        // Track first response if staff is responding
        if ($ticket['status'] === 'open' && $ticket['user_id'] !== $userId && !$ticket['first_response_at']) {
            $this->repository->update($ticketId, ['first_response_at' => date('Y-m-d H:i:s')]);
        }

        return JsonResponse::created(['comment' => $comment], 'Kommentar hinzugefügt');
    }

    // ========================================================================
    // Public Ticket Access
    // ========================================================================

    public function createPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();

        // Verify hCaptcha token
        $captchaToken = $data['captcha_token'] ?? '';
        if (!$this->verifyCaptcha($captchaToken)) {
            throw new ValidationException('Captcha-Verifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
        }

        if (empty($data['title'])) {
            throw new ValidationException('Titel ist erforderlich');
        }

        if (empty($data['description'])) {
            throw new ValidationException('Beschreibung ist erforderlich');
        }

        if (empty($data['guest_name'])) {
            throw new ValidationException('Name ist erforderlich');
        }

        if (empty($data['guest_email']) || !filter_var($data['guest_email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Gültige E-Mail ist erforderlich');
        }

        $id = $this->generateUuid();
        $accessCode = $this->repository->generateAccessCode();

        $ticketData = [
            'id' => $id,
            'access_code' => $accessCode,
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'status' => 'open',
            'priority' => 'normal',
            'category_id' => $data['category_id'] ?? null,
            'guest_name' => trim($data['guest_name']),
            'guest_email' => trim($data['guest_email']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ticket = $this->repository->create($ticketData);
        $this->repository->addStatusHistory($id, null, 'open', null, 'Ticket erstellt (öffentlich)');

        return JsonResponse::created([
            'ticket' => [
                'id' => $ticket['id'],
                'ticket_number' => $ticket['ticket_number'],
                'access_code' => $accessCode,
                'title' => $ticket['title'],
                'status' => $ticket['status'],
            ],
            'access_code' => $accessCode,
            'message' => 'Ticket erstellt! Speichere deinen Zugangs-Code: ' . $accessCode,
        ]);
    }

    public function showPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $code = $this->getRouteArg($request, 'code');

        $ticket = $this->repository->findByAccessCode($code);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden. Prüfe deinen Zugangs-Code.', 404);
        }

        // Get public comments only
        $comments = $this->repository->getComments($ticket['id'], false);

        // Filter sensitive data
        unset($ticket['user_id'], $ticket['assigned_to'], $ticket['assigned_group_id']);

        return JsonResponse::success([
            'ticket' => $ticket,
            'comments' => $comments,
        ]);
    }

    public function addPublicComment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $code = $this->getRouteArg($request, 'code');
        $data = $request->getParsedBody();

        $ticket = $this->repository->findByAccessCode($code);

        if (!$ticket) {
            return JsonResponse::error('Ticket nicht gefunden', 404);
        }

        if ($ticket['status'] === 'closed') {
            return JsonResponse::error('Ticket ist geschlossen', 400);
        }

        if (empty($data['content'])) {
            throw new ValidationException('Kommentar ist erforderlich');
        }

        $commentData = [
            'id' => $this->generateUuid(),
            'ticket_id' => $ticket['id'],
            'guest_name' => $ticket['guest_name'],
            'content' => trim($data['content']),
            'is_internal' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $comment = $this->repository->addComment($commentData);

        // Update ticket and set to waiting if it was resolved
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        if ($ticket['status'] === 'resolved') {
            $updateData['status'] = 'open';
            $this->repository->addStatusHistory($ticket['id'], 'resolved', 'open', null, 'Kunde hat geantwortet');
        }
        $this->repository->update($ticket['id'], $updateData);

        return JsonResponse::created(['comment' => $comment]);
    }

    // ========================================================================
    // Categories
    // ========================================================================

    public function getCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $nested = !empty($params['nested']);

        $categories = $this->repository->getCategories(true, $nested);

        return JsonResponse::success(['categories' => $categories]);
    }

    // ========================================================================
    // Statistics
    // ========================================================================

    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        // Get stats for assigned tickets or all (depending on permissions)
        $assignedStats = $this->repository->getStats($userId);
        $allStats = $this->canViewAllTickets($request) ? $this->repository->getStats() : null;

        return JsonResponse::success([
            'my_stats' => $assignedStats,
            'all_stats' => $allStats,
        ]);
    }

    // ========================================================================
    // Permission Helpers
    // ========================================================================

    private function canViewAllTickets(ServerRequestInterface $request): bool
    {
        $userId = $request->getAttribute('user_id');
        return $this->rbacManager->hasPermission($userId, 'tickets.manage');
    }

    private function canAccessTicket(ServerRequestInterface $request, array $ticket): bool
    {
        $userId = $request->getAttribute('user_id');

        // Owner can access
        if ($ticket['user_id'] === $userId) {
            return true;
        }

        // Assigned user can access
        if ($ticket['assigned_to'] === $userId) {
            return true;
        }

        // Staff with tickets.manage can access all
        return $this->canViewAllTickets($request);
    }

    private function canEditTicket(ServerRequestInterface $request, array $ticket): bool
    {
        $userId = $request->getAttribute('user_id');

        // Owner can edit
        if ($ticket['user_id'] === $userId) {
            return true;
        }

        // Staff with tickets.manage can edit
        return $this->rbacManager->hasPermission($userId, 'tickets.manage');
    }

    private function canChangeStatus(ServerRequestInterface $request, array $ticket): bool
    {
        return $this->canAccessTicket($request, $ticket);
    }

    private function canAssignTicket(ServerRequestInterface $request): bool
    {
        $userId = $request->getAttribute('user_id');
        return $this->rbacManager->hasPermission($userId, 'tickets.manage');
    }

    private function canDeleteTicket(ServerRequestInterface $request): bool
    {
        $userId = $request->getAttribute('user_id');
        return $this->rbacManager->hasPermission($userId, 'tickets.manage');
    }

    private function canViewInternalComments(ServerRequestInterface $request): bool
    {
        $userId = $request->getAttribute('user_id');
        return $this->rbacManager->hasPermission($userId, 'tickets.manage');
    }

    /**
     * Verify hCaptcha token
     */
    private function verifyCaptcha(string $token): bool
    {
        // Get secret key from environment
        $secretKey = $_ENV['HCAPTCHA_SECRET_KEY'] ?? '';

        // If no secret key configured, skip verification (for development)
        // In production, you should always have a secret key set
        if (empty($secretKey)) {
            // For test tokens, always pass
            if ($token === '10000000-aaaa-bbbb-cccc-000000000001') {
                return true;
            }
            // No secret key configured - skip verification in dev mode
            return !empty($token);
        }

        if (empty($token)) {
            return false;
        }

        // Verify with hCaptcha API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://hcaptcha.com/siteverify',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secretKey,
                'response' => $token,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return false;
        }

        $result = json_decode($response, true);
        return $result['success'] ?? false;
    }
}
