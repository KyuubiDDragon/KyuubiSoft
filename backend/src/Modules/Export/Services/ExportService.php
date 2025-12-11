<?php

declare(strict_types=1);

namespace App\Modules\Export\Services;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class ExportService
{
    private const EXPORTABLE_TYPES = [
        'lists' => 'lists',
        'documents' => 'documents',
        'snippets' => 'snippets',
        'bookmarks' => 'bookmarks',
        'connections' => 'connections',
        'passwords' => 'passwords',
        'checklists' => 'checklists',
        'kanban' => 'kanban_boards',
        'projects' => 'projects',
        'invoices' => 'invoices',
        'calendar' => 'calendar_events',
        'time_entries' => 'time_entries',
    ];

    public function __construct(
        private Connection $db,
        private LoggerInterface $logger
    ) {}

    /**
     * Export user data
     */
    public function exportData(string $userId, array $types, string $format = 'json'): array
    {
        $exportData = [
            'version' => '1.0',
            'exported_at' => date('c'),
            'format' => $format,
            'data' => [],
        ];

        foreach ($types as $type) {
            if (!isset(self::EXPORTABLE_TYPES[$type])) {
                continue;
            }

            $data = match ($type) {
                'lists' => $this->exportLists($userId),
                'documents' => $this->exportDocuments($userId),
                'snippets' => $this->exportSnippets($userId),
                'bookmarks' => $this->exportBookmarks($userId),
                'connections' => $this->exportConnections($userId),
                'passwords' => $this->exportPasswords($userId),
                'checklists' => $this->exportChecklists($userId),
                'kanban' => $this->exportKanban($userId),
                'projects' => $this->exportProjects($userId),
                'invoices' => $this->exportInvoices($userId),
                'calendar' => $this->exportCalendar($userId),
                'time_entries' => $this->exportTimeEntries($userId),
                default => [],
            };

            if (!empty($data)) {
                $exportData['data'][$type] = $data;
            }
        }

        return $exportData;
    }

    /**
     * Get export statistics
     */
    public function getExportStats(string $userId): array
    {
        $stats = [];

        foreach (self::EXPORTABLE_TYPES as $key => $table) {
            $count = $this->db->fetchOne(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = ?",
                [$userId]
            );
            $stats[$key] = (int) $count;
        }

        return $stats;
    }

