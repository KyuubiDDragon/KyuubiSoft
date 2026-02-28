<?php

declare(strict_types=1);

namespace App\Modules\Contracts\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Webhooks\Services\WebhookService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class ContractController
{
    public function __construct(
        private readonly Connection $db,
        private readonly WebhookService $webhookService
    ) {}

    // ============ CONTRACTS ============

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $status = $queryParams['status'] ?? null;
        $clientId = $queryParams['client_id'] ?? null;
        $type = $queryParams['contract_type'] ?? null;

        $sql = 'SELECT c.*, cl.name as client_name, cl.company as client_company_name
                FROM contracts c
                LEFT JOIN clients cl ON c.client_id = cl.id
                WHERE c.user_id = ?';
        $params = [$userId];

        if ($status) {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }
        if ($clientId) {
            $sql .= ' AND c.client_id = ?';
            $params[] = $clientId;
        }
        if ($type) {
            $sql .= ' AND c.contract_type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $contracts = $this->db->fetchAllAssociative($sql, $params);

        return JsonResponse::success(['items' => $contracts]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['title'])) {
            throw new ValidationException('Contract title is required');
        }
        if (empty($data['contract_type'])) {
            throw new ValidationException('Contract type is required');
        }

        $id = Uuid::uuid4()->toString();

