<?php

declare(strict_types=1);

namespace App\Modules\Notes\Services;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;

class DatabaseService
{
    public function __construct(
        private readonly Connection $db
    ) {}

    /**
     * Get cell value based on property type
     */
    public function getCellValue(array $cell, string $propertyType): mixed
    {
        return match ($propertyType) {
            'text', 'url', 'email', 'phone' => $cell['value_text'],
            'number' => $cell['value_number'] !== null ? (float) $cell['value_number'] : null,
            'checkbox' => (bool) $cell['value_boolean'],
            'date' => [
                'start' => $cell['value_date'],
                'end' => $cell['value_date_end'],
            ],
            'select' => $cell['value_text'],
            'multi_select', 'person', 'relation' => json_decode($cell['value_json'] ?? '[]', true),
            'created_time', 'updated_time' => $cell['value_date'],
            default => $cell['value_text'],
        };
    }

    /**
     * Set cell value based on property type
     */
    public function setCellValue(string $rowId, string $propertyId, string $propertyType, mixed $value): void
    {
        $data = [
            'value_text' => null,
            'value_number' => null,
            'value_boolean' => null,
            'value_date' => null,
            'value_date_end' => null,
            'value_json' => null,
        ];

        switch ($propertyType) {
            case 'text':
            case 'url':
            case 'email':
            case 'phone':
            case 'select':
                $data['value_text'] = $value;
                break;

            case 'number':
                $data['value_number'] = is_numeric($value) ? (float) $value : null;
                break;

            case 'checkbox':
                $data['value_boolean'] = (bool) $value;
                break;

            case 'date':
                if (is_array($value)) {
                    $data['value_date'] = $value['start'] ?? null;
                    $data['value_date_end'] = $value['end'] ?? null;
                } else {
                    $data['value_date'] = $value;
                }
                break;

            case 'multi_select':
            case 'person':
            case 'relation':
                $data['value_json'] = json_encode($value ?? []);
                break;
        }

        // Upsert cell
        $existing = $this->db->fetchOne(
            'SELECT id FROM note_database_cells WHERE row_id = ? AND property_id = ?',
            [$rowId, $propertyId]
        );

        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->update('note_database_cells', $data, [
                'row_id' => $rowId,
                'property_id' => $propertyId
            ]);
        } else {
            $data['id'] = Uuid::uuid4()->toString();
            $data['row_id'] = $rowId;
            $data['property_id'] = $propertyId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->insert('note_database_cells', $data);
        }
    }

    /**
     * Get rows with all cell values for a database
     */
    public function getRowsWithCells(string $databaseId, ?array $filters = null, ?array $sorts = null): array
    {
        // Get properties
        $properties = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_properties WHERE database_id = ? ORDER BY sort_order ASC',
            [$databaseId]
        );

        $propertyMap = [];
        foreach ($properties as $prop) {
            $propertyMap[$prop['id']] = $prop;
        }

        // Build query
        $sql = "SELECT r.*, u1.username as created_by_name, u2.username as updated_by_name
                FROM note_database_rows r
                LEFT JOIN users u1 ON r.created_by = u1.id
                LEFT JOIN users u2 ON r.updated_by = u2.id
                WHERE r.database_id = ? AND r.is_archived = FALSE";
        $params = [$databaseId];

        // Apply filters if provided
        if ($filters && !empty($filters['conditions'])) {
            foreach ($filters['conditions'] as $condition) {
                // Filter logic would be added here
                // This is simplified for now
            }
        }

        // Apply sorting
        if ($sorts && !empty($sorts)) {
            // For now, default sort by sort_order
            $sql .= " ORDER BY r.sort_order ASC";
        } else {
            $sql .= " ORDER BY r.sort_order ASC";
        }

        $rows = $this->db->fetchAllAssociative($sql, $params);

        // Get all cells for these rows
        $rowIds = array_column($rows, 'id');
        if (empty($rowIds)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($rowIds) - 1) . '?';
        $cells = $this->db->fetchAllAssociative(
            "SELECT * FROM note_database_cells WHERE row_id IN ($placeholders)",
            $rowIds
        );

        // Group cells by row
        $cellsByRow = [];
        foreach ($cells as $cell) {
            if (!isset($cellsByRow[$cell['row_id']])) {
                $cellsByRow[$cell['row_id']] = [];
            }
            $cellsByRow[$cell['row_id']][$cell['property_id']] = $cell;
        }

        // Build result with cell values
        $result = [];
        foreach ($rows as $row) {
            $rowData = [
                'id' => $row['id'],
                'database_id' => $row['database_id'],
                'linked_note_id' => $row['linked_note_id'],
                'sort_order' => (int) $row['sort_order'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'created_by' => $row['created_by'],
                'created_by_name' => $row['created_by_name'],
                'updated_by' => $row['updated_by'],
                'updated_by_name' => $row['updated_by_name'],
                'cells' => [],
            ];

            // Add cell values
            foreach ($properties as $prop) {
                $cell = $cellsByRow[$row['id']][$prop['id']] ?? null;
                $rowData['cells'][$prop['id']] = $cell
                    ? $this->getCellValue($cell, $prop['type'])
                    : $this->getDefaultValue($prop['type']);
            }

            $result[] = $rowData;
        }

        return $result;
    }

    /**
     * Get default value for a property type
     */
    public function getDefaultValue(string $type): mixed
    {
        return match ($type) {
            'number' => null,
            'checkbox' => false,
            'multi_select', 'person', 'relation' => [],
            'date' => ['start' => null, 'end' => null],
            default => '',
        };
    }

    /**
     * Generate default properties for new database
     */
    public function createDefaultProperties(string $databaseId): void
    {
        // Title property (primary)
        $this->db->insert('note_database_properties', [
            'id' => Uuid::uuid4()->toString(),
            'database_id' => $databaseId,
            'name' => 'Name',
            'type' => 'text',
            'is_primary' => true,
            'is_visible' => true,
            'sort_order' => 0,
            'width' => 300,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Tags property
        $this->db->insert('note_database_properties', [
            'id' => Uuid::uuid4()->toString(),
            'database_id' => $databaseId,
            'name' => 'Tags',
            'type' => 'multi_select',
            'is_primary' => false,
            'is_visible' => true,
            'sort_order' => 1,
            'width' => 200,
            'config' => json_encode(['options' => []]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Status property
        $this->db->insert('note_database_properties', [
            'id' => Uuid::uuid4()->toString(),
            'database_id' => $databaseId,
            'name' => 'Status',
            'type' => 'select',
            'is_primary' => false,
            'is_visible' => true,
            'sort_order' => 2,
            'width' => 150,
            'config' => json_encode([
                'options' => [
                    ['id' => Uuid::uuid4()->toString(), 'name' => 'Offen', 'color' => 'gray'],
                    ['id' => Uuid::uuid4()->toString(), 'name' => 'In Bearbeitung', 'color' => 'blue'],
                    ['id' => Uuid::uuid4()->toString(), 'name' => 'Erledigt', 'color' => 'green'],
                ]
            ]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Duplicate a database
     */
    public function duplicateDatabase(string $databaseId, string $noteId, string $userId): string
    {
        $original = $this->db->fetchAssociative(
            'SELECT * FROM note_databases WHERE id = ?',
            [$databaseId]
        );

        if (!$original) {
            throw new \Exception('Database not found');
        }

        $newDbId = Uuid::uuid4()->toString();

        // Copy database
        $this->db->insert('note_databases', [
            'id' => $newDbId,
            'note_id' => $noteId,
            'user_id' => $userId,
            'name' => $original['name'] . ' (Kopie)',
            'description' => $original['description'],
            'icon' => $original['icon'],
            'default_view' => $original['default_view'],
            'sort_config' => $original['sort_config'],
            'filter_config' => $original['filter_config'],
            'show_title' => $original['show_title'],
            'full_width' => $original['full_width'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Copy properties
        $properties = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_properties WHERE database_id = ?',
            [$databaseId]
        );

        $propertyIdMap = [];
        foreach ($properties as $prop) {
            $newPropId = Uuid::uuid4()->toString();
            $propertyIdMap[$prop['id']] = $newPropId;

            $this->db->insert('note_database_properties', [
                'id' => $newPropId,
                'database_id' => $newDbId,
                'name' => $prop['name'],
                'type' => $prop['type'],
                'config' => $prop['config'],
                'width' => $prop['width'],
                'is_visible' => $prop['is_visible'],
                'is_primary' => $prop['is_primary'],
                'sort_order' => $prop['sort_order'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Copy rows and cells
        $rows = $this->db->fetchAllAssociative(
            'SELECT * FROM note_database_rows WHERE database_id = ?',
            [$databaseId]
        );

        foreach ($rows as $row) {
            $newRowId = Uuid::uuid4()->toString();

            $this->db->insert('note_database_rows', [
                'id' => $newRowId,
                'database_id' => $newDbId,
                'sort_order' => $row['sort_order'],
                'is_archived' => $row['is_archived'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Copy cells
            $cells = $this->db->fetchAllAssociative(
                'SELECT * FROM note_database_cells WHERE row_id = ?',
                [$row['id']]
            );

            foreach ($cells as $cell) {
                if (!isset($propertyIdMap[$cell['property_id']])) {
                    continue;
                }

                $this->db->insert('note_database_cells', [
                    'id' => Uuid::uuid4()->toString(),
                    'row_id' => $newRowId,
                    'property_id' => $propertyIdMap[$cell['property_id']],
                    'value_text' => $cell['value_text'],
                    'value_number' => $cell['value_number'],
                    'value_boolean' => $cell['value_boolean'],
                    'value_date' => $cell['value_date'],
                    'value_date_end' => $cell['value_date_end'],
                    'value_json' => $cell['value_json'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $newDbId;
    }
}
