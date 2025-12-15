<?php

declare(strict_types=1);

namespace App\Modules\Notes\Controllers;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\JsonResponse;
use App\Modules\Notes\Services\DatabaseService;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Routing\RouteContext;

class DatabaseController
{
    public function __construct(
        private readonly Connection $db,
        private readonly DatabaseService $databaseService
    ) {}

    // =========================================================================
    // DATABASE CRUD
    // =========================================================================

    /**
     * Create a new database
     */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        // Validate note ownership
        $noteId = $data['note_id'] ?? null;
        if ($noteId) {
            $note = $this->db->fetchOne(
                'SELECT id FROM notes WHERE id = ? AND user_id = ?',
                [$noteId, $userId]
            );
            if (!$note) {
                throw new ValidationException('Note not found or access denied');
            }
        }

        $databaseId = Uuid::uuid4()->toString();

        $this->db->insert('note_databases', [
            'id' => $databaseId,
            'note_id' => $noteId,
            'user_id' => $userId,
            'name' => $data['name'] ?? 'Neue Datenbank',
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'default_view' => $data['default_view'] ?? 'table',
            'show_title' => $data['show_title'] ?? true,
            'full_width' => $data['full_width'] ?? false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create default properties
        $this->databaseService->createDefaultProperties($databaseId);

        // Fetch created database with properties
        $database = $this->getDatabaseWithProperties($databaseId, $userId);

        return JsonResponse::created($database, 'Database created successfully');
    }

    /**
     * Get a database with properties and rows
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $database = $this->getDatabaseForUser($databaseId, $userId);

        // Get properties
        $database['properties'] = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_properties WHERE database_id = ? ORDER BY sort_order ASC',
            [$databaseId]
        );

        // Parse config JSON
        foreach ($database['properties'] as &$prop) {
            $prop['config'] = $prop['config'] ? json_decode($prop['config'], true) : null;
            $prop['is_visible'] = (bool) $prop['is_visible'];
            $prop['is_primary'] = (bool) $prop['is_primary'];
            $prop['width'] = (int) $prop['width'];
            $prop['sort_order'] = (int) $prop['sort_order'];
        }

        // Get rows with cells
        $database['rows'] = $this->databaseService->getRowsWithCells($databaseId);

        // Get views
        $database['views'] = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_views WHERE database_id = ? ORDER BY sort_order ASC',
            [$databaseId]
        );

        foreach ($database['views'] as &$view) {
            $view['config'] = $view['config'] ? json_decode($view['config'], true) : [];
            $view['sort_config'] = $view['sort_config'] ? json_decode($view['sort_config'], true) : null;
            $view['filter_config'] = $view['filter_config'] ? json_decode($view['filter_config'], true) : null;
            $view['is_default'] = (bool) $view['is_default'];
        }

        return JsonResponse::success($database);
    }

    /**
     * Update database settings
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        $updateData = [];
        $allowedFields = ['name', 'description', 'icon', 'default_view', 'show_title', 'full_width'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($data['sort_config'])) {
            $updateData['sort_config'] = json_encode($data['sort_config']);
        }

        if (!empty($data['filter_config'])) {
            $updateData['filter_config'] = json_encode($data['filter_config']);
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('note_databases', $updateData, ['id' => $databaseId]);
        }

        $database = $this->getDatabaseWithProperties($databaseId, $userId);

        return JsonResponse::success($database, 'Database updated');
    }

    /**
     * Delete a database
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');

        $this->getDatabaseForUser($databaseId, $userId);

        // Cascade delete handled by foreign keys
        $this->db->delete('note_databases', ['id' => $databaseId]);

        return JsonResponse::success(null, 'Database deleted');
    }

    /**
     * Duplicate a database
     */
    public function duplicate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $database = $this->getDatabaseForUser($databaseId, $userId);

        $newDbId = $this->databaseService->duplicateDatabase(
            $databaseId,
            $data['note_id'] ?? $database['note_id'],
            $userId
        );

        $newDatabase = $this->getDatabaseWithProperties($newDbId, $userId);

        return JsonResponse::created($newDatabase, 'Database duplicated');
    }

