<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

class IncomeController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    // ─── Income Entries ───────────────────────────────────────────────────────

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;
        $from = $params['from'] ?? date('Y-m-01');
        $to = $params['to'] ?? date('Y-m-t');
        $categoryId = $params['category_id'] ?? null;

        $where = 'i.user_id = ? AND i.income_date BETWEEN ? AND ?';
        $bindValues = [$userId, $from, $to];

        if ($categoryId) {
            $where .= ' AND i.category_id = ?';
            $bindValues[] = $categoryId;
        }

        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM income_entries i WHERE {$where}", $bindValues);

        $sql = "SELECT i.*, ic.name AS category_name, ic.color AS category_color
                FROM income_entries i
                LEFT JOIN income_categories ic ON i.category_id = ic.id
                WHERE {$where}
                ORDER BY i.income_date DESC, i.created_at DESC
                LIMIT ? OFFSET ?";
        $bindValues[] = $perPage;
        $bindValues[] = $offset;

        $entries = $this->db->fetchAllAssociative($sql, $bindValues);

        foreach ($entries as &$entry) {
            $entry['amount'] = (float) $entry['amount'];
        }

        return JsonResponse::paginated($entries, $total, $page, $perPage);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $errors = [];
        $description = trim($body['description'] ?? '');
        $amount = (float) ($body['amount'] ?? 0);
        $date = $body['income_date'] ?? date('Y-m-d');

        if (empty($description)) $errors['description'] = 'Beschreibung ist erforderlich';
        if ($amount <= 0) $errors['amount'] = 'Betrag muss größer als 0 sein';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors['income_date'] = 'Ungültiges Datum';

        if (!empty($errors)) {
            return JsonResponse::validationError($errors);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('income_entries', [
            'id' => $id,
            'user_id' => $userId,
            'category_id' => $body['category_id'] ?? null,
            'invoice_id' => $body['invoice_id'] ?? null,
            'amount' => $amount,
            'currency' => strtoupper(substr($body['currency'] ?? 'EUR', 0, 3)),
            'description' => $description,
            'income_date' => $date,
            'source' => $body['source'] ?? null,
            'notes' => $body['notes'] ?? null,
            'receipt_file_id' => $body['receipt_file_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $entry = $this->db->fetchAssociative(
            'SELECT i.*, ic.name AS category_name, ic.color AS category_color
             FROM income_entries i LEFT JOIN income_categories ic ON i.category_id = ic.id WHERE i.id = ?',
            [$id]
        );
        $entry['amount'] = (float) $entry['amount'];

        return JsonResponse::created($entry);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $entryId = $args['id'];
        $body = (array) $request->getParsedBody();

        $entry = $this->db->fetchAssociative('SELECT id FROM income_entries WHERE id = ? AND user_id = ?', [$entryId, $userId]);
        if (!$entry) {
            return JsonResponse::notFound('Einnahme nicht gefunden');
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($body['description'])) $updates['description'] = trim($body['description']);
        if (isset($body['amount'])) $updates['amount'] = (float) $body['amount'];
        if (isset($body['currency'])) $updates['currency'] = strtoupper(substr($body['currency'], 0, 3));
        if (isset($body['income_date'])) $updates['income_date'] = $body['income_date'];
        if (array_key_exists('category_id', $body)) $updates['category_id'] = $body['category_id'];
        if (array_key_exists('source', $body)) $updates['source'] = $body['source'];
        if (array_key_exists('notes', $body)) $updates['notes'] = $body['notes'];
        if (array_key_exists('receipt_file_id', $body)) $updates['receipt_file_id'] = $body['receipt_file_id'];

        $this->db->update('income_entries', $updates, ['id' => $entryId]);

        $updated = $this->db->fetchAssociative(
            'SELECT i.*, ic.name AS category_name, ic.color AS category_color
             FROM income_entries i LEFT JOIN income_categories ic ON i.category_id = ic.id WHERE i.id = ?',
            [$entryId]
        );
        $updated['amount'] = (float) $updated['amount'];

        return JsonResponse::success($updated);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $entryId = $args['id'];

        $entry = $this->db->fetchAssociative('SELECT id FROM income_entries WHERE id = ? AND user_id = ?', [$entryId, $userId]);
        if (!$entry) {
            return JsonResponse::notFound('Einnahme nicht gefunden');
        }

        $this->db->delete('income_entries', ['id' => $entryId]);

        return JsonResponse::noContent();
    }

    public function getSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $from = $params['from'] ?? date('Y-m-01');
        $to = $params['to'] ?? date('Y-m-t');

        $total = (float) $this->db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM income_entries WHERE user_id = ? AND income_date BETWEEN ? AND ?',
            [$userId, $from, $to]
        );

        $byCategory = $this->db->fetchAllAssociative(
            'SELECT ic.id, ic.name, ic.color, COALESCE(SUM(i.amount), 0) AS total
             FROM income_categories ic
             LEFT JOIN income_entries i ON i.category_id = ic.id AND i.user_id = ? AND i.income_date BETWEEN ? AND ?
             WHERE ic.user_id = ?
             GROUP BY ic.id, ic.name, ic.color
             ORDER BY total DESC',
            [$userId, $from, $to, $userId]
        );

        foreach ($byCategory as &$cat) {
            $cat['total'] = (float) $cat['total'];
        }

        return JsonResponse::success([
            'total' => $total,
            'period' => ['from' => $from, 'to' => $to],
            'by_category' => $byCategory,
        ]);
    }

    // ─── EÜR Report ───────────────────────────────────────────────────────────

    public function getEuer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $year = (int) ($params['year'] ?? date('Y'));
        $from = "{$year}-01-01";
        $to = "{$year}-12-31";

        // Total income: manual entries + paid invoices
        $incomeManual = (float) $this->db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM income_entries WHERE user_id = ? AND income_date BETWEEN ? AND ?',
            [$userId, $from, $to]
        );
        $incomeInvoices = (float) $this->db->fetchOne(
            "SELECT COALESCE(SUM(total), 0) FROM invoices WHERE user_id = ? AND status = 'paid' AND paid_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        );
        $totalIncome = $incomeManual + $incomeInvoices;

        // Total expenses
        $totalExpenses = (float) $this->db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?',
            [$userId, $from, $to]
        );

        $profit = $totalIncome - $totalExpenses;

        // Monthly breakdown
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $mFrom = sprintf('%d-%02d-01', $year, $m);
            $mTo = sprintf('%d-%02d-%02d', $year, $m, cal_days_in_month(CAL_GREGORIAN, $m, $year));

            $mIncomeManual = (float) $this->db->fetchOne(
                'SELECT COALESCE(SUM(amount), 0) FROM income_entries WHERE user_id = ? AND income_date BETWEEN ? AND ?',
                [$userId, $mFrom, $mTo]
            );
            $mIncomeInvoices = (float) $this->db->fetchOne(
                "SELECT COALESCE(SUM(total), 0) FROM invoices WHERE user_id = ? AND status = 'paid' AND paid_date BETWEEN ? AND ?",
                [$userId, $mFrom, $mTo]
            );
            $mExpenses = (float) $this->db->fetchOne(
                'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?',
                [$userId, $mFrom, $mTo]
            );

            $mIncome = $mIncomeManual + $mIncomeInvoices;
            $months[] = [
                'month' => $m,
                'month_name' => strftime('%B', mktime(0, 0, 0, $m, 1, $year)),
                'income' => round($mIncome, 2),
                'income_manual' => round($mIncomeManual, 2),
                'income_invoices' => round($mIncomeInvoices, 2),
                'expenses' => round($mExpenses, 2),
                'profit' => round($mIncome - $mExpenses, 2),
            ];
        }

        // Expense breakdown by category
        $expensesByCategory = $this->db->fetchAllAssociative(
            'SELECT ec.name, ec.color, COALESCE(SUM(e.amount), 0) AS total
             FROM expense_categories ec
             LEFT JOIN expenses e ON e.category_id = ec.id AND e.user_id = ? AND e.expense_date BETWEEN ? AND ?
             WHERE ec.user_id = ?
             GROUP BY ec.id, ec.name, ec.color
             HAVING total > 0
             ORDER BY total DESC',
            [$userId, $from, $to, $userId]
        );

        foreach ($expensesByCategory as &$cat) {
            $cat['total'] = (float) $cat['total'];
        }

        return JsonResponse::success([
            'year' => $year,
            'total_income' => round($totalIncome, 2),
            'income_manual' => round($incomeManual, 2),
            'income_invoices' => round($incomeInvoices, 2),
            'total_expenses' => round($totalExpenses, 2),
            'profit' => round($profit, 2),
            'months' => $months,
            'expenses_by_category' => $expensesByCategory,
        ]);
    }

    public function exportEuerCsv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();
        $year = (int) ($params['year'] ?? date('Y'));
        $from = "{$year}-01-01";
        $to = "{$year}-12-31";

        // Fetch all income entries
        $incomeEntries = $this->db->fetchAllAssociative(
            'SELECT i.income_date, i.description, i.amount, i.currency, i.source,
                    ic.name AS category
             FROM income_entries i
             LEFT JOIN income_categories ic ON i.category_id = ic.id
             WHERE i.user_id = ? AND i.income_date BETWEEN ? AND ?
             ORDER BY i.income_date ASC',
            [$userId, $from, $to]
        );

        // Fetch paid invoices
        $paidInvoices = $this->db->fetchAllAssociative(
            "SELECT paid_date AS income_date, invoice_number AS description, total AS amount, currency, 'Rechnung' AS source, 'Rechnungen' AS category
             FROM invoices WHERE user_id = ? AND status = 'paid' AND paid_date BETWEEN ? AND ?
             ORDER BY paid_date ASC",
            [$userId, $from, $to]
        );

        // Fetch all expenses
        $expenseEntries = $this->db->fetchAllAssociative(
            'SELECT e.expense_date, e.description, e.amount, e.currency,
                    ec.name AS category
             FROM expenses e
             LEFT JOIN expense_categories ec ON e.category_id = ec.id
             WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
             ORDER BY e.expense_date ASC',
            [$userId, $from, $to]
        );

        // Build CSV
        $lines = [];
        $lines[] = "Typ;Datum;Beschreibung;Kategorie;Quelle;Betrag;Währung";

        foreach (array_merge($incomeEntries, $paidInvoices) as $row) {
            $lines[] = sprintf(
                'Einnahme;%s;%s;%s;%s;%s;%s',
                $row['income_date'],
                str_replace(';', ',', $row['description']),
                str_replace(';', ',', $row['category'] ?? ''),
                str_replace(';', ',', $row['source'] ?? ''),
                number_format((float) $row['amount'], 2, ',', '.'),
                $row['currency']
            );
        }

        foreach ($expenseEntries as $row) {
            $lines[] = sprintf(
                'Ausgabe;%s;%s;%s;;%s;%s',
                $row['expense_date'],
                str_replace(';', ',', $row['description']),
                str_replace(';', ',', $row['category'] ?? ''),
                number_format((float) $row['amount'], 2, ',', '.'),
                $row['currency']
            );
        }

        $csv = implode("\n", $lines);

        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', "attachment; filename=\"euer-{$year}.csv\"");
    }

    // ─── Income Categories ────────────────────────────────────────────────────

    public function getCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $categories = $this->db->fetchAllAssociative(
            'SELECT * FROM income_categories WHERE user_id = ? ORDER BY name ASC',
            [$userId]
        );

        return JsonResponse::success($categories);
    }

    public function createCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $name = trim($body['name'] ?? '');
        if (empty($name)) {
            return JsonResponse::validationError(['name' => 'Name ist erforderlich']);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('income_categories', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $body['color'] ?? '') ? $body['color'] : '#10B981',
            'icon' => $body['icon'] ?? 'banknotes',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $category = $this->db->fetchAssociative('SELECT * FROM income_categories WHERE id = ?', [$id]);

        return JsonResponse::created($category);
    }

    public function deleteCategory(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $categoryId = $args['id'];

        $category = $this->db->fetchAssociative('SELECT id FROM income_categories WHERE id = ? AND user_id = ?', [$categoryId, $userId]);
        if (!$category) {
            return JsonResponse::notFound('Kategorie nicht gefunden');
        }

        $this->db->update('income_entries', ['category_id' => null], ['category_id' => $categoryId]);
        $this->db->delete('income_categories', ['id' => $categoryId]);

        return JsonResponse::noContent();
    }
}
