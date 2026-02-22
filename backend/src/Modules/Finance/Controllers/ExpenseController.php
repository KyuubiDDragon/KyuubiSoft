<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

class ExpenseController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    // ─── Expenses ────────────────────────────────────────────────────────────

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

        $where = 'e.user_id = ? AND e.expense_date BETWEEN ? AND ?';
        $bindValues = [$userId, $from, $to];

        if ($categoryId) {
            $where .= ' AND e.category_id = ?';
            $bindValues[] = $categoryId;
        }

        $total = (int) $this->db->fetchOne("SELECT COUNT(*) FROM expenses e WHERE {$where}", $bindValues);

        $sql = "SELECT e.*, ec.name AS category_name, ec.color AS category_color
                FROM expenses e
                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                WHERE {$where}
                ORDER BY e.expense_date DESC, e.created_at DESC
                LIMIT ? OFFSET ?";
        $bindValues[] = $perPage;
        $bindValues[] = $offset;

        $expenses = $this->db->fetchAllAssociative($sql, $bindValues);

        foreach ($expenses as &$expense) {
            $expense['amount'] = (float) $expense['amount'];
            $expense['is_recurring'] = (bool) $expense['is_recurring'];
        }

        return JsonResponse::paginated($expenses, $total, $page, $perPage);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $body = (array) $request->getParsedBody();

        $errors = [];
        $description = trim($body['description'] ?? '');
        $amount = (float) ($body['amount'] ?? 0);
        $date = $body['expense_date'] ?? date('Y-m-d');

        if (empty($description)) $errors['description'] = 'Beschreibung ist erforderlich';
        if ($amount <= 0) $errors['amount'] = 'Betrag muss größer als 0 sein';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors['expense_date'] = 'Ungültiges Datum';

        if (!empty($errors)) {
            return JsonResponse::validationError($errors);
        }

        $id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');

        $this->db->insert('expenses', [
            'id' => $id,
            'user_id' => $userId,
            'category_id' => $body['category_id'] ?? null,
            'amount' => $amount,
            'currency' => strtoupper(substr($body['currency'] ?? 'EUR', 0, 3)),
            'description' => $description,
            'expense_date' => $date,
            'is_recurring' => isset($body['is_recurring']) && $body['is_recurring'] ? 1 : 0,
            'recurring_interval' => in_array($body['recurring_interval'] ?? '', ['weekly', 'monthly', 'yearly']) ? $body['recurring_interval'] : null,
            'notes' => $body['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $expense = $this->db->fetchAssociative(
            'SELECT e.*, ec.name AS category_name, ec.color AS category_color FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.id = ?',
            [$id]
        );
        $expense['amount'] = (float) $expense['amount'];
        $expense['is_recurring'] = (bool) $expense['is_recurring'];

        return JsonResponse::created($expense);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $expenseId = $args['id'];
        $body = (array) $request->getParsedBody();

        $expense = $this->db->fetchAssociative('SELECT id FROM expenses WHERE id = ? AND user_id = ?', [$expenseId, $userId]);
        if (!$expense) {
            return JsonResponse::notFound('Ausgabe nicht gefunden');
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($body['description'])) $updates['description'] = trim($body['description']);
        if (isset($body['amount'])) $updates['amount'] = (float) $body['amount'];
        if (isset($body['currency'])) $updates['currency'] = strtoupper(substr($body['currency'], 0, 3));
        if (isset($body['expense_date'])) $updates['expense_date'] = $body['expense_date'];
        if (array_key_exists('category_id', $body)) $updates['category_id'] = $body['category_id'];
        if (array_key_exists('notes', $body)) $updates['notes'] = $body['notes'];
        if (isset($body['is_recurring'])) $updates['is_recurring'] = $body['is_recurring'] ? 1 : 0;

        $this->db->update('expenses', $updates, ['id' => $expenseId]);

        $updated = $this->db->fetchAssociative(
            'SELECT e.*, ec.name AS category_name, ec.color AS category_color FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.id = ?',
            [$expenseId]
        );
        $updated['amount'] = (float) $updated['amount'];
        $updated['is_recurring'] = (bool) $updated['is_recurring'];

        return JsonResponse::success($updated);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $expenseId = $args['id'];

        $expense = $this->db->fetchAssociative('SELECT id FROM expenses WHERE id = ? AND user_id = ?', [$expenseId, $userId]);
        if (!$expense) {
            return JsonResponse::notFound('Ausgabe nicht gefunden');
        }

        $this->db->delete('expenses', ['id' => $expenseId]);

        return JsonResponse::noContent();
    }

    public function getSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $from = $params['from'] ?? date('Y-m-01');
        $to = $params['to'] ?? date('Y-m-t');

        // Total this period
        $total = (float) $this->db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?',
            [$userId, $from, $to]
        );

        // By category
        $byCategory = $this->db->fetchAllAssociative(
            'SELECT ec.id, ec.name, ec.color, COALESCE(SUM(e.amount), 0) AS total
             FROM expense_categories ec
             LEFT JOIN expenses e ON e.category_id = ec.id AND e.user_id = ? AND e.expense_date BETWEEN ? AND ?
             WHERE ec.user_id = ?
             GROUP BY ec.id, ec.name, ec.color
             ORDER BY total DESC',
            [$userId, $from, $to, $userId]
        );

        foreach ($byCategory as &$cat) {
            $cat['total'] = (float) $cat['total'];
        }

        // Uncategorized
        $uncategorized = (float) $this->db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = ? AND category_id IS NULL AND expense_date BETWEEN ? AND ?',
            [$userId, $from, $to]
        );

        // Daily spending (last 30 days)
        $dailyFrom = date('Y-m-d', strtotime('-30 days'));
        $dailySpending = $this->db->fetchAllAssociative(
            'SELECT expense_date, SUM(amount) AS total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ? GROUP BY expense_date ORDER BY expense_date ASC',
            [$userId, $dailyFrom, $to]
        );

        foreach ($dailySpending as &$day) {
            $day['total'] = (float) $day['total'];
        }

        return JsonResponse::success([
            'total' => $total,
            'period' => ['from' => $from, 'to' => $to],
            'by_category' => $byCategory,
            'uncategorized' => $uncategorized,
            'daily_spending' => $dailySpending,
        ]);
    }

    // ─── Categories ──────────────────────────────────────────────────────────

    public function getCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $categories = $this->db->fetchAllAssociative(
            'SELECT * FROM expense_categories WHERE user_id = ? ORDER BY name ASC',
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

        $this->db->insert('expense_categories', [
            'id' => $id,
            'user_id' => $userId,
            'name' => $name,
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $body['color'] ?? '') ? $body['color'] : '#3B82F6',
            'icon' => $body['icon'] ?? 'banknotes',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $category = $this->db->fetchAssociative('SELECT * FROM expense_categories WHERE id = ?', [$id]);

        return JsonResponse::created($category);
    }

    public function deleteCategory(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $categoryId = $args['id'];

        $category = $this->db->fetchAssociative('SELECT id FROM expense_categories WHERE id = ? AND user_id = ?', [$categoryId, $userId]);
        if (!$category) {
            return JsonResponse::notFound('Kategorie nicht gefunden');
        }

        // Set category_id to NULL on related expenses
        $this->db->update('expenses', ['category_id' => null], ['category_id' => $categoryId]);
        $this->db->delete('expense_categories', ['id' => $categoryId]);

        return JsonResponse::noContent();
    }
}
