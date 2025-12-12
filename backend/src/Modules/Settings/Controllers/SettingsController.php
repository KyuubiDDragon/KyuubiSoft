<?php

declare(strict_types=1);

namespace App\Modules\Settings\Controllers;

use App\Core\Http\JsonResponse;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SettingsController
{
    public function __construct(
        private readonly Connection $db
    ) {}

    public function getUserSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        $settings = $this->db->fetchAllAssociative(
            'SELECT `key`, `value` FROM user_settings WHERE user_id = ?',
            [$userId]
        );

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['key']] = json_decode($setting['value'], true);
        }

        // Add defaults for missing settings
        $defaults = [
            'theme' => 'dark',
            'language' => 'de',
            'notifications_enabled' => true,
            'sidebar_collapsed' => false,
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($result[$key])) {
                $result[$key] = $value;
            }
        }

        return JsonResponse::success($result);
    }

    public function updateUserSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        foreach ($data as $key => $value) {
            $exists = $this->db->fetchOne(
                'SELECT 1 FROM user_settings WHERE user_id = ? AND `key` = ?',
                [$userId, $key]
            );

            $jsonValue = json_encode($value);

            if ($exists) {
                $this->db->executeStatement(
                    'UPDATE user_settings SET `value` = ?, updated_at = ? WHERE user_id = ? AND `key` = ?',
                    [$jsonValue, date('Y-m-d H:i:s'), $userId, $key]
                );
            } else {
                $this->db->executeStatement(
                    'INSERT INTO user_settings (user_id, `key`, `value`, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                    [$userId, $key, $jsonValue, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
                );
            }
        }

        return JsonResponse::success(null, 'Settings updated successfully');
    }

    public function getSystemSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $settings = $this->db->fetchAllAssociative(
            'SELECT `key`, `value`, `type`, description, is_public FROM system_settings'
        );

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['key']] = [
                'value' => json_decode($setting['value'], true),
                'type' => $setting['type'],
                'description' => $setting['description'],
                'is_public' => (bool) $setting['is_public'],
            ];
        }

        return JsonResponse::success($result);
    }

    public function updateSystemSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        foreach ($data as $key => $value) {
            $exists = $this->db->fetchOne(
                'SELECT 1 FROM system_settings WHERE `key` = ?',
                [$key]
            );

            $jsonValue = json_encode($value);

            if ($exists) {
                $this->db->executeStatement(
                    'UPDATE system_settings SET `value` = ?, updated_at = ?, updated_by = ? WHERE `key` = ?',
                    [$jsonValue, date('Y-m-d H:i:s'), $userId, $key]
                );
            } else {
                $this->db->executeStatement(
                    'INSERT INTO system_settings (`key`, `value`, `type`, updated_at, updated_by) VALUES (?, ?, ?, ?, ?)',
                    [$key, $jsonValue, 'string', date('Y-m-d H:i:s'), $userId]
                );
            }
        }

        return JsonResponse::success(null, 'System settings updated successfully');
    }
}