        // Generate contract number
        $type = $data['contract_type'];
        $prefixMap = [
            'license'     => 'LIZ',
            'development' => 'ENT',
            'saas'        => 'SAS',
            'maintenance' => 'WAR',
            'nda'         => 'NDA',
        ];
        $prefix = $prefixMap[$type] ?? 'VTR';
        $year = date('Y');
        $lastNumber = $this->db->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(contract_number, -4) AS UNSIGNED))
             FROM contracts WHERE user_id = ? AND contract_number LIKE ?",
            [$userId, "{$prefix}-{$year}-%"]
        );
        $nextNumber = ($lastNumber ?? 0) + 1;
        $contractNumber = sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);

        // Get sender info from user settings
        $user = $this->db->fetchAssociative('SELECT * FROM users WHERE id = ?', [$userId]);
        $settingsRows = $this->db->fetchAllAssociative(
            'SELECT `key`, `value` FROM user_settings WHERE user_id = ?',
            [$userId]
        );
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[$row['key']] = json_decode($row['value'], true);
        }

        // Get client data if provided
        $clientData = [];
        if (!empty($data['client_id'])) {
            $client = $this->db->fetchAssociative(
                'SELECT * FROM clients WHERE id = ? AND user_id = ?',
                [$data['client_id'], $userId]
            );
            if ($client) {
                $clientData = [
                    'party_b_name' => $client['name'],
                    'party_b_company' => $client['company'],
                    'party_b_address' => implode("\n", array_filter([
                        $client['address_line1'],
                        $client['address_line2'],
                        $client['postal_code'] . ' ' . $client['city'],
                        $client['country'],
                    ])),
                    'party_b_email' => $client['email'],
                    'party_b_vat_id' => $client['vat_id'],
                ];
            }
        }

        // Resolve template if content_html not provided
        $contentHtml = $data['content_html'] ?? '';
        $language = $data['language'] ?? 'de';
        $variablesData = $data['variables_data'] ?? [];
        $templateId = $data['template_id'] ?? null;

        if (empty($contentHtml)) {
            // Load default template for this contract type + language
            $template = null;
            if ($templateId) {
                $template = $this->db->fetchAssociative(
                    'SELECT * FROM contract_templates WHERE id = ? AND (user_id = ? OR user_id IS NULL)',
                    [$templateId, $userId]
                );
            }
            if (!$template) {
                $template = $this->db->fetchAssociative(
                    'SELECT * FROM contract_templates WHERE contract_type = ? AND language = ? AND is_default = 1 AND (user_id = ? OR user_id IS NULL)',
                    [$type, $language, $userId]
                );
            }
            if ($template) {
                $templateId = $template['id'];
                // Build template context from contract fields + variables_data
                $partyACompany = $data['party_a_company'] ?? $settings['invoice_company'] ?? '';
                $partyAAddress = $data['party_a_address'] ?? $settings['invoice_address'] ?? '';
                $partyAEmail = $data['party_a_email'] ?? $settings['invoice_email'] ?? $user['email'] ?? '';

                $context = array_merge(
                    $variablesData,
                    $this->buildTemplateLabels($variablesData, $language),
                    [
                        'start_date' => $data['start_date'] ?? date('Y-m-d'),
                        'end_date' => !empty($data['end_date']) ? $data['end_date'] : null,
                        'notice_period_days' => $data['notice_period_days'] ?? 30,
                        'total_value' => number_format((float) ($data['total_value'] ?? 0), 2, ',', '.'),
                        'currency' => $data['currency'] ?? 'EUR',
                        'auto_renewal' => !empty($data['auto_renewal']),
                        'governing_law' => $data['governing_law'] ?? 'DE',
                        'jurisdiction' => $data['jurisdiction'] ?? '',
                        'is_b2c' => !empty($data['is_b2c']),
                        'include_nda_clause' => !empty($data['include_nda_clause']),
                        'party_a_company' => $partyACompany,
                        'party_a_address' => $partyAAddress,
                        'party_a_email' => $partyAEmail,
                    ]
                );
                $contentHtml = $this->resolveTemplate($template['content_html'], $context);
            }
        }

        $this->db->insert('contracts', array_merge([
            'id' => $id,
            'user_id' => $userId,
            'client_id' => $data['client_id'] ?? null,
            'template_id' => $templateId,
            'contract_number' => $contractNumber,
            'title' => $data['title'],
            'contract_type' => $type,
            'language' => $language,
            'status' => 'draft',
            'content_html' => $contentHtml,
            'variables_data' => !empty($variablesData) ? json_encode($variablesData) : null,
            // Party A (sender)
            'party_a_name' => $data['party_a_name'] ?? $settings['invoice_sender_name'] ?? $user['name'],
            'party_a_company' => $data['party_a_company'] ?? $settings['invoice_company'] ?? null,
            'party_a_address' => $data['party_a_address'] ?? $settings['invoice_address'] ?? null,
            'party_a_email' => $data['party_a_email'] ?? $settings['invoice_email'] ?? $user['email'],
            'party_a_vat_id' => $data['party_a_vat_id'] ?? $settings['invoice_vat_id'] ?? null,
            // Party B from data or client
            'party_b_name' => $data['party_b_name'] ?? $clientData['party_b_name'] ?? null,
            'party_b_company' => $data['party_b_company'] ?? $clientData['party_b_company'] ?? null,
            'party_b_address' => $data['party_b_address'] ?? $clientData['party_b_address'] ?? null,
            'party_b_email' => $data['party_b_email'] ?? $clientData['party_b_email'] ?? null,
            'party_b_vat_id' => $data['party_b_vat_id'] ?? $clientData['party_b_vat_id'] ?? null,
            // Terms
            'start_date' => $data['start_date'] ?? date('Y-m-d'),
            'end_date' => !empty($data['end_date']) ? $data['end_date'] : null,
            'auto_renewal' => (int) ($data['auto_renewal'] ?? 0),
            'renewal_period' => $data['renewal_period'] ?? null,
            'notice_period_days' => (int) ($data['notice_period_days'] ?? 30),
            'total_value' => (float) ($data['total_value'] ?? 0),
            'currency' => $data['currency'] ?? 'EUR',
            'payment_schedule' => $data['payment_schedule'] ?? null,
            // Legal
            'governing_law' => $data['governing_law'] ?? 'DE',
            'jurisdiction' => $data['jurisdiction'] ?? null,
            'is_b2c' => (int) ($data['is_b2c'] ?? 0),
            'include_nda_clause' => (int) ($data['include_nda_clause'] ?? 1),
            'notes' => $data['notes'] ?? null,
        ], []));

        // Add history entry
        $this->addHistory($id, 'created', 'Vertrag erstellt', $userId);

        $contract = $this->db->fetchAssociative('SELECT * FROM contracts WHERE id = ?', [$id]);

        $this->webhookService->trigger($userId, 'contract.created', [
            'id' => $id,
            'contract_number' => $contractNumber,
            'title' => $data['title'],
            'type' => $type,
            'message' => 'Neuer Vertrag erstellt: ' . $contractNumber,
        ]);

        return JsonResponse::created($contract, 'Contract created');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $contract = $this->db->fetchAssociative(
            'SELECT c.*, cl.name as client_name
             FROM contracts c
             LEFT JOIN clients cl ON c.client_id = cl.id
             WHERE c.id = ? AND c.user_id = ?',
            [$id, $userId]
        );

        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        // Get linked invoices
        $contract['invoices'] = $this->db->fetchAllAssociative(
            'SELECT i.id, i.invoice_number, i.status, i.total, i.issue_date, i.currency
             FROM contract_invoices ci
             JOIN invoices i ON ci.invoice_id = i.id
             WHERE ci.contract_id = ?
             ORDER BY i.issue_date DESC',
            [$id]
        );

        return JsonResponse::success($contract);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $updates = [];
        $params = [];

        $fields = [
            'title', 'language', 'content_html', 'template_id', 'client_id',
            'party_a_name', 'party_a_company', 'party_a_address', 'party_a_email', 'party_a_vat_id',
            'party_b_name', 'party_b_company', 'party_b_address', 'party_b_email', 'party_b_vat_id',
            'start_date', 'end_date', 'auto_renewal', 'renewal_period', 'notice_period_days',
            'total_value', 'currency', 'payment_schedule',
            'governing_law', 'jurisdiction', 'is_b2c', 'include_nda_clause', 'notes',
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (in_array($field, ['end_date', 'start_date']) && $value === '') {
                    $value = null;
                }
                $updates[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (isset($data['variables_data'])) {
            $updates[] = 'variables_data = ?';
            $params[] = json_encode($data['variables_data']);
        }

        if (!empty($updates)) {
            $params[] = $id;
            $this->db->executeStatement(
                'UPDATE contracts SET ' . implode(', ', $updates) . ' WHERE id = ?',
                $params
            );
            $this->addHistory($id, 'edited', 'Vertrag bearbeitet', $userId);
        }

        return JsonResponse::success(null, 'Contract updated');
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $this->db->delete('contracts', ['id' => $id, 'user_id' => $userId]);

        return JsonResponse::success(null, 'Contract deleted');
    }

    public function updateStatus(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $newStatus = $data['status'] ?? null;
        $validStatuses = ['draft', 'sent', 'signed', 'active', 'expired', 'cancelled', 'terminated'];
        if (!$newStatus || !in_array($newStatus, $validStatuses)) {
            throw new ValidationException('Invalid status');
        }

        $this->db->update('contracts', ['status' => $newStatus], ['id' => $id]);

        $statusLabels = [
            'draft' => 'Entwurf',
            'sent' => 'Versendet',
            'signed' => 'Unterschrieben',
            'active' => 'Aktiv',
            'expired' => 'Abgelaufen',
            'cancelled' => 'Storniert',
            'terminated' => 'Gekündigt',
        ];
        $this->addHistory($id, 'status_changed', 'Status geändert: ' . ($statusLabels[$newStatus] ?? $newStatus), $userId);

        $this->webhookService->trigger($userId, 'contract.status_changed', [
            'id' => $id,
            'contract_number' => $contract['contract_number'],
            'old_status' => $contract['status'],
            'new_status' => $newStatus,
            'message' => 'Vertragsstatus geändert: ' . $contract['contract_number'] . ' → ' . ($statusLabels[$newStatus] ?? $newStatus),
        ]);

        return JsonResponse::success(null, 'Status updated');
    }

    public function sign(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $party = $data['party'] ?? null;
        if (!in_array($party, ['a', 'b'])) {
            throw new ValidationException('Party must be "a" or "b"');
        }

        if (empty($data['signature_data'])) {
            throw new ValidationException('Signature data is required');
        }

        $now = date('Y-m-d H:i:s');

        if ($party === 'a') {
            $this->db->update('contracts', [
                'party_a_signed_at' => $now,
                'party_a_signature_data' => $data['signature_data'],
            ], ['id' => $id]);
            $this->addHistory($id, 'signed', 'Auftragnehmer hat unterschrieben', $userId);
        } else {
            $this->db->update('contracts', [
                'party_b_signed_at' => $now,
                'party_b_signature_data' => $data['signature_data'],
            ], ['id' => $id]);
            $this->addHistory($id, 'signed', 'Auftraggeber hat unterschrieben', $userId);
        }

        // Check if both parties have signed
        $updated = $this->db->fetchAssociative('SELECT * FROM contracts WHERE id = ?', [$id]);
        if ($updated['party_a_signed_at'] && $updated['party_b_signed_at']) {
            $this->db->update('contracts', ['status' => 'signed'], ['id' => $id]);
            $this->addHistory($id, 'status_changed', 'Status geändert: Unterschrieben (beide Parteien)', $userId);
        }

        return JsonResponse::success(null, 'Contract signed');
    }

    public function duplicate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $sourceId = $routeContext->getRoute()->getArgument('id');

        $source = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$sourceId, $userId]
        );

        if (!$source) {
            throw new NotFoundException('Contract not found');
        }

        // Generate new contract number
        $type = $source['contract_type'];
        $prefixMap = [
            'license'     => 'LIZ',
            'development' => 'ENT',
            'saas'        => 'SAS',
            'maintenance' => 'WAR',
            'nda'         => 'NDA',
        ];
        $prefix = $prefixMap[$type] ?? 'VTR';
        $year = date('Y');
        $lastNumber = $this->db->fetchOne(
            "SELECT MAX(CAST(SUBSTRING(contract_number, -4) AS UNSIGNED))
             FROM contracts WHERE user_id = ? AND contract_number LIKE ?",
            [$userId, "{$prefix}-{$year}-%"]
        );
        $nextNumber = ($lastNumber ?? 0) + 1;
        $contractNumber = sprintf('%s-%s-%04d', $prefix, $year, $nextNumber);

        $newId = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('contracts', array_merge(
            array_intersect_key($source, array_flip([
                'user_id', 'client_id', 'template_id', 'title', 'contract_type', 'language',
                'content_html', 'variables_data',
                'party_a_name', 'party_a_company', 'party_a_address', 'party_a_email', 'party_a_vat_id',
                'party_b_name', 'party_b_company', 'party_b_address', 'party_b_email', 'party_b_vat_id',
                'auto_renewal', 'renewal_period', 'notice_period_days',
                'total_value', 'currency', 'payment_schedule',
                'governing_law', 'jurisdiction', 'is_b2c', 'include_nda_clause', 'notes',
            ])),
            [
                'id' => $newId,
                'contract_number' => $contractNumber,
                'status' => 'draft',
                'start_date' => date('Y-m-d'),
                'end_date' => null,
                'party_a_signed_at' => null,
                'party_a_signature_data' => null,
                'party_b_signed_at' => null,
                'party_b_signature_data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ));

        $this->addHistory($newId, 'created', 'Vertrag dupliziert von ' . $source['contract_number'], $userId);

        $newContract = $this->db->fetchAssociative('SELECT * FROM contracts WHERE id = ?', [$newId]);

        return JsonResponse::success($newContract, 'Contract duplicated');
    }

    public function getStats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $stats = $this->db->fetchAssociative(
            "SELECT
                COUNT(*) as total_contracts,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END) as signed_count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN status IN ('active','signed') THEN total_value ELSE 0 END) as total_active_value,
                SUM(total_value) as total_value
             FROM contracts WHERE user_id = ?",
            [$userId]
        );

        return JsonResponse::success($stats);
    }

    // ============ HISTORY ============

    public function getHistory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $contract = $this->db->fetchAssociative(
            'SELECT id FROM contracts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );

        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $history = $this->db->fetchAllAssociative(
            'SELECT h.*, u.name as performed_by_name
             FROM contract_history h
             LEFT JOIN users u ON h.performed_by = u.id
             WHERE h.contract_id = ?
             ORDER BY h.created_at DESC',
            [$id]
        );

        return JsonResponse::success(['items' => $history]);
    }

    // ============ INVOICE LINKING ============

    public function linkInvoice(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $contractId = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$contractId, $userId]
        );
        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $invoiceId = $data['invoice_id'] ?? null;
        if (!$invoiceId) {
            throw new ValidationException('Invoice ID is required');
        }

        $invoice = $this->db->fetchAssociative(
            'SELECT * FROM invoices WHERE id = ? AND user_id = ?',
            [$invoiceId, $userId]
        );
        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        // Check if already linked
        $existing = $this->db->fetchOne(
            'SELECT COUNT(*) FROM contract_invoices WHERE contract_id = ? AND invoice_id = ?',
            [$contractId, $invoiceId]
        );
        if ($existing > 0) {
            throw new ValidationException('Invoice is already linked to this contract');
        }

        $this->db->insert('contract_invoices', [
            'id' => Uuid::uuid4()->toString(),
            'contract_id' => $contractId,
            'invoice_id' => $invoiceId,
        ]);

        $this->addHistory($contractId, 'invoice_linked', 'Rechnung verknuepft: ' . $invoice['invoice_number'], $userId);

        return JsonResponse::success(null, 'Invoice linked');
    }

    public function unlinkInvoice(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $contractId = $routeContext->getRoute()->getArgument('id');
        $invoiceId = $routeContext->getRoute()->getArgument('invoiceId');

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$contractId, $userId]
        );
        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $this->db->delete('contract_invoices', [
            'contract_id' => $contractId,
            'invoice_id' => $invoiceId,
        ]);

        $this->addHistory($contractId, 'invoice_unlinked', 'Rechnungsverknüpfung entfernt', $userId);

        return JsonResponse::success(null, 'Invoice unlinked');
    }

    public function getLinkedInvoices(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $contractId = $routeContext->getRoute()->getArgument('id');

        $contract = $this->db->fetchAssociative(
            'SELECT id FROM contracts WHERE id = ? AND user_id = ?',
            [$contractId, $userId]
        );
        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $invoices = $this->db->fetchAllAssociative(
            'SELECT i.*, ci.created_at as linked_at
             FROM contract_invoices ci
             JOIN invoices i ON ci.invoice_id = i.id
             WHERE ci.contract_id = ?
             ORDER BY i.issue_date DESC',
            [$contractId]
        );

        return JsonResponse::success(['items' => $invoices]);
    }

    // ============ SHARING ============

    public function enableShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');
        $data = $request->getParsedBody();

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $token = bin2hex(random_bytes(32));
        $password = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;

        $this->db->update('contracts', [
            'share_token' => $token,
            'share_password' => $password,
            'share_expires_at' => $expiresAt,
            'share_view_count' => 0,
        ], ['id' => $id]);

        $this->addHistory($id, 'shared', 'Share-Link erstellt', $userId);

        return JsonResponse::success([
            'token' => $token,
            'has_password' => !empty($password),
            'expires_at' => $expiresAt,
        ], 'Share-Link erstellt');
    }

    public function disableShare(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        $this->db->update('contracts', [
            'share_token' => null,
            'share_password' => null,
            'share_expires_at' => null,
            'share_view_count' => 0,
        ], ['id' => $id]);

        $this->addHistory($id, 'share_disabled', 'Share-Link deaktiviert', $userId);

        return JsonResponse::success(null, 'Share-Link deaktiviert');
    }

    public function getShareInfo(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $routeContext = RouteContext::fromRequest($request);
        $id = $routeContext->getRoute()->getArgument('id');

        $contract = $this->db->fetchAssociative(
            'SELECT share_token, share_password, share_expires_at, share_view_count FROM contracts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if (!$contract) {
            throw new NotFoundException('Contract not found');
        }

        return JsonResponse::success([
            'active' => !empty($contract['share_token']),
            'token' => $contract['share_token'],
            'has_password' => !empty($contract['share_password']),
            'expires_at' => $contract['share_expires_at'],
            'view_count' => (int) $contract['share_view_count'],
        ]);
    }

    public function showPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        $token = $routeContext->getRoute()->getArgument('token');
        $data = array_merge($request->getQueryParams(), (array) ($request->getParsedBody() ?? []));

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE share_token = ?',
            [$token]
        );

        if (!$contract) {
            throw new NotFoundException('Vertrag nicht gefunden');
        }

        if ($contract['share_expires_at'] && $contract['share_expires_at'] < date('Y-m-d H:i:s')) {
            return JsonResponse::error('Dieser Link ist abgelaufen', 403);
        }

        if ($contract['share_password']) {
            $pw = $data['password'] ?? '';
            if (empty($pw)) {
                return JsonResponse::success(['requires_password' => true], 'Passwort erforderlich');
            }
            if (!password_verify($pw, $contract['share_password'])) {
                return JsonResponse::error('Falsches Passwort', 401);
            }
        }

        $this->db->executeStatement(
            'UPDATE contracts SET share_view_count = share_view_count + 1 WHERE id = ?',
            [$contract['id']]
        );

        // Return sanitized contract data (no internal fields like share_token/password)
        return JsonResponse::success([
            'id' => $contract['id'],
            'contract_number' => $contract['contract_number'],
            'title' => $contract['title'],
            'contract_type' => $contract['contract_type'],
            'language' => $contract['language'],
            'content_html' => $contract['content_html'] ?? null,
            'clauses_html' => $contract['clauses_html'] ?? null,
            'status' => $contract['status'],
            'party_a_name' => $contract['party_a_name'],
            'party_a_company' => $contract['party_a_company'] ?? null,
            'party_a_address' => $contract['party_a_address'] ?? null,
            'party_a_email' => $contract['party_a_email'] ?? null,
            'party_b_name' => $contract['party_b_name'],
            'party_b_company' => $contract['party_b_company'] ?? null,
            'party_b_address' => $contract['party_b_address'] ?? null,
            'party_b_email' => $contract['party_b_email'] ?? null,
            'start_date' => $contract['start_date'],
            'end_date' => $contract['end_date'],
            'total_value' => $contract['total_value'],
            'currency' => $contract['currency'],
            'payment_schedule' => $contract['payment_schedule'] ?? null,
            'notice_period_days' => $contract['notice_period_days'] ?? null,
            'notes' => $contract['notes'] ?? null,
            'party_a_signed_at' => $contract['party_a_signed_at'],
            'party_b_signed_at' => $contract['party_b_signed_at'],
        ]);
    }

    public function signPublic(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        $token = $routeContext->getRoute()->getArgument('token');
        $data = $request->getParsedBody();

        $contract = $this->db->fetchAssociative(
            'SELECT * FROM contracts WHERE share_token = ?',
            [$token]
        );

        if (!$contract) {
            throw new NotFoundException('Vertrag nicht gefunden');
        }

        if ($contract['share_expires_at'] && $contract['share_expires_at'] < date('Y-m-d H:i:s')) {
            return JsonResponse::error('Dieser Link ist abgelaufen', 403);
        }

        // Verify password if set
        if ($contract['share_password']) {
            $pw = $data['password'] ?? '';
            if (!password_verify($pw, $contract['share_password'])) {
                return JsonResponse::error('Falsches Passwort', 401);
            }
        }

        if (empty($data['signature_data'])) {
            throw new ValidationException('Unterschrift ist erforderlich');
        }

        if ($contract['party_b_signed_at']) {
            return JsonResponse::error('Vertrag wurde bereits unterschrieben', 400);
        }

        $now = date('Y-m-d H:i:s');

        $this->db->update('contracts', [
            'party_b_signed_at' => $now,
            'party_b_signature_data' => $data['signature_data'],
        ], ['id' => $contract['id']]);

        $this->addHistory($contract['id'], 'signed', 'Auftraggeber hat online unterschrieben (via Share-Link)', $contract['user_id']);

        // Check if both parties have signed
        if ($contract['party_a_signed_at']) {
            $this->db->update('contracts', ['status' => 'signed'], ['id' => $contract['id']]);
            $this->addHistory($contract['id'], 'status_changed', 'Status geändert: Unterschrieben (beide Parteien)', $contract['user_id']);
        }

        return JsonResponse::success(null, 'Vertrag erfolgreich unterschrieben');
    }

    // ============ HELPERS ============

    private function addHistory(string $contractId, string $action, string $details, string $performedBy): void
    {
        $this->db->insert('contract_history', [
            'id' => Uuid::uuid4()->toString(),
            'contract_id' => $contractId,
            'action' => $action,
            'details' => $details,
            'performed_by' => $performedBy,
        ]);
    }

    /**
     * Resolve Mustache-style template placeholders.
     * Supports: {{variable}}, {{#flag}}...{{/flag}}, {{^flag}}...{{/flag}}
     */
    private function resolveTemplate(string $html, array $context): string
    {
        // First resolve conditional sections {{#flag}}...{{/flag}} and {{^flag}}...{{/flag}}
        // Process nested sections by iterating until no more changes
        $maxIterations = 10;
        for ($i = 0; $i < $maxIterations; $i++) {
            $previous = $html;

            // Positive sections: {{#flag}}content{{/flag}} — show if truthy
            $html = preg_replace_callback(
                '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
                function ($matches) use ($context) {
                    $key = $matches[1];
                    $content = $matches[2];
                    $value = $context[$key] ?? null;
                    if ($value && $value !== '0' && $value !== 0) {
                        return $content;
                    }
                    return '';
                },
                $html
            );

            // Negative sections: {{^flag}}content{{/flag}} — show if falsy
            $html = preg_replace_callback(
                '/\{\{\^(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
                function ($matches) use ($context) {
                    $key = $matches[1];
                    $content = $matches[2];
                    $value = $context[$key] ?? null;
                    if (!$value || $value === '0' || $value === 0) {
                        return $content;
                    }
                    return '';
                },
                $html
            );

            if ($html === $previous) {
                break;
            }
        }

        // Then resolve simple variables {{variable}}
        $html = preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            function ($matches) use ($context) {
                $key = $matches[1];
                $value = $context[$key] ?? '';
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            },
            $html
        );

        return $html;
    }

    /**
     * Build human-readable labels from raw variable values based on language.
     */
    private function buildTemplateLabels(array $vars, string $lang): array
    {
        $isDe = $lang === 'de';
        $labels = [];

        // License type
        $licenseTypes = $isDe
            ? ['simple' => 'einfache (nicht-ausschließliche)', 'exclusive' => 'ausschließliche']
            : ['simple' => 'non-exclusive', 'exclusive' => 'exclusive'];
        $labels['license_type_label'] = $licenseTypes[$vars['license_type'] ?? ''] ?? ($vars['license_type'] ?? '');

        // Territory
        $territories = $isDe
            ? ['worldwide' => 'weltweit', 'eu' => 'Europäische Union', 'dach' => 'DACH-Region (DE/AT/CH)', 'de' => 'Deutschland', 'custom' => 'wie vereinbart']
            : ['worldwide' => 'worldwide', 'eu' => 'European Union', 'dach' => 'DACH Region (DE/AT/CH)', 'de' => 'Germany', 'custom' => 'as agreed'];
        $labels['territory_label'] = $territories[$vars['territory'] ?? ''] ?? ($vars['territory'] ?? '');

        // Support level
        $supportLevels = $isDe
            ? ['basic' => 'Basis (E-Mail)', 'standard' => 'Standard (E-Mail + Telefon)', 'premium' => 'Premium (24/7)', 'none' => 'Kein Support']
            : ['basic' => 'Basic (Email)', 'standard' => 'Standard (Email + Phone)', 'premium' => 'Premium (24/7)', 'none' => 'No Support'];
        $labels['support_level_label'] = $supportLevels[$vars['support_level'] ?? ''] ?? ($vars['support_level'] ?? '');

        // Payment schedule
        $schedules = $isDe
            ? ['once' => 'einmalig', 'monthly' => 'monatlich', 'quarterly' => 'quartalsweise', 'yearly' => 'jaehrlich', 'milestone' => 'nach Meilensteinen']
            : ['once' => 'one-time', 'monthly' => 'monthly', 'quarterly' => 'quarterly', 'yearly' => 'annually', 'milestone' => 'per milestone'];
        $labels['payment_schedule_label'] = $schedules[$vars['payment_schedule'] ?? ''] ?? ($vars['payment_schedule'] ?? '');

        // Governing law
        $laws = $isDe
            ? ['DE' => 'Bundesrepublik Deutschland', 'AT' => 'Republik Österreich', 'CH' => 'Schweizerische Eidgenossenschaft', 'DK' => 'Königreich Dänemark']
            : ['DE' => 'Federal Republic of Germany', 'AT' => 'Republic of Austria', 'CH' => 'Swiss Confederation', 'DK' => 'Kingdom of Denmark'];
        $labels['governing_law_label'] = $laws[$vars['governing_law'] ?? ''] ?? ($vars['governing_law'] ?? '');

        // Subscription model (SaaS)
        $subModels = $isDe
            ? ['monthly' => 'Monat', 'quarterly' => 'Quartal', 'yearly' => 'Jahr']
            : ['monthly' => 'month', 'quarterly' => 'quarter', 'yearly' => 'year'];
        $labels['subscription_model_label'] = $subModels[$vars['subscription_model'] ?? ''] ?? ($vars['subscription_model'] ?? '');

        // Response time (Maintenance)
        $responseTimes = $isDe
            ? ['4h' => '4 Stunden', '8h' => '8 Stunden', '24h' => '24 Stunden', '48h' => '48 Stunden']
            : ['4h' => '4 hours', '8h' => '8 hours', '24h' => '24 hours', '48h' => '48 hours'];
        $labels['response_time_label'] = $responseTimes[$vars['response_time'] ?? ''] ?? ($vars['response_time'] ?? '');

        // NDA type
        $ndaTypes = $isDe
            ? ['unilateral' => 'Einseitig', 'mutual' => 'Gegenseitig']
            : ['unilateral' => 'Unilateral', 'mutual' => 'Mutual'];
        $labels['nda_type_label'] = $ndaTypes[$vars['nda_type'] ?? ''] ?? ($vars['nda_type'] ?? '');

        // Data location (SaaS)
        $dataLocations = $isDe
            ? ['eu' => 'Europäische Union', 'de' => 'Deutschland', 'us' => 'Vereinigte Staaten', 'custom' => 'wie vereinbart']
            : ['eu' => 'European Union', 'de' => 'Germany', 'us' => 'United States', 'custom' => 'as agreed'];
        $labels['data_location_label'] = $dataLocations[$vars['data_location'] ?? ''] ?? ($vars['data_location'] ?? '');

        return $labels;
    }
}
