<?php

declare(strict_types=1);

namespace App\Modules\Export\Controllers;

use App\Core\Http\JsonResponse;
use App\Modules\Export\Services\ExportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExportController
{
    public function __construct(
        private ExportService $exportService
    ) {}

    /**
     * Get export statistics
     */
    public function getStats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $stats = $this->exportService->getExportStats($userId);

        return JsonResponse::success($stats, 'Export statistics retrieved');
    }

    /**
     * Export data
     */
    public function export(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        $types = $body['types'] ?? ['lists', 'documents', 'snippets', 'bookmarks'];
        $format = $body['format'] ?? 'json';

        $exportData = $this->exportService->exportData($userId, $types, $format);

        if ($format === 'json') {
            $filename = 'kyuubisoft-export-' . date('Y-m-d-His') . '.json';

            return JsonResponse::create($exportData, 200, [
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // CSV format - export each type as separate CSV in a zip
        if ($format === 'csv') {
            $filename = 'kyuubisoft-export-' . date('Y-m-d-His') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $filename;

            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            foreach ($exportData['data'] as $type => $data) {
                $csv = $this->convertToCsv($type, $data);
                $zip->addFromString($type . '.csv', $csv);
            }

            $zip->close();

            $zipContent = file_get_contents($zipPath);
            unlink($zipPath);

            $response->getBody()->write($zipContent);

            return $response
                ->withHeader('Content-Type', 'application/zip')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        return JsonResponse::error('Invalid format', 400);
    }

    /**
     * Validate import data
     */
    public function validateImport(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $data = $body['data'] ?? [];

        $validation = $this->exportService->validateImportData($data);

        return JsonResponse::success($validation, 'Validation completed');
    }

    /**
     * Import data
     */
    public function import(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();

        $data = $body['data'] ?? [];
        $options = [
            'conflict_resolution' => $body['conflict_resolution'] ?? 'skip',
            'types' => $body['types'] ?? null,
        ];

        // Validate first
        $validation = $this->exportService->validateImportData($data);
        if (!$validation['valid']) {
            return JsonResponse::validationError($validation['errors'], 'Invalid import data');
        }

        $result = $this->exportService->importData($userId, $data, $options);

        if (!$result['success']) {
            return JsonResponse::serverError('Import failed');
        }

        return JsonResponse::success($result, 'Import completed');
    }

    /**
     * Convert data to CSV format
     */
    private function convertToCsv(string $type, array $data): string
    {
        if (empty($data)) {
            return '';
        }

        // Handle nested structures
        $flatData = $this->flattenData($type, $data);

        if (empty($flatData)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Get headers from first item
        $headers = array_keys($flatData[0]);
        fputcsv($output, $headers);

        foreach ($flatData as $row) {
            $rowData = [];
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $rowData[] = $value;
            }
            fputcsv($output, $rowData);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Flatten nested data structures
     */
    private function flattenData(string $type, array $data): array
    {
        $flat = [];

        // Handle types with nested data
        if (in_array($type, ['documents', 'bookmarks', 'passwords']) && isset($data['documents'])) {
            $data = $data['documents'] ?? $data['bookmarks'] ?? $data['passwords'] ?? $data;
        }

        foreach ($data as $item) {
            // Remove nested arrays for CSV
            $flatItem = [];
            foreach ($item as $key => $value) {
                if (!is_array($value)) {
                    $flatItem[$key] = $value;
                } elseif ($key === 'items' || $key === 'columns' || $key === 'tasks') {
                    // Count nested items
                    $flatItem[$key . '_count'] = count($value);
                } else {
                    $flatItem[$key] = json_encode($value);
                }
            }
            $flat[] = $flatItem;
        }

        return $flat;
    }
}