    /**
     * Import user data
     */
    public function importData(string $userId, array $data, array $options = []): array
    {
        $results = [
            'success' => true,
            'imported' => [],
            'skipped' => [],
            'errors' => [],
        ];

        $conflictResolution = $options['conflict_resolution'] ?? 'skip';
        $importTypes = $options['types'] ?? array_keys($data['data'] ?? []);

        $this->db->beginTransaction();

        try {
            foreach ($importTypes as $type) {
                if (!isset($data['data'][$type])) {
                    continue;
                }

                $items = $data['data'][$type];
                $result = match ($type) {
                    'lists' => $this->importLists($userId, $items, $conflictResolution),
                    'documents' => $this->importDocuments($userId, $items, $conflictResolution),
                    'snippets' => $this->importSnippets($userId, $items, $conflictResolution),
                    'bookmarks' => $this->importBookmarks($userId, $items, $conflictResolution),
                    'connections' => $this->importConnections($userId, $items, $conflictResolution),
                    'passwords' => $this->importPasswords($userId, $items, $conflictResolution),
                    'checklists' => $this->importChecklists($userId, $items, $conflictResolution),
                    'kanban' => $this->importKanban($userId, $items, $conflictResolution),
                    'projects' => $this->importProjects($userId, $items, $conflictResolution),
                    'invoices' => $this->importInvoices($userId, $items, $conflictResolution),
                    'calendar' => $this->importCalendar($userId, $items, $conflictResolution),
                    'time_entries' => $this->importTimeEntries($userId, $items, $conflictResolution),
                    default => ['imported' => 0, 'skipped' => 0, 'errors' => []],
                };

                $results['imported'][$type] = $result['imported'];
                $results['skipped'][$type] = $result['skipped'];
                if (!empty($result['errors'])) {
                    $results['errors'][$type] = $result['errors'];
                }
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            $results['success'] = false;
            $results['errors']['general'] = $e->getMessage();
            $this->logger->error('Import failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Validate import data
     */
    public function validateImportData(array $data): array
    {
        $errors = [];

        if (!isset($data['version'])) {
            $errors[] = 'Missing version field';
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            $errors[] = 'Missing or invalid data field';
        }

        if (isset($data['version']) && version_compare($data['version'], '1.0', '<')) {
            $errors[] = 'Unsupported export version';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'types' => array_keys($data['data'] ?? []),
            'counts' => array_map('count', $data['data'] ?? []),
        ];
    }

    // Export methods

    private function exportLists(string $userId): array
    {
        $lists = $this->db->fetchAllAssociative(
            "SELECT id, name, description, color, icon, is_archived, sort_order, created_at, updated_at
             FROM lists WHERE user_id = ? ORDER BY sort_order",
            [$userId]
        );

        foreach ($lists as &$list) {
            $list['items'] = $this->db->fetchAllAssociative(
                "SELECT id, content, is_completed, priority, due_date, notes, sort_order, created_at
                 FROM list_items WHERE list_id = ? ORDER BY sort_order",
                [$list['id']]
            );
        }

        return $lists;
    }

    private function exportDocuments(string $userId): array
    {
        $documents = $this->db->fetchAllAssociative(
            "SELECT id, title, content, folder_id, is_favorite, is_archived, tags, created_at, updated_at
             FROM documents WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );

        $folders = $this->db->fetchAllAssociative(
            "SELECT id, name, parent_id, color, icon, created_at
             FROM document_folders WHERE user_id = ? ORDER BY name",
            [$userId]
        );

        return [
            'documents' => $documents,
            'folders' => $folders,
        ];
    }

    private function exportSnippets(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT id, title, code, language, description, tags, is_favorite, folder_id, created_at, updated_at
             FROM snippets WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );
    }

    private function exportBookmarks(string $userId): array
    {
        $bookmarks = $this->db->fetchAllAssociative(
            "SELECT id, title, url, description, favicon, folder_id, tags, is_favorite, created_at
             FROM bookmarks WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );

        $folders = $this->db->fetchAllAssociative(
            "SELECT id, name, parent_id, color, icon, created_at
             FROM bookmark_folders WHERE user_id = ? ORDER BY name",
            [$userId]
        );

        return [
            'bookmarks' => $bookmarks,
            'folders' => $folders,
        ];
    }

    private function exportConnections(string $userId): array
    {
        // Export connections without sensitive data
        return $this->db->fetchAllAssociative(
            "SELECT id, name, type, host, port, username, description, tags, folder_id, created_at, updated_at
             FROM connections WHERE user_id = ? ORDER BY name",
            [$userId]
        );
    }

    private function exportPasswords(string $userId): array
    {
        // Export passwords with encrypted data (user needs master password to decrypt)
        $passwords = $this->db->fetchAllAssociative(
            "SELECT id, category_id, name, username, password_encrypted, password_iv, password_tag,
                    url, notes_encrypted, notes_iv, notes_tag, totp_secret_encrypted, totp_secret_iv, totp_secret_tag,
                    is_favorite, created_at, updated_at
             FROM passwords WHERE user_id = ? ORDER BY name",
            [$userId]
        );

        $categories = $this->db->fetchAllAssociative(
            "SELECT id, name, icon, color, sort_order, created_at
             FROM password_categories WHERE user_id = ? ORDER BY sort_order",
            [$userId]
        );

        return [
            'passwords' => $passwords,
            'categories' => $categories,
        ];
    }

    private function exportChecklists(string $userId): array
    {
        $checklists = $this->db->fetchAllAssociative(
            "SELECT id, title, description, color, icon, is_archived, created_at, updated_at
             FROM checklists WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );

        foreach ($checklists as &$checklist) {
            $checklist['items'] = $this->db->fetchAllAssociative(
                "SELECT id, content, is_completed, sort_order, notes, image_url, created_at
                 FROM checklist_items WHERE checklist_id = ? ORDER BY sort_order",
                [$checklist['id']]
            );
        }

        return $checklists;
    }

    private function exportKanban(string $userId): array
    {
        $boards = $this->db->fetchAllAssociative(
            "SELECT id, name, description, color, is_archived, created_at, updated_at
             FROM kanban_boards WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );

        foreach ($boards as &$board) {
            $board['columns'] = $this->db->fetchAllAssociative(
                "SELECT id, name, color, wip_limit, sort_order
                 FROM kanban_columns WHERE board_id = ? ORDER BY sort_order",
                [$board['id']]
            );

            foreach ($board['columns'] as &$column) {
                $column['cards'] = $this->db->fetchAllAssociative(
                    "SELECT id, title, description, color, priority, due_date, labels, sort_order, created_at
                     FROM kanban_cards WHERE column_id = ? ORDER BY sort_order",
                    [$column['id']]
                );
            }
        }

        return $boards;
    }

    private function exportProjects(string $userId): array
    {
        $projects = $this->db->fetchAllAssociative(
            "SELECT id, name, description, status, color, start_date, end_date, created_at, updated_at
             FROM projects WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );

        foreach ($projects as &$project) {
            $project['tasks'] = $this->db->fetchAllAssociative(
                "SELECT id, title, description, status, priority, due_date, estimated_hours, actual_hours, created_at
                 FROM project_tasks WHERE project_id = ? ORDER BY created_at",
                [$project['id']]
            );
        }

        return $projects;
    }

    private function exportInvoices(string $userId): array
    {
        $invoices = $this->db->fetchAllAssociative(
            "SELECT id, invoice_number, client_name, client_email, client_address, status,
                    issue_date, due_date, subtotal, tax_rate, tax_amount, total, notes, created_at
             FROM invoices WHERE user_id = ? ORDER BY created_at",
            [$userId]
        );

        foreach ($invoices as &$invoice) {
            $invoice['items'] = $this->db->fetchAllAssociative(
                "SELECT id, description, quantity, unit_price, total
                 FROM invoice_items WHERE invoice_id = ?",
                [$invoice['id']]
            );
        }

        return $invoices;
    }

    private function exportCalendar(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT id, title, description, start_time, end_time, all_day, color, location, reminder, recurrence, created_at
             FROM calendar_events WHERE user_id = ? ORDER BY start_time",
            [$userId]
        );
    }

    private function exportTimeEntries(string $userId): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT id, project_id, task_id, description, start_time, end_time, duration, billable, created_at
             FROM time_entries WHERE user_id = ? ORDER BY start_time",
            [$userId]
        );
    }

