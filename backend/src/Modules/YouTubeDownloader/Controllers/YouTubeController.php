<?php

declare(strict_types=1);

namespace App\Modules\YouTubeDownloader\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class YouTubeController
{
    private string $downloadPath;

    public function __construct()
    {
        $this->downloadPath = '/var/www/html/storage/downloads';
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0755, true);
        }
    }

    /**
     * Get video information without downloading
     */
    public function getInfo(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $url = $body['url'] ?? '';

        if (empty($url)) {
            return $this->jsonResponse($response, ['error' => 'URL ist erforderlich'], 400);
        }

        if (!$this->isValidYouTubeUrl($url)) {
            return $this->jsonResponse($response, ['error' => 'Ungültige YouTube URL'], 400);
        }

        $command = sprintf(
            'yt-dlp --dump-json --no-download %s 2>&1',
            escapeshellarg($url)
        );

        $output = shell_exec($command);

        if (!$output) {
            return $this->jsonResponse($response, ['error' => 'Video konnte nicht gefunden werden'], 404);
        }

        // yt-dlp might output warnings/errors before JSON, try to find the JSON object
        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $output = substr($output, $jsonStart);
        }

        $info = json_decode($output, true);

        if (!$info || json_last_error() !== JSON_ERROR_NONE) {
            return $this->jsonResponse($response, [
                'error' => 'Video-Informationen konnten nicht geladen werden',
                'debug' => substr($output, 0, 500)
            ], 500);
        }

        // Extract relevant formats
        $formats = [];
        if (isset($info['formats'])) {
            foreach ($info['formats'] as $format) {
                if (isset($format['format_id'], $format['ext'])) {
                    $formats[] = [
                        'format_id' => $format['format_id'],
                        'ext' => $format['ext'],
                        'resolution' => $format['resolution'] ?? 'audio only',
                        'filesize' => $format['filesize'] ?? $format['filesize_approx'] ?? null,
                        'vcodec' => $format['vcodec'] ?? 'none',
                        'acodec' => $format['acodec'] ?? 'none',
                        'quality' => $format['quality'] ?? 0,
                    ];
                }
            }
        }

        return $this->jsonResponse($response, [
            'data' => [
                'id' => $info['id'] ?? '',
                'title' => $info['title'] ?? '',
                'description' => substr($info['description'] ?? '', 0, 500),
                'duration' => $info['duration'] ?? 0,
                'duration_string' => $info['duration_string'] ?? '',
                'thumbnail' => $info['thumbnail'] ?? '',
                'uploader' => $info['uploader'] ?? '',
                'view_count' => $info['view_count'] ?? 0,
                'upload_date' => $info['upload_date'] ?? '',
                'formats' => $formats,
            ]
        ]);
    }

    /**
     * Download video/audio
     */
    public function download(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $url = $body['url'] ?? '';
        $format = $body['format'] ?? 'best';
        $audioOnly = $body['audio_only'] ?? false;

        if (empty($url)) {
            return $this->jsonResponse($response, ['error' => 'URL ist erforderlich'], 400);
        }

        if (!$this->isValidYouTubeUrl($url)) {
            return $this->jsonResponse($response, ['error' => 'Ungültige YouTube URL'], 400);
        }

        // Generate unique filename
        $fileId = uniqid('yt_', true);
        $outputTemplate = $this->downloadPath . '/' . $fileId . '.%(ext)s';

        // Build command
        if ($audioOnly) {
            $command = sprintf(
                'yt-dlp -x --audio-format mp3 --audio-quality 0 -o %s %s 2>&1',
                escapeshellarg($outputTemplate),
                escapeshellarg($url)
            );
        } else {
            $formatArg = $format === 'best' ? '-f "bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best"' : '-f ' . escapeshellarg($format);
            $command = sprintf(
                'yt-dlp %s --merge-output-format mp4 -o %s %s 2>&1',
                $formatArg,
                escapeshellarg($outputTemplate),
                escapeshellarg($url)
            );
        }

        $output = shell_exec($command);

        // Find the downloaded file
        $files = glob($this->downloadPath . '/' . $fileId . '.*');

        if (empty($files)) {
            return $this->jsonResponse($response, [
                'error' => 'Download fehlgeschlagen',
                'details' => $output
            ], 500);
        }

        $downloadedFile = $files[0];
        $filename = basename($downloadedFile);

        return $this->jsonResponse($response, [
            'data' => [
                'file_id' => $fileId,
                'filename' => $filename,
                'download_url' => '/api/v1/youtube/file/' . $filename,
                'size' => filesize($downloadedFile),
            ]
        ]);
    }

    /**
     * Serve downloaded file
     */
    public function serveFile(Request $request, Response $response, array $args): Response
    {
        $filename = $args['filename'] ?? '';

        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filepath = $this->downloadPath . '/' . $filename;

        if (!file_exists($filepath)) {
            return $this->jsonResponse($response, ['error' => 'Datei nicht gefunden'], 404);
        }

        // Get mime type and file size
        $mimeType = mime_content_type($filepath) ?: 'application/octet-stream';
        $fileSize = filesize($filepath);

        // Create a stream from the file
        $stream = new \Slim\Psr7\Stream(fopen($filepath, 'rb'));

        // Schedule file deletion after request completes
        register_shutdown_function(function () use ($filepath) {
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        });

        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) $fileSize)
            ->withHeader('Cache-Control', 'no-cache, must-revalidate')
            ->withBody($stream);
    }

    /**
     * Clean up old downloads
     */
    public function cleanup(Request $request, Response $response): Response
    {
        $files = glob($this->downloadPath . '/yt_*');
        $deleted = 0;
        $threshold = time() - 3600; // 1 hour old

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
                $deleted++;
            }
        }

        return $this->jsonResponse($response, [
            'data' => [
                'deleted' => $deleted,
                'message' => "$deleted alte Dateien gelöscht"
            ]
        ]);
    }

    /**
     * Validate YouTube URL
     */
    private function isValidYouTubeUrl(string $url): bool
    {
        $patterns = [
            '/^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/',
            '/^https?:\/\/youtu\.be\/[\w-]+/',
            '/^https?:\/\/(www\.)?youtube\.com\/shorts\/[\w-]+/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * JSON response helper
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
