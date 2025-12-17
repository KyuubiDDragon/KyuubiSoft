<?php

declare(strict_types=1);

namespace App\Modules\Checklists\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Core\Services\CacheService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

class SharedChecklistController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CacheService $cache
    ) {}

    // ==================== Authenticated Endpoints ====================

    /**
     * List all checklists for the current user
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklists = $this->db->fetchAllAssociative(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM shared_checklist_items WHERE checklist_id = c.id) as item_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries e
                     JOIN shared_checklist_items i ON e.item_id = i.id
                     WHERE i.checklist_id = c.id AND e.status IN ("passed", "failed")) as completed_entries
             FROM shared_checklists c
             WHERE c.user_id = ?
             ORDER BY c.updated_at DESC',
            [$userId]
        );

        return JsonResponse::success(['items' => $checklists]);
    }

    /**
     * Create a new checklist
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        if (empty($data['title'])) {
            throw new ValidationException('Titel ist erforderlich');
        }

        $checklistId = Uuid::uuid4()->toString();
        $shareToken = bin2hex(random_bytes(32));

        $this->db->insert('shared_checklists', [
            'id' => $checklistId,
            'user_id' => $userId,
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'share_token' => $shareToken,
            'is_active' => 1,
            'allow_anonymous' => isset($data['allow_anonymous']) ? (int) $data['allow_anonymous'] : 1,
            'require_name' => isset($data['require_name']) ? (int) $data['require_name'] : 1,
            'allow_add_items' => isset($data['allow_add_items']) ? (int) $data['allow_add_items'] : 0,
            'allow_comments' => isset($data['allow_comments']) ? (int) $data['allow_comments'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE id = ?',
            [$checklistId]
        );

        return JsonResponse::created($checklist, 'Checkliste erstellt');
    }

    /**
     * Get a single checklist with all details
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId);

        // Get categories
        $categories = $this->db->fetchAllAssociative(
            'SELECT * FROM shared_checklist_categories WHERE checklist_id = ? ORDER BY sort_order',
            [$id]
        );

        // Get items with entry counts
        $items = $this->db->fetchAllAssociative(
            'SELECT i.*,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id) as entry_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id AND status = "passed") as passed_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id AND status = "failed") as failed_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id AND status = "uncertain") as uncertain_count
             FROM shared_checklist_items i
             WHERE i.checklist_id = ?
             ORDER BY i.sort_order',
            [$id]
        );

        // Get entries for each item
        foreach ($items as &$item) {
            $item['entries'] = $this->db->fetchAllAssociative(
                'SELECT * FROM shared_checklist_entries WHERE item_id = ? ORDER BY created_at DESC',
                [$item['id']]
            );
        }

        // Cast boolean fields for proper frontend handling
        $checklist['is_active'] = (bool) $checklist['is_active'];
        $checklist['allow_anonymous'] = (bool) $checklist['allow_anonymous'];
        $checklist['require_name'] = (bool) $checklist['require_name'];
        $checklist['allow_add_items'] = (bool) $checklist['allow_add_items'];
        $checklist['allow_comments'] = (bool) $checklist['allow_comments'];
        $checklist['has_password'] = !empty($checklist['password_hash'] ?? null);
        unset($checklist['password_hash']); // Don't send hash to frontend
        $checklist['categories'] = $categories;
        $checklist['items'] = $items;

        return JsonResponse::success($checklist);
    }

    /**
     * Update a checklist
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->getChecklistForUser($id, $userId, true);

        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['title'])) {
            $updates['title'] = trim($data['title']);
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = (int) $data['is_active'];
        }
        if (isset($data['allow_anonymous'])) {
            $updates['allow_anonymous'] = (int) $data['allow_anonymous'];
        }
        if (isset($data['require_name'])) {
            $updates['require_name'] = (int) $data['require_name'];
        }
        if (isset($data['allow_add_items'])) {
            $updates['allow_add_items'] = (int) $data['allow_add_items'];
        }
        if (isset($data['allow_comments'])) {
            $updates['allow_comments'] = (int) $data['allow_comments'];
        }
        // Handle password - empty string or null clears it, non-empty sets it
        if (array_key_exists('password', $data)) {
            if (empty($data['password'])) {
                $updates['password_hash'] = null;
            } else {
                $updates['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
        }

        $this->db->update('shared_checklists', $updates, ['id' => $id]);

        $updatedChecklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE id = ?',
            [$id]
        );

        // Cast boolean fields for proper frontend handling
        $updatedChecklist['is_active'] = (bool) $updatedChecklist['is_active'];
        $updatedChecklist['allow_anonymous'] = (bool) $updatedChecklist['allow_anonymous'];
        $updatedChecklist['require_name'] = (bool) $updatedChecklist['require_name'];
        $updatedChecklist['allow_add_items'] = (bool) $updatedChecklist['allow_add_items'];
        $updatedChecklist['allow_comments'] = (bool) $updatedChecklist['allow_comments'];
        $updatedChecklist['has_password'] = !empty($updatedChecklist['password_hash']);
        unset($updatedChecklist['password_hash']);

        return JsonResponse::success($updatedChecklist, 'Checkliste aktualisiert');
    }

    /**
     * Delete a checklist
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId, true);

        // Cleanup favorites and tags
        $this->db->delete('favorites', ['item_type' => 'checklist', 'item_id' => $id]);
        $this->db->delete('taggables', ['taggable_type' => 'checklist', 'taggable_id' => $id]);

        $this->db->delete('shared_checklists', ['id' => $id]);

        return JsonResponse::success(null, 'Checkliste gelöscht');
    }

    /**
     * Duplicate a checklist (with all categories and items, but no entries)
     */
    public function duplicate(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId);

        // Create new checklist
        $newChecklistId = Uuid::uuid4()->toString();
        $shareToken = bin2hex(random_bytes(32));

        $this->db->insert('shared_checklists', [
            'id' => $newChecklistId,
            'user_id' => $userId,
            'title' => $checklist['title'] . ' (Kopie)',
            'description' => $checklist['description'],
            'share_token' => $shareToken,
            'is_active' => 1,
            'allow_anonymous' => $checklist['allow_anonymous'],
            'require_name' => $checklist['require_name'],
            'allow_add_items' => $checklist['allow_add_items'],
            'allow_comments' => $checklist['allow_comments'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Copy categories with ID mapping
        $categoryMap = [];
        $categories = $this->db->fetchAllAssociative(
            'SELECT * FROM shared_checklist_categories WHERE checklist_id = ? ORDER BY sort_order',
            [$id]
        );

        foreach ($categories as $category) {
            $newCategoryId = Uuid::uuid4()->toString();
            $categoryMap[$category['id']] = $newCategoryId;

            $this->db->insert('shared_checklist_categories', [
                'id' => $newCategoryId,
                'checklist_id' => $newChecklistId,
                'name' => $category['name'],
                'description' => $category['description'],
                'sort_order' => $category['sort_order'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Copy items (with updated category IDs)
        $items = $this->db->fetchAllAssociative(
            'SELECT * FROM shared_checklist_items WHERE checklist_id = ? ORDER BY sort_order',
            [$id]
        );

        foreach ($items as $item) {
            $newItemId = Uuid::uuid4()->toString();
            $newCategoryId = $item['category_id'] ? ($categoryMap[$item['category_id']] ?? null) : null;

            $this->db->insert('shared_checklist_items', [
                'id' => $newItemId,
                'checklist_id' => $newChecklistId,
                'category_id' => $newCategoryId,
                'title' => $item['title'],
                'description' => $item['description'],
                'required_testers' => $item['required_testers'],
                'sort_order' => $item['sort_order'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $newChecklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE id = ?',
            [$newChecklistId]
        );

        return JsonResponse::created($newChecklist, 'Checkliste dupliziert');
    }

    /**
     * Reset all entries for a checklist
     */
    public function resetEntries(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId, true);

        // Delete all entries for items in this checklist
        $this->db->executeStatement(
            'DELETE e FROM shared_checklist_entries e
             INNER JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE i.checklist_id = ?',
            [$id]
        );

        // Delete related activity log
        $this->db->executeStatement(
            'DELETE FROM shared_checklist_activity WHERE checklist_id = ?',
            [$id]
        );

        // Log this action
        $this->logActivity($id, null, null, 'Owner', 'entries_reset', [
            'message' => 'Alle Einträge zurückgesetzt',
        ]);

        return JsonResponse::success(null, 'Alle Einträge wurden zurückgesetzt');
    }

    /**
     * Add a category to a checklist
     */
    public function addCategory(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->getChecklistForUser($id, $userId, true);

        if (empty($data['name'])) {
            throw new ValidationException('Name ist erforderlich');
        }

        // Get max sort order
        $maxSort = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), 0) FROM shared_checklist_categories WHERE checklist_id = ?',
            [$id]
        );

        $categoryId = Uuid::uuid4()->toString();

        $this->db->insert('shared_checklist_categories', [
            'id' => $categoryId,
            'checklist_id' => $id,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => $maxSort + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $category = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_categories WHERE id = ?',
            [$categoryId]
        );

        return JsonResponse::created($category, 'Kategorie erstellt');
    }

    /**
     * Update a category
     */
    public function updateCategory(ServerRequestInterface $request, ResponseInterface $response, string $id, string $categoryId): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->getChecklistForUser($id, $userId, true);

        $category = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_categories WHERE id = ? AND checklist_id = ?',
            [$categoryId, $id]
        );

        if (!$category) {
            throw new NotFoundException('Kategorie nicht gefunden');
        }

        $updates = [];
        if (isset($data['name'])) {
            $updates['name'] = trim($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if (isset($data['sort_order'])) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }

        if (!empty($updates)) {
            $this->db->update('shared_checklist_categories', $updates, ['id' => $categoryId]);
        }

        $updatedCategory = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_categories WHERE id = ?',
            [$categoryId]
        );

        return JsonResponse::success($updatedCategory, 'Kategorie aktualisiert');
    }

    /**
     * Delete a category
     */
    public function deleteCategory(ServerRequestInterface $request, ResponseInterface $response, string $id, string $categoryId): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId, true);

        // Items will have their category_id set to NULL due to ON DELETE SET NULL
        $this->db->delete('shared_checklist_categories', ['id' => $categoryId, 'checklist_id' => $id]);

        return JsonResponse::success(null, 'Kategorie gelöscht');
    }

    /**
     * Add an item to a checklist
     */
    public function addItem(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->getChecklistForUser($id, $userId, true);

        if (empty($data['title'])) {
            throw new ValidationException('Titel ist erforderlich');
        }

        // Get max sort order
        $maxSort = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), 0) FROM shared_checklist_items WHERE checklist_id = ?',
            [$id]
        );

        $itemId = Uuid::uuid4()->toString();

        $this->db->insert('shared_checklist_items', [
            'id' => $itemId,
            'checklist_id' => $id,
            'category_id' => $data['category_id'] ?? null,
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'required_testers' => isset($data['required_testers']) ? ((int) $data['required_testers'] === -1 ? -1 : max(1, (int) $data['required_testers'])) : 1,
            'sort_order' => $maxSort + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Log activity
        $this->logActivity($id, $itemId, null, 'Owner', 'item_added', [
            'title' => $data['title'],
        ]);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_items WHERE id = ?',
            [$itemId]
        );

        return JsonResponse::created($item, 'Testpunkt erstellt');
    }

    /**
     * Update an item
     */
    public function updateItem(ServerRequestInterface $request, ResponseInterface $response, string $id, string $itemId): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->getChecklistForUser($id, $userId, true);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_items WHERE id = ? AND checklist_id = ?',
            [$itemId, $id]
        );

        if (!$item) {
            throw new NotFoundException('Testpunkt nicht gefunden');
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['title'])) {
            $updates['title'] = trim($data['title']);
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }
        if (array_key_exists('category_id', $data)) {
            $updates['category_id'] = $data['category_id'];
        }
        if (isset($data['required_testers'])) {
            $updates['required_testers'] = (int) $data['required_testers'] === -1 ? -1 : max(1, (int) $data['required_testers']);
        }
        if (isset($data['sort_order'])) {
            $updates['sort_order'] = (int) $data['sort_order'];
        }

        $this->db->update('shared_checklist_items', $updates, ['id' => $itemId]);

        $updatedItem = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_items WHERE id = ?',
            [$itemId]
        );

        return JsonResponse::success($updatedItem, 'Testpunkt aktualisiert');
    }

    /**
     * Delete an item
     */
    public function deleteItem(ServerRequestInterface $request, ResponseInterface $response, string $id, string $itemId): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId, true);

        $this->db->delete('shared_checklist_items', ['id' => $itemId, 'checklist_id' => $id]);

        return JsonResponse::success(null, 'Testpunkt gelöscht');
    }

    /**
     * Delete a test entry (admin - can delete any entry)
     */
    public function deleteEntryAdmin(ServerRequestInterface $request, ResponseInterface $response, string $id, string $entryId): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId, true);

        // Get entry and verify it belongs to this checklist
        $entry = $this->db->fetchAssociative(
            'SELECT e.*, i.checklist_id
             FROM shared_checklist_entries e
             JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE e.id = ? AND i.checklist_id = ?',
            [$entryId, $id]
        );

        if (!$entry) {
            throw new NotFoundException('Eintrag nicht gefunden');
        }

        // Delete image if exists
        if ($entry['image_path']) {
            $uploadDir = __DIR__ . '/../../../../storage/checklist-images/';
            $imagePath = $uploadDir . $entry['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $this->db->delete('shared_checklist_entries', ['id' => $entryId]);

        // Log activity
        $this->logActivity($id, $entry['item_id'], null, 'Admin', 'entry_deleted', []);

        return JsonResponse::success(null, 'Eintrag gelöscht');
    }

    /**
     * Upload an image for a checklist entry (admin)
     */
    public function uploadEntryImageAdmin(ServerRequestInterface $request, ResponseInterface $response, string $id, string $entryId): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId, true);

        // Get entry and verify it belongs to this checklist
        $entry = $this->db->fetchAssociative(
            'SELECT e.*, i.checklist_id
             FROM shared_checklist_entries e
             JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE e.id = ? AND i.checklist_id = ?',
            [$entryId, $id]
        );

        if (!$entry) {
            throw new NotFoundException('Eintrag nicht gefunden');
        }

        $uploadedFiles = $request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['image'] ?? null;

        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Kein gültiges Bild hochgeladen');
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = $uploadedFile->getClientMediaType();
        if (!in_array($mimeType, $allowedTypes)) {
            throw new ValidationException('Nur JPEG, PNG, GIF und WebP Bilder sind erlaubt');
        }

        // Check file size (max 5MB)
        if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
            throw new ValidationException('Bild darf maximal 5MB groß sein');
        }

        // Generate unique filename
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = 'checklist_' . $id . '_' . $entryId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/../../../../storage/checklist-images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Delete old image if exists
        if ($entry['image_path']) {
            $oldImagePath = $uploadDir . $entry['image_path'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }

        // Move uploaded file
        $uploadedFile->moveTo($uploadDir . $filename);

        // Update database
        $this->db->update('shared_checklist_entries', [
            'image_path' => $filename,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $entryId]);

        return JsonResponse::success([
            'image_path' => $filename,
        ], 'Bild hochgeladen');
    }

    /**
     * Delete an entry image (admin)
     */
    public function deleteEntryImageAdmin(ServerRequestInterface $request, ResponseInterface $response, string $id, string $entryId): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId, true);

        // Get entry and verify it belongs to this checklist
        $entry = $this->db->fetchAssociative(
            'SELECT e.*, i.checklist_id
             FROM shared_checklist_entries e
             JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE e.id = ? AND i.checklist_id = ?',
            [$entryId, $id]
        );

        if (!$entry) {
            throw new NotFoundException('Eintrag nicht gefunden');
        }

        if ($entry['image_path']) {
            $uploadDir = __DIR__ . '/../../../../storage/checklist-images/';
            $imagePath = $uploadDir . $entry['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            $this->db->update('shared_checklist_entries', [
                'image_path' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $entryId]);
        }

        return JsonResponse::success(null, 'Bild gelöscht');
    }

    /**
     * Get activity log for a checklist
     */
    public function getActivity(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $checklist = $this->getChecklistForUser($id, $userId);

        $activities = $this->db->fetchAllAssociative(
            'SELECT a.*, i.title as item_title
             FROM shared_checklist_activity a
             LEFT JOIN shared_checklist_items i ON a.item_id = i.id
             WHERE a.checklist_id = ?
             ORDER BY a.created_at DESC
             LIMIT 100',
            [$id]
        );

        return JsonResponse::success(['items' => $activities]);
    }

    // ==================== Public Endpoints ====================

    /**
     * Get public checklist info
     */
    public function getPublic(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        $checklist = $this->db->fetchAssociative(
            'SELECT c.*, u.username as owner_name
             FROM shared_checklists c
             JOIN users u ON c.user_id = u.id
             WHERE c.share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        if (!$checklist['is_active']) {
            throw new ForbiddenException('Diese Checkliste ist deaktiviert');
        }

        // Check password protection
        if (!empty($checklist['password_hash'])) {
            $params = $request->getQueryParams();
            $providedPassword = $params['password'] ?? null;

            if (!$providedPassword || !password_verify($providedPassword, $checklist['password_hash'])) {
                // Return limited info for password-protected lists
                return JsonResponse::success([
                    'id' => $checklist['id'],
                    'title' => $checklist['title'],
                    'owner_name' => $checklist['owner_name'],
                    'requires_password' => true,
                ]);
            }
        }

        // Get categories
        $categories = $this->db->fetchAllAssociative(
            'SELECT * FROM shared_checklist_categories WHERE checklist_id = ? ORDER BY sort_order',
            [$checklist['id']]
        );

        // Get items with entries
        $items = $this->db->fetchAllAssociative(
            'SELECT i.*,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id) as entry_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id AND status = "passed") as passed_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id AND status = "failed") as failed_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id AND status = "in_progress") as in_progress_count,
                    (SELECT COUNT(*) FROM shared_checklist_entries WHERE item_id = i.id AND status = "uncertain") as uncertain_count
             FROM shared_checklist_items i
             WHERE i.checklist_id = ?
             ORDER BY i.sort_order',
            [$checklist['id']]
        );

        // Get entries for each item
        foreach ($items as &$item) {
            $item['entries'] = $this->db->fetchAllAssociative(
                'SELECT id, tester_name, status, notes, tested_at, created_at, updated_at
                 FROM shared_checklist_entries
                 WHERE item_id = ?
                 ORDER BY created_at DESC',
                [$item['id']]
            );
        }

        // Calculate progress (-1 means unlimited/unspecified testers)
        $totalRequired = 0;
        $totalCompleted = 0;
        foreach ($items as $item) {
            if ($item['required_testers'] == -1) {
                // Unlimited: count as 1 required, completed if at least 1 passed
                $totalRequired += 1;
                $totalCompleted += $item['passed_count'] > 0 ? 1 : 0;
            } else {
                $totalRequired += $item['required_testers'];
                $totalCompleted += min($item['passed_count'], $item['required_testers']);
            }
        }

        return JsonResponse::success([
            'id' => $checklist['id'],
            'title' => $checklist['title'],
            'description' => $checklist['description'],
            'owner_name' => $checklist['owner_name'],
            'allow_anonymous' => (bool) $checklist['allow_anonymous'],
            'require_name' => (bool) $checklist['require_name'],
            'allow_add_items' => (bool) $checklist['allow_add_items'],
            'allow_comments' => (bool) $checklist['allow_comments'],
            'categories' => $categories,
            'items' => $items,
            'progress' => [
                'total_items' => count($items),
                'total_required' => $totalRequired,
                'total_completed' => $totalCompleted,
                'percentage' => $totalRequired > 0 ? round(($totalCompleted / $totalRequired) * 100) : 0,
            ],
        ]);
    }

    /**
     * Add a test entry (public)
     */
    public function addEntry(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        if (!$checklist['is_active']) {
            throw new ForbiddenException('Diese Checkliste ist deaktiviert');
        }

        if (empty($data['item_id'])) {
            throw new ValidationException('Testpunkt ist erforderlich');
        }

        if ($checklist['require_name'] && empty($data['tester_name'])) {
            throw new ValidationException('Name ist erforderlich');
        }

        // Check item belongs to this checklist
        $item = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_items WHERE id = ? AND checklist_id = ?',
            [$data['item_id'], $checklist['id']]
        );

        if (!$item) {
            throw new NotFoundException('Testpunkt nicht gefunden');
        }

        $entryId = Uuid::uuid4()->toString();
        $testerName = trim($data['tester_name'] ?? 'Anonym');
        $status = $data['status'] ?? 'in_progress';

        // Validate status
        $validStatuses = ['pending', 'in_progress', 'passed', 'failed', 'blocked', 'uncertain'];
        if (!in_array($status, $validStatuses)) {
            $status = 'in_progress';
        }

        $this->db->insert('shared_checklist_entries', [
            'id' => $entryId,
            'item_id' => $data['item_id'],
            'tester_name' => $testerName,
            'tester_email' => $data['tester_email'] ?? null,
            'status' => $status,
            'notes' => $data['notes'] ?? null,
            'tested_at' => in_array($status, ['passed', 'failed', 'uncertain']) ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Log activity
        $this->logActivity($checklist['id'], $data['item_id'], $entryId, $testerName, 'entry_added', [
            'status' => $status,
            'item_title' => $item['title'],
        ]);

        $entry = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_entries WHERE id = ?',
            [$entryId]
        );

        // Publish real-time update
        $this->publishUpdate($token, 'entry_added', [
            'entry' => $entry,
            'item_id' => $data['item_id'],
            'item_title' => $item['title'],
        ]);

        return JsonResponse::created($entry, 'Testeintrag erstellt');
    }

    /**
     * Update a test entry (public)
     */
    public function updateEntry(ServerRequestInterface $request, ResponseInterface $response, string $token, string $entryId): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        if (!$checklist['is_active']) {
            throw new ForbiddenException('Diese Checkliste ist deaktiviert');
        }

        // Get entry and verify it belongs to this checklist
        $entry = $this->db->fetchAssociative(
            'SELECT e.*, i.checklist_id, i.title as item_title
             FROM shared_checklist_entries e
             JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE e.id = ? AND i.checklist_id = ?',
            [$entryId, $checklist['id']]
        );

        if (!$entry) {
            throw new NotFoundException('Eintrag nicht gefunden');
        }

        $updates = ['updated_at' => date('Y-m-d H:i:s')];
        $oldStatus = $entry['status'];

        if (isset($data['status'])) {
            $validStatuses = ['pending', 'in_progress', 'passed', 'failed', 'blocked', 'uncertain'];
            if (in_array($data['status'], $validStatuses)) {
                $updates['status'] = $data['status'];

                // Set tested_at when completing
                if (in_array($data['status'], ['passed', 'failed', 'uncertain']) && !$entry['tested_at']) {
                    $updates['tested_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        if (array_key_exists('notes', $data)) {
            $updates['notes'] = $data['notes'];
        }

        $this->db->update('shared_checklist_entries', $updates, ['id' => $entryId]);

        // Log activity if status changed
        if (isset($updates['status']) && $updates['status'] !== $oldStatus) {
            $this->logActivity($checklist['id'], $entry['item_id'], $entryId, $entry['tester_name'], 'status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $updates['status'],
                'item_title' => $entry['item_title'],
            ]);
        }

        $updatedEntry = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_entries WHERE id = ?',
            [$entryId]
        );

        // Publish real-time update
        $this->publishUpdate($token, 'entry_updated', [
            'entry' => $updatedEntry,
            'item_id' => $entry['item_id'],
            'old_status' => $oldStatus,
        ]);

        return JsonResponse::success($updatedEntry, 'Eintrag aktualisiert');
    }

    /**
     * Delete a test entry (public - only own entries)
     */
    public function deleteEntry(ServerRequestInterface $request, ResponseInterface $response, string $token, string $entryId): ResponseInterface
    {
        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        // Get entry and verify it belongs to this checklist
        $entry = $this->db->fetchAssociative(
            'SELECT e.*, i.checklist_id
             FROM shared_checklist_entries e
             JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE e.id = ? AND i.checklist_id = ?',
            [$entryId, $checklist['id']]
        );

        if (!$entry) {
            throw new NotFoundException('Eintrag nicht gefunden');
        }

        $this->db->delete('shared_checklist_entries', ['id' => $entryId]);

        // Log activity
        $this->logActivity($checklist['id'], $entry['item_id'], null, $entry['tester_name'], 'entry_deleted', []);

        // Publish real-time update
        $this->publishUpdate($token, 'entry_deleted', [
            'entry_id' => $entryId,
            'item_id' => $entry['item_id'],
        ]);

        return JsonResponse::success(null, 'Eintrag gelöscht');
    }

    /**
     * Upload an image for a checklist entry
     */
    public function uploadEntryImage(ServerRequestInterface $request, ResponseInterface $response, string $token, string $entryId): ResponseInterface
    {
        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        if (!$checklist['is_active']) {
            throw new ForbiddenException('Diese Checkliste ist deaktiviert');
        }

        // Get entry and verify it belongs to this checklist
        $entry = $this->db->fetchAssociative(
            'SELECT e.*, i.checklist_id
             FROM shared_checklist_entries e
             JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE e.id = ? AND i.checklist_id = ?',
            [$entryId, $checklist['id']]
        );

        if (!$entry) {
            throw new NotFoundException('Eintrag nicht gefunden');
        }

        $uploadedFiles = $request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['image'] ?? null;

        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Kein gültiges Bild hochgeladen');
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = $uploadedFile->getClientMediaType();
        if (!in_array($mimeType, $allowedTypes)) {
            throw new ValidationException('Nur JPEG, PNG, GIF und WebP Bilder sind erlaubt');
        }

        // Check file size (max 5MB)
        if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
            throw new ValidationException('Bild darf maximal 5MB groß sein');
        }

        // Generate unique filename
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = 'checklist_' . $checklist['id'] . '_' . $entryId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/../../../../storage/checklist-images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Delete old image if exists
        if ($entry['image_path']) {
            $oldImagePath = $uploadDir . $entry['image_path'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }

        // Move uploaded file
        $uploadedFile->moveTo($uploadDir . $filename);

        // Update database
        $this->db->update('shared_checklist_entries', [
            'image_path' => $filename,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $entryId]);

        return JsonResponse::success([
            'image_path' => $filename,
        ], 'Bild hochgeladen');
    }

    /**
     * Delete an entry image
     */
    public function deleteEntryImage(ServerRequestInterface $request, ResponseInterface $response, string $token, string $entryId): ResponseInterface
    {
        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        // Get entry and verify it belongs to this checklist
        $entry = $this->db->fetchAssociative(
            'SELECT e.*, i.checklist_id
             FROM shared_checklist_entries e
             JOIN shared_checklist_items i ON e.item_id = i.id
             WHERE e.id = ? AND i.checklist_id = ?',
            [$entryId, $checklist['id']]
        );

        if (!$entry) {
            throw new NotFoundException('Eintrag nicht gefunden');
        }

        if ($entry['image_path']) {
            $uploadDir = __DIR__ . '/../../../../storage/checklist-images/';
            $imagePath = $uploadDir . $entry['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }

            $this->db->update('shared_checklist_entries', [
                'image_path' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $entryId]);
        }

        return JsonResponse::success(null, 'Bild gelöscht');
    }

    /**
     * Serve a checklist entry image
     */
    public function serveImage(ServerRequestInterface $request, ResponseInterface $response, string $filename): ResponseInterface
    {
        $uploadDir = __DIR__ . '/../../../../storage/checklist-images/';
        $filePath = $uploadDir . basename($filename); // Use basename to prevent directory traversal

        if (!file_exists($filePath)) {
            throw new NotFoundException('Bild nicht gefunden');
        }

        // Get MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        $response = $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Length', (string) filesize($filePath))
            ->withHeader('Cache-Control', 'public, max-age=31536000');

        $response->getBody()->write(file_get_contents($filePath));

        return $response;
    }

    /**
     * Add item via public link (if allowed)
     */
    public function addItemPublic(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        if (!$checklist['is_active']) {
            throw new ForbiddenException('Diese Checkliste ist deaktiviert');
        }

        if (!$checklist['allow_add_items']) {
            throw new ForbiddenException('Das Hinzufügen von Einträgen ist nicht erlaubt');
        }

        if (empty($data['title'])) {
            throw new ValidationException('Titel ist erforderlich');
        }

        $addedBy = trim($data['added_by'] ?? 'Anonym');

        // Get max sort order
        $maxSort = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), 0) FROM shared_checklist_items WHERE checklist_id = ?',
            [$checklist['id']]
        );

        $itemId = Uuid::uuid4()->toString();

        $this->db->insert('shared_checklist_items', [
            'id' => $itemId,
            'checklist_id' => $checklist['id'],
            'category_id' => $data['category_id'] ?? null,
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'required_testers' => isset($data['required_testers']) ? ((int) $data['required_testers'] === -1 ? -1 : max(1, (int) $data['required_testers'])) : 1,
            'sort_order' => $maxSort + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Log activity
        $this->logActivity($checklist['id'], $itemId, null, $addedBy, 'item_added', [
            'title' => $data['title'],
        ]);

        $item = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklist_items WHERE id = ?',
            [$itemId]
        );

        // Publish real-time update
        $this->publishUpdate($token, 'item_added', [
            'item' => $item,
        ]);

        return JsonResponse::created($item, 'Testpunkt erstellt');
    }

    /**
     * Server-Sent Events endpoint for real-time updates
     */
    public function stream(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        // Set SSE headers
        $response = $response
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no');

        // Get a fresh Redis connection for pub/sub
        $redis = new \Predis\Client([
            'scheme' => 'tcp',
            'host'   => $_ENV['REDIS_HOST'] ?? 'redis',
            'port'   => (int) ($_ENV['REDIS_PORT'] ?? 6379),
        ]);

        $channel = $this->cache->getPrefix() . 'checklist:' . $token;

        // Create a streaming response body
        $body = new \Slim\Psr7\Stream(fopen('php://temp', 'r+'));

        // Subscribe to the channel
        $pubsub = $redis->pubSubLoop();
        $pubsub->subscribe($channel);

        // Set a timeout
        $startTime = time();
        $timeout = 30; // 30 seconds, then reconnect

        foreach ($pubsub as $message) {
            if ($message->kind === 'message') {
                $body->write("data: {$message->payload}\n\n");
            }

            // Check timeout
            if (time() - $startTime > $timeout) {
                $body->write("event: timeout\ndata: reconnect\n\n");
                break;
            }

            // Flush output
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }

        $pubsub->unsubscribe();

        return $response->withBody($body);
    }

    /**
     * Get latest updates since a timestamp (fallback for SSE)
     */
    public function getUpdates(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE share_token = ?',
            [$token]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        $params = $request->getQueryParams();
        $since = $params['since'] ?? null;

        // Get recent activity
        $activities = [];
        if ($since) {
            $activities = $this->db->fetchAllAssociative(
                'SELECT * FROM shared_checklist_activity
                 WHERE checklist_id = ? AND created_at > ?
                 ORDER BY created_at ASC',
                [$checklist['id'], $since]
            );
        }

        // Get current version hash (for change detection)
        $versionHash = $this->getChecklistVersionHash($checklist['id']);

        return JsonResponse::success([
            'activities' => $activities,
            'version' => $versionHash,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    // ==================== Helper Methods ====================

    /**
     * Get a version hash for change detection
     */
    private function getChecklistVersionHash(string $checklistId): string
    {
        $data = $this->db->fetchOne(
            'SELECT CONCAT(
                (SELECT COUNT(*) FROM shared_checklist_entries e
                 JOIN shared_checklist_items i ON e.item_id = i.id
                 WHERE i.checklist_id = ?),
                "-",
                (SELECT COALESCE(MAX(e.updated_at), "0") FROM shared_checklist_entries e
                 JOIN shared_checklist_items i ON e.item_id = i.id
                 WHERE i.checklist_id = ?)
            )',
            [$checklistId, $checklistId]
        );
        return md5($data ?: '0');
    }

    /**
     * Publish an update to Redis for real-time sync
     */
    private function publishUpdate(string $token, string $event, array $data): void
    {
        try {
            $this->cache->publish('checklist:' . $token, [
                'event' => $event,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the request
            error_log('Failed to publish checklist update: ' . $e->getMessage());
        }
    }

    /**
     * Get checklist with ownership check
     */
    private function getChecklistForUser(string $checklistId, string $userId, bool $requireOwner = false): array
    {
        $checklist = $this->db->fetchAssociative(
            'SELECT * FROM shared_checklists WHERE id = ?',
            [$checklistId]
        );

        if (!$checklist) {
            throw new NotFoundException('Checkliste nicht gefunden');
        }

        if ($checklist['user_id'] !== $userId) {
            throw new ForbiddenException('Zugriff verweigert');
        }

        return $checklist;
    }

    /**
     * Log an activity
     */
    private function logActivity(string $checklistId, ?string $itemId, ?string $entryId, string $actorName, string $action, array $details): void
    {
        $this->db->insert('shared_checklist_activity', [
            'id' => Uuid::uuid4()->toString(),
            'checklist_id' => $checklistId,
            'item_id' => $itemId,
            'entry_id' => $entryId,
            'actor_name' => $actorName,
            'action' => $action,
            'details' => json_encode($details),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