    // Import methods

    private function importLists(string $userId, array $lists, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $idMap = [];

        foreach ($lists as $list) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM lists WHERE user_id = ? AND name = ?",
                    [$userId, $list['name']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        $idMap[$list['id']] = $existingId;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('list_items', ['list_id' => $existingId]);
                        $this->db->delete('lists', ['id' => $existingId]);
                    } else {
                        // rename
                        $list['name'] = $list['name'] . ' (imported)';
                    }
                }

                $newId = $this->generateUuid();
                $idMap[$list['id']] = $newId;

                $this->db->insert('lists', [
                    'id' => $newId,
                    'user_id' => $userId,
                    'name' => $list['name'],
                    'description' => $list['description'] ?? null,
                    'color' => $list['color'] ?? null,
                    'icon' => $list['icon'] ?? null,
                    'is_archived' => $list['is_archived'] ?? 0,
                    'sort_order' => $list['sort_order'] ?? 0,
                    'created_at' => $list['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Import items
                if (!empty($list['items'])) {
                    foreach ($list['items'] as $item) {
                        $this->db->insert('list_items', [
                            'id' => $this->generateUuid(),
                            'list_id' => $newId,
                            'content' => $item['content'],
                            'is_completed' => $item['is_completed'] ?? 0,
                            'priority' => $item['priority'] ?? 'medium',
                            'due_date' => $item['due_date'] ?? null,
                            'notes' => $item['notes'] ?? null,
                            'sort_order' => $item['sort_order'] ?? 0,
                            'created_at' => $item['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "List '{$list['name']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importDocuments(string $userId, array $data, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $folderIdMap = [];

        // Import folders first
        if (!empty($data['folders'])) {
            foreach ($data['folders'] as $folder) {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM document_folders WHERE user_id = ? AND name = ?",
                    [$userId, $folder['name']]
                );

                if ($existingId) {
                    $folderIdMap[$folder['id']] = $existingId;
                } else {
                    $newId = $this->generateUuid();
                    $folderIdMap[$folder['id']] = $newId;
                    $this->db->insert('document_folders', [
                        'id' => $newId,
                        'user_id' => $userId,
                        'name' => $folder['name'],
                        'parent_id' => isset($folder['parent_id']) ? ($folderIdMap[$folder['parent_id']] ?? null) : null,
                        'color' => $folder['color'] ?? null,
                        'icon' => $folder['icon'] ?? null,
                        'created_at' => $folder['created_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        // Import documents
        $documents = $data['documents'] ?? $data;
        foreach ($documents as $doc) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM documents WHERE user_id = ? AND title = ?",
                    [$userId, $doc['title']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('documents', ['id' => $existingId]);
                    } else {
                        $doc['title'] = $doc['title'] . ' (imported)';
                    }
                }

                $this->db->insert('documents', [
                    'id' => $this->generateUuid(),
                    'user_id' => $userId,
                    'title' => $doc['title'],
                    'content' => $doc['content'] ?? '',
                    'folder_id' => isset($doc['folder_id']) ? ($folderIdMap[$doc['folder_id']] ?? null) : null,
                    'is_favorite' => $doc['is_favorite'] ?? 0,
                    'is_archived' => $doc['is_archived'] ?? 0,
                    'tags' => $doc['tags'] ?? null,
                    'created_at' => $doc['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Document '{$doc['title']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importSnippets(string $userId, array $snippets, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($snippets as $snippet) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM snippets WHERE user_id = ? AND title = ?",
                    [$userId, $snippet['title']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('snippets', ['id' => $existingId]);
                    } else {
                        $snippet['title'] = $snippet['title'] . ' (imported)';
                    }
                }

                $this->db->insert('snippets', [
                    'id' => $this->generateUuid(),
                    'user_id' => $userId,
                    'title' => $snippet['title'],
                    'code' => $snippet['code'],
                    'language' => $snippet['language'] ?? 'plaintext',
                    'description' => $snippet['description'] ?? null,
                    'tags' => $snippet['tags'] ?? null,
                    'is_favorite' => $snippet['is_favorite'] ?? 0,
                    'folder_id' => $snippet['folder_id'] ?? null,
                    'created_at' => $snippet['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Snippet '{$snippet['title']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importBookmarks(string $userId, array $data, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $folderIdMap = [];

        // Import folders first
        if (!empty($data['folders'])) {
            foreach ($data['folders'] as $folder) {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM bookmark_folders WHERE user_id = ? AND name = ?",
                    [$userId, $folder['name']]
                );

                if ($existingId) {
                    $folderIdMap[$folder['id']] = $existingId;
                } else {
                    $newId = $this->generateUuid();
                    $folderIdMap[$folder['id']] = $newId;
                    $this->db->insert('bookmark_folders', [
                        'id' => $newId,
                        'user_id' => $userId,
                        'name' => $folder['name'],
                        'parent_id' => isset($folder['parent_id']) ? ($folderIdMap[$folder['parent_id']] ?? null) : null,
                        'color' => $folder['color'] ?? null,
                        'icon' => $folder['icon'] ?? null,
                        'created_at' => $folder['created_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        $bookmarks = $data['bookmarks'] ?? $data;
        foreach ($bookmarks as $bookmark) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM bookmarks WHERE user_id = ? AND url = ?",
                    [$userId, $bookmark['url']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('bookmarks', ['id' => $existingId]);
                    } else {
                        $bookmark['title'] = $bookmark['title'] . ' (imported)';
                    }
                }

                $this->db->insert('bookmarks', [
                    'id' => $this->generateUuid(),
                    'user_id' => $userId,
                    'title' => $bookmark['title'],
                    'url' => $bookmark['url'],
                    'description' => $bookmark['description'] ?? null,
                    'favicon' => $bookmark['favicon'] ?? null,
                    'folder_id' => isset($bookmark['folder_id']) ? ($folderIdMap[$bookmark['folder_id']] ?? null) : null,
                    'tags' => $bookmark['tags'] ?? null,
                    'is_favorite' => $bookmark['is_favorite'] ?? 0,
                    'created_at' => $bookmark['created_at'] ?? date('Y-m-d H:i:s'),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Bookmark '{$bookmark['title']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importConnections(string $userId, array $connections, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($connections as $conn) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM connections WHERE user_id = ? AND name = ?",
                    [$userId, $conn['name']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('connections', ['id' => $existingId]);
                    } else {
                        $conn['name'] = $conn['name'] . ' (imported)';
                    }
                }

                $this->db->insert('connections', [
                    'id' => $this->generateUuid(),
                    'user_id' => $userId,
                    'name' => $conn['name'],
                    'type' => $conn['type'] ?? 'ssh',
                    'host' => $conn['host'],
                    'port' => $conn['port'] ?? 22,
                    'username' => $conn['username'] ?? null,
                    'description' => $conn['description'] ?? null,
                    'tags' => $conn['tags'] ?? null,
                    'folder_id' => $conn['folder_id'] ?? null,
                    'created_at' => $conn['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Connection '{$conn['name']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importPasswords(string $userId, array $data, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $categoryIdMap = [];

        // Import categories first
        if (!empty($data['categories'])) {
            foreach ($data['categories'] as $category) {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM password_categories WHERE user_id = ? AND name = ?",
                    [$userId, $category['name']]
                );

                if ($existingId) {
                    $categoryIdMap[$category['id']] = $existingId;
                } else {
                    $newId = $this->generateUuid();
                    $categoryIdMap[$category['id']] = $newId;
                    $this->db->insert('password_categories', [
                        'id' => $newId,
                        'user_id' => $userId,
                        'name' => $category['name'],
                        'icon' => $category['icon'] ?? null,
                        'color' => $category['color'] ?? null,
                        'sort_order' => $category['sort_order'] ?? 0,
                        'created_at' => $category['created_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        $passwords = $data['passwords'] ?? $data;
        foreach ($passwords as $pwd) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM passwords WHERE user_id = ? AND name = ?",
                    [$userId, $pwd['name']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('passwords', ['id' => $existingId]);
                    } else {
                        $pwd['name'] = $pwd['name'] . ' (imported)';
                    }
                }

                $this->db->insert('passwords', [
                    'id' => $this->generateUuid(),
                    'user_id' => $userId,
                    'category_id' => isset($pwd['category_id']) ? ($categoryIdMap[$pwd['category_id']] ?? null) : null,
                    'name' => $pwd['name'],
                    'username' => $pwd['username'] ?? null,
                    'password_encrypted' => $pwd['password_encrypted'],
                    'password_iv' => $pwd['password_iv'],
                    'password_tag' => $pwd['password_tag'],
                    'url' => $pwd['url'] ?? null,
                    'notes_encrypted' => $pwd['notes_encrypted'] ?? null,
                    'notes_iv' => $pwd['notes_iv'] ?? null,
                    'notes_tag' => $pwd['notes_tag'] ?? null,
                    'totp_secret_encrypted' => $pwd['totp_secret_encrypted'] ?? null,
                    'totp_secret_iv' => $pwd['totp_secret_iv'] ?? null,
                    'totp_secret_tag' => $pwd['totp_secret_tag'] ?? null,
                    'is_favorite' => $pwd['is_favorite'] ?? 0,
                    'created_at' => $pwd['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Password '{$pwd['name']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importChecklists(string $userId, array $checklists, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($checklists as $checklist) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM checklists WHERE user_id = ? AND title = ?",
                    [$userId, $checklist['title']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('checklist_items', ['checklist_id' => $existingId]);
                        $this->db->delete('checklists', ['id' => $existingId]);
                    } else {
                        $checklist['title'] = $checklist['title'] . ' (imported)';
                    }
                }

                $newId = $this->generateUuid();
                $this->db->insert('checklists', [
                    'id' => $newId,
                    'user_id' => $userId,
                    'title' => $checklist['title'],
                    'description' => $checklist['description'] ?? null,
                    'color' => $checklist['color'] ?? null,
                    'icon' => $checklist['icon'] ?? null,
                    'is_archived' => $checklist['is_archived'] ?? 0,
                    'created_at' => $checklist['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if (!empty($checklist['items'])) {
                    foreach ($checklist['items'] as $item) {
                        $this->db->insert('checklist_items', [
                            'id' => $this->generateUuid(),
                            'checklist_id' => $newId,
                            'content' => $item['content'],
                            'is_completed' => $item['is_completed'] ?? 0,
                            'sort_order' => $item['sort_order'] ?? 0,
                            'notes' => $item['notes'] ?? null,
                            'image_url' => $item['image_url'] ?? null,
                            'created_at' => $item['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Checklist '{$checklist['title']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importKanban(string $userId, array $boards, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($boards as $board) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM kanban_boards WHERE user_id = ? AND name = ?",
                    [$userId, $board['name']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        // Delete cards, columns, then board
                        $columnIds = $this->db->fetchFirstColumn(
                            "SELECT id FROM kanban_columns WHERE board_id = ?",
                            [$existingId]
                        );
                        foreach ($columnIds as $colId) {
                            $this->db->delete('kanban_cards', ['column_id' => $colId]);
                        }
                        $this->db->delete('kanban_columns', ['board_id' => $existingId]);
                        $this->db->delete('kanban_boards', ['id' => $existingId]);
                    } else {
                        $board['name'] = $board['name'] . ' (imported)';
                    }
                }

                $boardId = $this->generateUuid();
                $this->db->insert('kanban_boards', [
                    'id' => $boardId,
                    'user_id' => $userId,
                    'name' => $board['name'],
                    'description' => $board['description'] ?? null,
                    'color' => $board['color'] ?? null,
                    'is_archived' => $board['is_archived'] ?? 0,
                    'created_at' => $board['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if (!empty($board['columns'])) {
                    foreach ($board['columns'] as $column) {
                        $columnId = $this->generateUuid();
                        $this->db->insert('kanban_columns', [
                            'id' => $columnId,
                            'board_id' => $boardId,
                            'name' => $column['name'],
                            'color' => $column['color'] ?? null,
                            'wip_limit' => $column['wip_limit'] ?? null,
                            'sort_order' => $column['sort_order'] ?? 0,
                        ]);

                        if (!empty($column['cards'])) {
                            foreach ($column['cards'] as $card) {
                                $this->db->insert('kanban_cards', [
                                    'id' => $this->generateUuid(),
                                    'column_id' => $columnId,
                                    'title' => $card['title'],
                                    'description' => $card['description'] ?? null,
                                    'color' => $card['color'] ?? null,
                                    'priority' => $card['priority'] ?? 'medium',
                                    'due_date' => $card['due_date'] ?? null,
                                    'labels' => $card['labels'] ?? null,
                                    'sort_order' => $card['sort_order'] ?? 0,
                                    'created_at' => $card['created_at'] ?? date('Y-m-d H:i:s'),
                                ]);
                            }
                        }
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Kanban board '{$board['name']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importProjects(string $userId, array $projects, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($projects as $project) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM projects WHERE user_id = ? AND name = ?",
                    [$userId, $project['name']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('project_tasks', ['project_id' => $existingId]);
                        $this->db->delete('projects', ['id' => $existingId]);
                    } else {
                        $project['name'] = $project['name'] . ' (imported)';
                    }
                }

                $projectId = $this->generateUuid();
                $this->db->insert('projects', [
                    'id' => $projectId,
                    'user_id' => $userId,
                    'name' => $project['name'],
                    'description' => $project['description'] ?? null,
                    'status' => $project['status'] ?? 'active',
                    'color' => $project['color'] ?? null,
                    'start_date' => $project['start_date'] ?? null,
                    'end_date' => $project['end_date'] ?? null,
                    'created_at' => $project['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if (!empty($project['tasks'])) {
                    foreach ($project['tasks'] as $task) {
                        $this->db->insert('project_tasks', [
                            'id' => $this->generateUuid(),
                            'project_id' => $projectId,
                            'title' => $task['title'],
                            'description' => $task['description'] ?? null,
                            'status' => $task['status'] ?? 'todo',
                            'priority' => $task['priority'] ?? 'medium',
                            'due_date' => $task['due_date'] ?? null,
                            'estimated_hours' => $task['estimated_hours'] ?? null,
                            'actual_hours' => $task['actual_hours'] ?? null,
                            'created_at' => $task['created_at'] ?? date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Project '{$project['name']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importInvoices(string $userId, array $invoices, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($invoices as $invoice) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM invoices WHERE user_id = ? AND invoice_number = ?",
                    [$userId, $invoice['invoice_number']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('invoice_items', ['invoice_id' => $existingId]);
                        $this->db->delete('invoices', ['id' => $existingId]);
                    } else {
                        $invoice['invoice_number'] = $invoice['invoice_number'] . '-imported';
                    }
                }

                $invoiceId = $this->generateUuid();
                $this->db->insert('invoices', [
                    'id' => $invoiceId,
                    'user_id' => $userId,
                    'invoice_number' => $invoice['invoice_number'],
                    'client_name' => $invoice['client_name'],
                    'client_email' => $invoice['client_email'] ?? null,
                    'client_address' => $invoice['client_address'] ?? null,
                    'status' => $invoice['status'] ?? 'draft',
                    'issue_date' => $invoice['issue_date'],
                    'due_date' => $invoice['due_date'],
                    'subtotal' => $invoice['subtotal'],
                    'tax_rate' => $invoice['tax_rate'] ?? 0,
                    'tax_amount' => $invoice['tax_amount'] ?? 0,
                    'total' => $invoice['total'],
                    'notes' => $invoice['notes'] ?? null,
                    'created_at' => $invoice['created_at'] ?? date('Y-m-d H:i:s'),
                ]);

                if (!empty($invoice['items'])) {
                    foreach ($invoice['items'] as $item) {
                        $this->db->insert('invoice_items', [
                            'id' => $this->generateUuid(),
                            'invoice_id' => $invoiceId,
                            'description' => $item['description'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total' => $item['total'],
                        ]);
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Invoice '{$invoice['invoice_number']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importCalendar(string $userId, array $events, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($events as $event) {
            try {
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM calendar_events WHERE user_id = ? AND title = ? AND start_time = ?",
                    [$userId, $event['title'], $event['start_time']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('calendar_events', ['id' => $existingId]);
                    } else {
                        $event['title'] = $event['title'] . ' (imported)';
                    }
                }

                $this->db->insert('calendar_events', [
                    'id' => $this->generateUuid(),
                    'user_id' => $userId,
                    'title' => $event['title'],
                    'description' => $event['description'] ?? null,
                    'start_time' => $event['start_time'],
                    'end_time' => $event['end_time'] ?? null,
                    'all_day' => $event['all_day'] ?? 0,
                    'color' => $event['color'] ?? null,
                    'location' => $event['location'] ?? null,
                    'reminder' => $event['reminder'] ?? null,
                    'recurrence' => $event['recurrence'] ?? null,
                    'created_at' => $event['created_at'] ?? date('Y-m-d H:i:s'),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Event '{$event['title']}': " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function importTimeEntries(string $userId, array $entries, string $conflictResolution): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($entries as $entry) {
            try {
                // For time entries, skip duplicates based on start_time
                $existingId = $this->db->fetchOne(
                    "SELECT id FROM time_entries WHERE user_id = ? AND start_time = ?",
                    [$userId, $entry['start_time']]
                );

                if ($existingId) {
                    if ($conflictResolution === 'skip') {
                        $skipped++;
                        continue;
                    } elseif ($conflictResolution === 'replace') {
                        $this->db->delete('time_entries', ['id' => $existingId]);
                    }
                }

                $this->db->insert('time_entries', [
                    'id' => $this->generateUuid(),
                    'user_id' => $userId,
                    'project_id' => $entry['project_id'] ?? null,
                    'task_id' => $entry['task_id'] ?? null,
                    'description' => $entry['description'] ?? null,
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'] ?? null,
                    'duration' => $entry['duration'] ?? null,
                    'billable' => $entry['billable'] ?? 0,
                    'created_at' => $entry['created_at'] ?? date('Y-m-d H:i:s'),
                ]);

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Time entry: " . $e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
