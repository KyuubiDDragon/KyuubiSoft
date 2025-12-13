<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Services\ProjectAccessService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class InvoiceController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ProjectAccessService $projectAccess
    ) {}

    // ============ CLIENTS ============

    public function getClients(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $clients = $this->db->fetchAllAssociative(
            'SELECT c.*,
                (SELECT COUNT(*) FROM invoices WHERE client_id = c.id) as invoice_count,
                (SELECT SUM(total) FROM invoices WHERE client_id = c.id AND status = "paid") as total_paid
             FROM clients c WHERE c.user_id = ? ORDER BY c.name',
            [$userId]
        );

        return JsonResponse::success(['items' => $clients]);
    }

    public function createClient(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            throw new ValidationException('Client name is required');
        }

        $id = Uuid::uuid4()->toString();

        $this->db->insert('clients', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $data['name'],
            'company' => $data['company'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Deutschland',
            'vat_id' => $data['vat_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'default_hourly_rate' => $data['default_hourly_rate'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
        ]);

        $client = $this->db->fetchAssociative('SELECT * FROM clients WHERE id = ?', [$id]);

        return JsonResponse::created($client, 'Client created');
    }

    public function updateClient(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $client = $this->db->fetchAssociative(
            'SELECT * FROM clients WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$client) {
            throw new NotFoundException('Client not found');
        }

        $updates = [];
        $params = [];

        $fields = ['name', 'company', 'email', 'phone', 'address_line1', 'address_line2',
                   'city', 'postal_code', 'country', 'vat_id', 'notes', 'default_hourly_rate', 'color'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE clients SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Client updated');
    }

    public function deleteClient(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $this->db->delete('clients', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Client deleted');
    }

    // ============ INVOICES ============

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $status = $queryParams['status'] ?? null;
        $clientId = $queryParams['client_id'] ?? null;
        $projectId = $queryParams['project_id'] ?? null;

        // Check project access for restricted users
        $isRestricted = $this->projectAccess->isUserRestricted($userId);
        $accessibleProjectIds = $isRestricted ? $this->projectAccess->getUserAccessibleProjectIds($userId) : [];

        // Validate requested project_id access
        if ($projectId && $isRestricted && !in_array($projectId, $accessibleProjectIds)) {
            return JsonResponse::error('Keine Berechtigung für dieses Projekt', 403);
        }

        $sql = 'SELECT i.*, c.name as client_name FROM invoices i
                LEFT JOIN clients c ON i.client_id = c.id
                WHERE i.user_id = ?';
        $params = [$userId];

        // Filter by accessible projects for restricted users
        if ($isRestricted && !$projectId) {
            if (empty($accessibleProjectIds)) {
                return JsonResponse::success(['items' => []]);
            }
            $placeholders = implode(',', array_fill(0, count($accessibleProjectIds), '?'));
            $sql .= " AND i.project_id IN ({$placeholders})";
            $params = array_merge($params, $accessibleProjectIds);
        }

        if ($status) {
            $sql .= ' AND i.status = ?';
            $params[] = $status;
        }
        if ($clientId) {
            $sql .= ' AND i.client_id = ?';
            $params[] = $clientId;
        }
        if ($projectId) {
            $sql .= ' AND i.project_id = ?';
            $params[] = $projectId;
        }

        $sql .= ' ORDER BY i.issue_date DESC, i.invoice_number DESC';

        $invoices = $this->db->fetchAllAssociative($sql, $params);

        return JsonResponse::success(['items' => $invoices]);
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

        $id = Uuid::uuid4()->toString();

        // Generate invoice number
        $year = date('Y');
        $lastNumber = $this->db->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(invoice_number, -4) AS UNSIGNED))
             FROM invoices WHERE user_id = ? AND invoice_number LIKE ?",
            [$userId, "RE-{$year}-%"]
        );
        $nextNumber = ($lastNumber ?? 0) + 1;
        $invoiceNumber = sprintf("RE-%s-%04d", $year, $nextNumber);

        // Get client data if client_id provided
        $clientData = [];
        if (!empty($data['client_id'])) {
            $client = $this->db->fetchAssociative(
                'SELECT * FROM clients WHERE id = ? AND user_id = ?',
                [$data['client_id'], $userId]
            );
            if ($client) {
                $clientData = [
                    'client_name' => $client['name'],
                    'client_company' => $client['company'],
                    'client_address' => implode("\n", array_filter([
                        $client['address_line1'],
                        $client['address_line2'],
                        $client['postal_code'] . ' ' . $client['city'],
                        $client['country'],
                    ])),
                    'client_email' => $client['email'],
                    'client_vat_id' => $client['vat_id'],
                ];
            }
        }

        // Get user settings for sender info
        $user = $this->db->fetchAssociative('SELECT * FROM users WHERE id = ?', [$userId]);
        $userSettings = $this->db->fetchAssociative(
            'SELECT * FROM user_settings WHERE user_id = ?',
            [$userId]
        );
        $settings = $userSettings ? json_decode($userSettings['settings'], true) : [];

        $this->db->insert('invoices', array_merge([
            'id' => $id,
            'user_id' => $userId,
            'client_id' => $data['client_id'] ?? null,
            'project_id' => $projectId,
            'invoice_number' => $invoiceNumber,
            'status' => 'draft',
            'issue_date' => $data['issue_date'] ?? date('Y-m-d'),
            'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+14 days')),
            'tax_rate' => $data['tax_rate'] ?? 19.00,
            'currency' => $data['currency'] ?? 'EUR',
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? null,
            'sender_name' => $settings['invoice_sender_name'] ?? $user['name'],
            'sender_company' => $settings['invoice_company'] ?? null,
            'sender_address' => $settings['invoice_address'] ?? null,
            'sender_email' => $settings['invoice_email'] ?? $user['email'],
            'sender_phone' => $settings['invoice_phone'] ?? null,
            'sender_vat_id' => $settings['invoice_vat_id'] ?? null,
            'sender_bank_details' => $settings['invoice_bank_details'] ?? null,
        ], $clientData));

        $invoice = $this->db->fetchAssociative('SELECT * FROM invoices WHERE id = ?', [$id]);

        return JsonResponse::created($invoice, 'Invoice created');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $invoice = $this->db->fetchAssociative(
            'SELECT i.*, c.name as client_name, p.name as project_name
             FROM invoices i
             LEFT JOIN clients c ON i.client_id = c.id
             LEFT JOIN projects p ON i.project_id = p.id
             WHERE i.id = ? AND i.user_id = ?',
            [$id, $userId]
        );

        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        // Get items
        $invoice['items'] = $this->db->fetchAllAssociative(
            'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order',
            [$id]
        );

        return JsonResponse::success($invoice);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $invoice = $this->db->fetchAssociative(
            'SELECT * FROM invoices WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        $updates = [];
        $params = [];

        $fields = ['client_id', 'project_id', 'status', 'issue_date', 'due_date', 'paid_date',
                   'tax_rate', 'currency', 'notes', 'terms'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE invoices SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        return JsonResponse::success(null, 'Invoice updated');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $this->db->delete('invoices', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Invoice deleted');
    }

    // ============ INVOICE ITEMS ============

    public function addItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $invoiceId = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $invoice = $this->db->fetchAssociative(
            'SELECT * FROM invoices WHERE id = ? AND user_id = ?',
            [$invoiceId, $userId]
        );

        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        $id = Uuid::uuid4()->toString();
        $quantity = (float) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $total = $quantity * $unitPrice;

        $this->db->insert('invoice_items', [
            'id' => $id,
            'invoice_id' => $invoiceId,
            'time_entry_id' => $data['time_entry_id'] ?? null,
            'description' => $data['description'],
            'quantity' => $quantity,
            'unit' => $data['unit'] ?? 'Stunde',
            'unit_price' => $unitPrice,
            'total' => $total,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        // Update invoice totals
        $this->recalculateInvoice($invoiceId);

        $item = $this->db->fetchAssociative('SELECT * FROM invoice_items WHERE id = ?', [$id]);

        return JsonResponse::created($item, 'Item added');
    }

    public function updateItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $invoiceId = $routeContext->getRoute()->getArgument('id');
        $itemId = $routeContext->getRoute()->getArgument('itemId');
        $data = $request->getParsedBody();

        $invoice = $this->db->fetchAssociative(
            'SELECT * FROM invoices WHERE id = ? AND user_id = ?',
            [$invoiceId, $userId]
        );

        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        $updates = [];
        $params = [];

        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }
        if (isset($data['quantity'])) {
            $updates[] = 'quantity = ?';
            $params[] = (float) $data['quantity'];
        }
        if (isset($data['unit'])) {
            $updates[] = 'unit = ?';
            $params[] = $data['unit'];
        }
        if (isset($data['unit_price'])) {
            $updates[] = 'unit_price = ?';
            $params[] = (float) $data['unit_price'];
        }

        // Recalculate item total
        if (isset($data['quantity']) || isset($data['unit_price'])) {
            $item = $this->db->fetchAssociative('SELECT * FROM invoice_items WHERE id = ?', [$itemId]);
            $quantity = $data['quantity'] ?? $item['quantity'];
            $unitPrice = $data['unit_price'] ?? $item['unit_price'];
            $updates[] = 'total = ?';
            $params[] = (float) $quantity * (float) $unitPrice;
        }

        if (!empty($updates)) {
            $params[] = $itemId;
            $this->db->executeStatement(
                'UPDATE invoice_items SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
        }

        $this->recalculateInvoice($invoiceId);

        return JsonResponse::success(null, 'Item updated');
    }

    public function deleteItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $invoiceId = $routeContext->getRoute()->getArgument('id');
        $itemId = $routeContext->getRoute()->getArgument('itemId');

        $invoice = $this->db->fetchAssociative(
            'SELECT * FROM invoices WHERE id = ? AND user_id = ?',
            [$invoiceId, $userId]
        );

        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        $this->db->delete('invoice_items', ['id' => $itemId, 'invoice_id' => $invoiceId]);
        $this->recalculateInvoice($invoiceId);

        return JsonResponse::success(null, 'Item deleted');
    }

    // ============ SPECIAL ACTIONS ============

    public function createFromTimeEntries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['time_entry_ids']) || !is_array($data['time_entry_ids'])) {
            throw new ValidationException('Time entry IDs are required');
        }

        // First create the invoice
        $request = $request->withParsedBody([
            'client_id' => $data['client_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
        ]);
        $invoiceResponse = $this->create($request, $response);

        // Get invoice ID from response
        $body = json_decode((string) $invoiceResponse->getBody(), true);
        $invoiceId = $body['data']['id'];

        // Add time entries as items
        $entries = $this->db->fetchAllAssociative(
            'SELECT * FROM time_entries WHERE id IN (?) AND user_id = ? AND is_billable = 1 AND invoiced = 0',
            [$data['time_entry_ids'], $userId],
            [Connection::PARAM_STR_ARRAY, \PDO::PARAM_STR]
        );

        foreach ($entries as $entry) {
            $hours = $entry['duration_seconds'] / 3600;
            $rate = $entry['hourly_rate'] ?? $data['hourly_rate'] ?? 50;

            $itemId = Uuid::uuid4()->toString();
            $this->db->insert('invoice_items', [
                'id' => $itemId,
                'invoice_id' => $invoiceId,
                'time_entry_id' => $entry['id'],
                'description' => $entry['task_name'] . ($entry['description'] ? "\n" . $entry['description'] : ''),
                'quantity' => round($hours, 2),
                'unit' => 'Stunde',
                'unit_price' => $rate,
                'total' => round($hours * $rate, 2),
            ]);

            // Mark as invoiced
            $this->db->update('time_entries', [
                'invoiced' => 1,
                'invoice_id' => $invoiceId,
            ], ['id' => $entry['id']]);
        }

        $this->recalculateInvoice($invoiceId);

        $invoice = $this->db->fetchAssociative('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        $invoice['items'] = $this->db->fetchAllAssociative(
            'SELECT * FROM invoice_items WHERE invoice_id = ?',
            [$invoiceId]
        );

        return JsonResponse::created($invoice, 'Invoice created from time entries');
    }

    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as total_paid,
                SUM(CASE WHEN status IN ('sent', 'overdue') THEN total ELSE 0 END) as total_outstanding
             FROM invoices WHERE user_id = ?",
            [$userId]
        );

        return JsonResponse::success($stats);
    }

    private function recalculateInvoice(string $invoiceId): void
    {
        $invoice = $this->db->fetchAssociative('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);

        $subtotal = (float) $this->db->fetchOne(
            'SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = ?',
            [$invoiceId]
        );

        $taxRate = (float) $invoice['tax_rate'];
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $this->db->update('invoices', [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ], ['id' => $invoiceId]);
    }
}