    // =========================================================================
    // PROPERTIES
    // =========================================================================

    /**
     * Add a property to database
     */
    public function addProperty(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        // Get max sort order
        $maxOrder = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) FROM note_database_properties WHERE database_id = ?',
            [$databaseId]
        );

        $propertyId = Uuid::uuid4()->toString();

        $this->db->insert('note_database_properties', [
            'id' => $propertyId,
            'database_id' => $databaseId,
            'name' => $data['name'] ?? 'Neue Spalte',
            'type' => $data['type'] ?? 'text',
            'config' => isset($data['config']) ? json_encode($data['config']) : null,
            'width' => $data['width'] ?? 200,
            'is_visible' => $data['is_visible'] ?? true,
            'is_primary' => false,
            'sort_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $property = $this->db->fetchAssociative(
            'SELECT * FROM note_database_properties WHERE id = ?',
            [$propertyId]
        );

        $property['config'] = $property['config'] ? json_decode($property['config'], true) : null;
        $property['is_visible'] = (bool) $property['is_visible'];
        $property['is_primary'] = (bool) $property['is_primary'];

        return JsonResponse::created($property, 'Property added');
    }

    /**
     * Update a property
     */
    public function updateProperty(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $databaseId = $route->getArgument('id');
        $propertyId = $route->getArgument('propertyId');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        $property = $this->db->fetchAssociative(
            'SELECT * FROM note_database_properties WHERE id = ? AND database_id = ?',
            [$propertyId, $databaseId]
        );

        if (!$property) {
            throw new NotFoundException('Property not found');
        }

        $updateData = [];
        $allowedFields = ['name', 'width', 'is_visible', 'sort_order'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // Config update (merge with existing)
        if (isset($data['config'])) {
            $existingConfig = $property['config'] ? json_decode($property['config'], true) : [];
            $updateData['config'] = json_encode(array_merge($existingConfig, $data['config']));
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('note_database_properties', $updateData, ['id' => $propertyId]);
        }

        $updatedProperty = $this->db->fetchAssociative(
            'SELECT * FROM note_database_properties WHERE id = ?',
            [$propertyId]
        );

        $updatedProperty['config'] = $updatedProperty['config'] ? json_decode($updatedProperty['config'], true) : null;
        $updatedProperty['is_visible'] = (bool) $updatedProperty['is_visible'];
        $updatedProperty['is_primary'] = (bool) $updatedProperty['is_primary'];

        return JsonResponse::success($updatedProperty, 'Property updated');
    }

    /**
     * Delete a property
     */
    public function deleteProperty(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $databaseId = $route->getArgument('id');
        $propertyId = $route->getArgument('propertyId');

        $this->getDatabaseForUser($databaseId, $userId);

        $property = $this->db->fetchAssociative(
            'SELECT * FROM note_database_properties WHERE id = ? AND database_id = ?',
            [$propertyId, $databaseId]
        );

        if (!$property) {
            throw new NotFoundException('Property not found');
        }

        if ($property['is_primary']) {
            throw new ValidationException('Cannot delete primary property');
        }

        // Delete property (cells cascade)
        $this->db->delete('note_database_properties', ['id' => $propertyId]);

        return JsonResponse::success(null, 'Property deleted');
    }

    /**
     * Reorder properties
     */
    public function reorderProperties(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        if (empty($data['order']) || !is_array($data['order'])) {
            throw new ValidationException('Order array required');
        }

        foreach ($data['order'] as $index => $propertyId) {
            $this->db->update(
                'note_database_properties',
                ['sort_order' => $index, 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $propertyId, 'database_id' => $databaseId]
            );
        }

        return JsonResponse::success(null, 'Properties reordered');
    }

    // =========================================================================
    // ROWS
    // =========================================================================

    /**
     * Add a row
     */
    public function addRow(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        // Get max sort order
        $maxOrder = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) FROM note_database_rows WHERE database_id = ?',
            [$databaseId]
        );

        $rowId = Uuid::uuid4()->toString();

        $this->db->insert('note_database_rows', [
            'id' => $rowId,
            'database_id' => $databaseId,
            'linked_note_id' => $data['linked_note_id'] ?? null,
            'sort_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        // Set initial cell values if provided
        if (!empty($data['cells']) && is_array($data['cells'])) {
            $properties = $this->db->fetchAllAssociative(
                'SELECT id, type FROM note_database_properties WHERE database_id = ?',
                [$databaseId]
            );

            $propTypes = [];
            foreach ($properties as $prop) {
                $propTypes[$prop['id']] = $prop['type'];
            }

            foreach ($data['cells'] as $propId => $value) {
                if (isset($propTypes[$propId])) {
                    $this->databaseService->setCellValue($rowId, $propId, $propTypes[$propId], $value);
                }
            }
        }

        // Fetch created row
        $row = $this->db->fetchAssociative(
            "SELECT r.*, u1.username as created_by_name, u2.username as updated_by_name
             FROM note_database_rows r
             LEFT JOIN users u1 ON r.created_by = u1.id
             LEFT JOIN users u2 ON r.updated_by = u2.id
             WHERE r.id = ?",
            [$rowId]
        );

        // Get cells
        $properties = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_properties WHERE database_id = ?',
            [$databaseId]
        );

        $cells = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_cells WHERE row_id = ?',
            [$rowId]
        );

        $cellMap = [];
        foreach ($cells as $cell) {
            $cellMap[$cell['property_id']] = $cell;
        }

        $row['cells'] = [];
        foreach ($properties as $prop) {
            $cell = $cellMap[$prop['id']] ?? null;
            $row['cells'][$prop['id']] = $cell
                ? $this->databaseService->getCellValue($cell, $prop['type'])
                : $this->databaseService->getDefaultValue($prop['type']);
        }

        return JsonResponse::created($row, 'Row added');
    }

    /**
     * Update a row (cells)
     */
    public function updateRow(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $databaseId = $route->getArgument('id');
        $rowId = $route->getArgument('rowId');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        $row = $this->db->fetchAssociative(
            'SELECT * FROM note_database_rows WHERE id = ? AND database_id = ?',
            [$rowId, $databaseId]
        );

        if (!$row) {
            throw new NotFoundException('Row not found');
        }

        // Update cells
        if (!empty($data['cells']) && is_array($data['cells'])) {
            $properties = $this->db->fetchAllAssociative(
                'SELECT id, type FROM note_database_properties WHERE database_id = ?',
                [$databaseId]
            );

            $propTypes = [];
            foreach ($properties as $prop) {
                $propTypes[$prop['id']] = $prop['type'];
            }

            foreach ($data['cells'] as $propId => $value) {
                if (isset($propTypes[$propId])) {
                    $this->databaseService->setCellValue($rowId, $propId, $propTypes[$propId], $value);
                }
            }
        }

        // Update row metadata
        $this->db->update('note_database_rows', [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        ], ['id' => $rowId]);

        return JsonResponse::success(null, 'Row updated');
    }

    /**
     * Delete a row
     */
    public function deleteRow(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $databaseId = $route->getArgument('id');
        $rowId = $route->getArgument('rowId');

        $this->getDatabaseForUser($databaseId, $userId);

        $row = $this->db->fetchOne(
            'SELECT id FROM note_database_rows WHERE id = ? AND database_id = ?',
            [$rowId, $databaseId]
        );

        if (!$row) {
            throw new NotFoundException('Row not found');
        }

        $this->db->delete('note_database_rows', ['id' => $rowId]);

        return JsonResponse::success(null, 'Row deleted');
    }

    /**
     * Reorder rows
     */
    public function reorderRows(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        if (empty($data['order']) || !is_array($data['order'])) {
            throw new ValidationException('Order array required');
        }

        foreach ($data['order'] as $index => $rowId) {
            $this->db->update(
                'note_database_rows',
                ['sort_order' => $index, 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $rowId, 'database_id' => $databaseId]
            );
        }

        return JsonResponse::success(null, 'Rows reordered');
    }

    // =========================================================================
    // VIEWS
    // =========================================================================

    /**
     * Create a view
     */
    public function createView(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $databaseId = RouteContext::fromRequest($request)->getRoute()->getArgument('id');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        $viewId = Uuid::uuid4()->toString();

        // Get max sort order
        $maxOrder = (int) $this->db->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) FROM note_database_views WHERE database_id = ?',
            [$databaseId]
        );

        $this->db->insert('note_database_views', [
            'id' => $viewId,
            'database_id' => $databaseId,
            'name' => $data['name'] ?? 'Neue Ansicht',
            'type' => $data['type'] ?? 'table',
            'config' => json_encode($data['config'] ?? []),
            'sort_config' => isset($data['sort_config']) ? json_encode($data['sort_config']) : null,
            'filter_config' => isset($data['filter_config']) ? json_encode($data['filter_config']) : null,
            'is_default' => false,
            'sort_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $view = $this->db->fetchAssociative(
            'SELECT * FROM note_database_views WHERE id = ?',
            [$viewId]
        );

        $view['config'] = json_decode($view['config'], true);
        $view['sort_config'] = $view['sort_config'] ? json_decode($view['sort_config'], true) : null;
        $view['filter_config'] = $view['filter_config'] ? json_decode($view['filter_config'], true) : null;

        return JsonResponse::created($view, 'View created');
    }

    /**
     * Update a view
     */
    public function updateView(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $databaseId = $route->getArgument('id');
        $viewId = $route->getArgument('viewId');
        $data = $request->getParsedBody() ?? [];

        $this->getDatabaseForUser($databaseId, $userId);

        $view = $this->db->fetchOne(
            'SELECT id FROM note_database_views WHERE id = ? AND database_id = ?',
            [$viewId, $databaseId]
        );

        if (!$view) {
            throw new NotFoundException('View not found');
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['config'])) {
            $updateData['config'] = json_encode($data['config']);
        }

        if (isset($data['sort_config'])) {
            $updateData['sort_config'] = json_encode($data['sort_config']);
        }

        if (isset($data['filter_config'])) {
            $updateData['filter_config'] = json_encode($data['filter_config']);
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('note_database_views', $updateData, ['id' => $viewId]);
        }

        return JsonResponse::success(null, 'View updated');
    }

    /**
     * Delete a view
     */
    public function deleteView(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $route = RouteContext::fromRequest($request)->getRoute();
        $databaseId = $route->getArgument('id');
        $viewId = $route->getArgument('viewId');

        $this->getDatabaseForUser($databaseId, $userId);

        $this->db->delete('note_database_views', [
            'id' => $viewId,
            'database_id' => $databaseId
        ]);

        return JsonResponse::success(null, 'View deleted');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function getDatabaseForUser(string $databaseId, string $userId): array
    {
        $database = $this->db->fetchAssociative(
            'SELECT * FROM note_databases WHERE id = ? AND user_id = ?',
            [$databaseId, $userId]
        );

        if (!$database) {
            throw new NotFoundException('Database not found');
        }

        return $database;
    }

    private function getDatabaseWithProperties(string $databaseId, string $userId): array
    {
        $database = $this->getDatabaseForUser($databaseId, $userId);

        // Parse JSON fields
        $database['sort_config'] = $database['sort_config'] ? json_decode($database['sort_config'], true) : null;
        $database['filter_config'] = $database['filter_config'] ? json_decode($database['filter_config'], true) : null;
        $database['show_title'] = (bool) $database['show_title'];
        $database['full_width'] = (bool) $database['full_width'];

        // Get properties
        $database['properties'] = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_properties WHERE database_id = ? ORDER BY sort_order ASC',
            [$databaseId]
        );

        foreach ($database['properties'] as &$prop) {
            $prop['config'] = $prop['config'] ? json_decode($prop['config'], true) : null;
            $prop['is_visible'] = (bool) $prop['is_visible'];
            $prop['is_primary'] = (bool) $prop['is_primary'];
            $prop['width'] = (int) $prop['width'];
            $prop['sort_order'] = (int) $prop['sort_order'];
        }

        return $database;
    }
}
