<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Core\Security\RbacManager;

/**
 * Feature Service - Manages feature flags for multi-instance deployments
 *
 * Two-layer access control:
 * 1. Instance-Level (ENV): What features exist on this instance?
 * 2. User-Level (Permissions): What can this user access within instance limits?
 *
 * Each feature can have different modes:
 * - disabled: Feature completely disabled on this instance
 * - portainer_only: (Docker) Only Portainer connections allowed
 * - own: Limited access (e.g., Docker: only own hosts, Server: only SSH connections)
 * - limited: Partial access with restrictions (e.g., Tools: no SSH terminal)
 * - full: Full access to feature
 */
class FeatureService
{
    // Feature definitions with their possible modes
    private const FEATURES = [
        'docker' => [
            'modes' => ['disabled', 'portainer_only', 'own', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_DOCKER',
            'description' => 'Docker container management',
            'subFeatures' => [
                'view' => ['portainer_only', 'own', 'full'],
                'hosts_manage' => ['own', 'full'],
                'containers' => ['own', 'full'],
                'images' => ['own', 'full'],
                'volumes' => ['own', 'full'],
                'networks' => ['own', 'full'],
                'system_socket' => ['full'],
                'portainer' => ['portainer_only', 'own', 'full'],
            ],
        ],
        'server' => [
            'modes' => ['disabled', 'own', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_SERVER',
            'description' => 'Server monitoring and management',
            'subFeatures' => [
                'view' => ['own', 'full'],
                'manage' => ['own', 'full'],
                'terminal' => ['own', 'full'],
                'localhost' => ['full'],
            ],
        ],
        'tools' => [
            'modes' => ['disabled', 'limited', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_TOOLS',
            'description' => 'Network tools (ping, DNS, SSH, etc.)',
            'subFeatures' => [
                'ping' => ['limited', 'full'],
                'dns' => ['limited', 'full'],
                'whois' => ['limited', 'full'],
                'traceroute' => ['limited', 'full'],
                'ssl_check' => ['limited', 'full'],
                'http_headers' => ['limited', 'full'],
                'ip_lookup' => ['limited', 'full'],
                'security_headers' => ['limited', 'full'],
                'open_graph' => ['limited', 'full'],
                'port_check' => ['full'],
                'ssh' => ['full'],
            ],
        ],
        'uptime' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_UPTIME',
            'description' => 'Uptime monitoring',
            'subFeatures' => [
                'view' => ['full'],
                'manage' => ['full'],
            ],
        ],
        'invoices' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_INVOICES',
            'description' => 'Invoice management',
            'subFeatures' => [
                'view' => ['full'],
                'create' => ['full'],
                'edit' => ['full'],
                'delete' => ['full'],
            ],
        ],
        'tickets' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_TICKETS',
            'description' => 'Ticket system',
            'subFeatures' => [
                'view' => ['full'],
                'create' => ['full'],
                'manage' => ['full'],
            ],
        ],
        'api_tester' => [
            'modes' => ['disabled', 'limited', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_API_TESTER',
            'description' => 'API testing tool',
            'subFeatures' => [
                'view' => ['limited', 'full'],
                'execute' => ['limited', 'full'],
                'auth_headers' => ['full'],
            ],
        ],
        'youtube' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_YOUTUBE',
            'description' => 'YouTube downloader',
            'subFeatures' => [
                'use' => ['full'],
            ],
        ],
        'passwords' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_PASSWORDS',
            'description' => 'Password manager',
            'subFeatures' => [
                'view' => ['full'],
                'manage' => ['full'],
            ],
        ],
        'git' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_GIT',
            'description' => 'Git repository dashboard (GitHub/GitLab)',
            'subFeatures' => [
                'view' => ['full'],
                'manage' => ['full'],
                'sync' => ['full'],
            ],
        ],
        'ssl' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_SSL',
            'description' => 'SSL certificate monitoring',
            'subFeatures' => [
                'view' => ['full'],
                'manage' => ['full'],
                'check' => ['full'],
            ],
        ],
        'service_health' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_SERVICE_HEALTH',
            'description' => 'Unified service health dashboard',
            'subFeatures' => [
                'view' => ['full'],
            ],
        ],
        'galleries' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_GALLERIES',
            'description' => 'Public link galleries',
            'subFeatures' => [
                'view' => ['full'],
                'manage' => ['full'],
                'public' => ['full'],
            ],
        ],
    ];

    private array $featureCache = [];
    private ?RbacManager $rbac = null;

    public function __construct(?RbacManager $rbac = null)
    {
        $this->rbac = $rbac;
        $this->loadFeatures();
    }

    /**
     * Set the RBAC manager (for dependency injection)
     */
    public function setRbacManager(RbacManager $rbac): void
    {
        $this->rbac = $rbac;
    }

    /**
     * Load all features from environment
     */
    private function loadFeatures(): void
    {
        foreach (self::FEATURES as $feature => $config) {
            $envValue = $_ENV[$config['env']] ?? null;

            if ($envValue === null) {
                $this->featureCache[$feature] = $config['default'];
            } elseif ($envValue === 'false' || $envValue === '0' || $envValue === 'disabled') {
                $this->featureCache[$feature] = 'disabled';
            } elseif ($envValue === 'true' || $envValue === '1' || $envValue === 'full') {
                $this->featureCache[$feature] = 'full';
            } else {
                // Custom mode (e.g., 'own', 'limited', 'portainer_only')
                $this->featureCache[$feature] = in_array($envValue, $config['modes'])
                    ? $envValue
                    : $config['default'];
            }
        }
    }

    /**
     * Get the current mode of a feature (Instance-Level)
     */
    public function getMode(string $feature): string
    {
        return $this->featureCache[$feature] ?? 'disabled';
    }

    /**
     * Check if a feature is enabled on this instance (any mode except disabled)
     */
    public function isEnabled(string $feature): bool
    {
        return $this->getMode($feature) !== 'disabled';
    }

    /**
     * Check if a feature has a specific mode
     */
    public function hasMode(string $feature, string $mode): bool
    {
        return $this->getMode($feature) === $mode;
    }

    /**
     * Check if feature has full access on instance level
     */
    public function hasFull(string $feature): bool
    {
        return $this->hasMode($feature, 'full');
    }

    /**
     * Check if a sub-feature is allowed by the current instance mode
     */
    public function isSubFeatureAllowed(string $feature, string $subFeature): bool
    {
        $mode = $this->getMode($feature);

        if ($mode === 'disabled') {
            return false;
        }

        $config = self::FEATURES[$feature] ?? null;
        if (!$config || !isset($config['subFeatures'][$subFeature])) {
            // Unknown sub-feature, default to requiring full mode
            return $mode === 'full';
        }

        return in_array($mode, $config['subFeatures'][$subFeature]);
    }

    /**
     * Check specific sub-feature access (Instance-Level only)
     * Use canAccess() for combined Instance + Permission check
     */
    public function checkAccess(string $feature, string $subFeature): bool
    {
        return $this->isSubFeatureAllowed($feature, $subFeature);
    }

    /**
     * Combined access check: Instance-Level AND User Permission
     *
     * @param string $feature The feature name (e.g., 'docker')
     * @param string $userId The user ID to check permissions for
     * @param string|null $subFeature Optional sub-feature (e.g., 'system_socket')
     * @return bool
     */
    public function canAccess(string $feature, string $userId, ?string $subFeature = null): bool
    {
        // 1. Instance-Level Check
        if (!$this->isEnabled($feature)) {
            return false;
        }

        // 2. Sub-Feature Mode Check (if specified)
        if ($subFeature !== null && !$this->isSubFeatureAllowed($feature, $subFeature)) {
            return false;
        }

        // 3. Permission Check (if RBAC manager is available)
        if ($this->rbac !== null) {
            $permission = $subFeature
                ? "{$feature}.{$subFeature}"
                : "{$feature}.view";

            if (!$this->rbac->hasPermission($userId, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all features with their current modes (for API response)
     */
    public function getAllFeatures(): array
    {
        $result = [];

        foreach (self::FEATURES as $feature => $config) {
            $currentMode = $this->featureCache[$feature] ?? 'disabled';

            // Calculate restricted sub-features based on current mode
            $restricted = [];
            if (isset($config['subFeatures'])) {
                foreach ($config['subFeatures'] as $subFeature => $allowedModes) {
                    if (!in_array($currentMode, $allowedModes)) {
                        $restricted[] = $subFeature;
                    }
                }
            }

            $result[$feature] = [
                'enabled' => $currentMode !== 'disabled',
                'mode' => $currentMode,
                'description' => $config['description'],
                'availableModes' => $config['modes'],
                'restricted' => $restricted,
            ];
        }

        return $result;
    }

    /**
     * Get only enabled features (simplified for frontend navigation)
     */
    public function getEnabledFeatures(): array
    {
        $result = [];

        foreach ($this->featureCache as $feature => $mode) {
            if ($mode !== 'disabled') {
                $result[$feature] = $mode;
            }
        }

        return $result;
    }

    /**
     * Get the permission name for a feature/sub-feature
     */
    public function getPermissionName(string $feature, ?string $subFeature = null): string
    {
        return $subFeature ? "{$feature}.{$subFeature}" : "{$feature}.view";
    }

    /**
     * Get all required permissions for a feature based on current instance mode
     */
    public function getAvailablePermissions(string $feature): array
    {
        $config = self::FEATURES[$feature] ?? null;
        if (!$config || !isset($config['subFeatures'])) {
            return [];
        }

        $mode = $this->getMode($feature);
        $permissions = [];

        foreach ($config['subFeatures'] as $subFeature => $allowedModes) {
            if (in_array($mode, $allowedModes)) {
                $permissions[] = "{$feature}.{$subFeature}";
            }
        }

        return $permissions;
    }
}
