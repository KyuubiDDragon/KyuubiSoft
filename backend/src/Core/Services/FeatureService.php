<?php

declare(strict_types=1);

namespace App\Core\Services;

/**
 * Feature Service - Manages feature flags for multi-instance deployments
 *
 * Each feature can have different modes:
 * - false/disabled: Feature completely disabled
 * - true/full: Full access to feature
 * - "own": Limited access (e.g., Docker: only own hosts, not system socket)
 * - "limited": Partial access with restrictions
 */
class FeatureService
{
    // Feature definitions with their possible modes
    private const FEATURES = [
        'docker' => [
            'modes' => ['disabled', 'own', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_DOCKER',
            'description' => 'Docker container management',
            'restrictions' => [
                'disabled' => 'Docker module completely hidden',
                'own' => 'Can add own Docker hosts, no access to system Docker socket',
                'full' => 'Full Docker access including system socket',
            ],
        ],
        'server' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_SERVER',
            'description' => 'Server monitoring and management',
            'restrictions' => [
                'disabled' => 'Server module completely hidden',
                'full' => 'Full server access',
            ],
        ],
        'tools' => [
            'modes' => ['disabled', 'limited', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_TOOLS',
            'description' => 'Network tools (ping, DNS, SSH, etc.)',
            'restrictions' => [
                'disabled' => 'Tools module completely hidden',
                'limited' => 'Only safe tools (ping, DNS lookup), no SSH',
                'full' => 'All tools including SSH',
            ],
        ],
        'uptime' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_UPTIME',
            'description' => 'Uptime monitoring',
        ],
        'invoices' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_INVOICES',
            'description' => 'Invoice management',
        ],
        'tickets' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_TICKETS',
            'description' => 'Ticket system',
        ],
        'api_tester' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_API_TESTER',
            'description' => 'API testing tool',
        ],
        'youtube' => [
            'modes' => ['disabled', 'full'],
            'default' => 'full',
            'env' => 'FEATURE_YOUTUBE',
            'description' => 'YouTube downloader',
        ],
    ];

    private array $featureCache = [];

    public function __construct()
    {
        $this->loadFeatures();
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
                // Custom mode (e.g., 'own', 'limited')
                $this->featureCache[$feature] = in_array($envValue, $config['modes'])
                    ? $envValue
                    : $config['default'];
            }
        }
    }

    /**
     * Check if a feature is enabled (any mode except disabled)
     */
    public function isEnabled(string $feature): bool
    {
        return ($this->featureCache[$feature] ?? 'disabled') !== 'disabled';
    }

    /**
     * Check if a feature has a specific mode
     */
    public function hasMode(string $feature, string $mode): bool
    {
        return ($this->featureCache[$feature] ?? 'disabled') === $mode;
    }

    /**
     * Check if feature has full access
     */
    public function hasFull(string $feature): bool
    {
        return $this->hasMode($feature, 'full');
    }

    /**
     * Get the current mode of a feature
     */
    public function getMode(string $feature): string
    {
        return $this->featureCache[$feature] ?? 'disabled';
    }

    /**
     * Get all features with their current modes (for API response)
     */
    public function getAllFeatures(): array
    {
        $result = [];

        foreach (self::FEATURES as $feature => $config) {
            $currentMode = $this->featureCache[$feature] ?? 'disabled';
            $result[$feature] = [
                'enabled' => $currentMode !== 'disabled',
                'mode' => $currentMode,
                'description' => $config['description'],
                'availableModes' => $config['modes'],
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
     * Check specific sub-feature access
     * e.g., checkAccess('docker', 'system_socket') checks if docker has full mode
     */
    public function checkAccess(string $feature, string $subFeature): bool
    {
        $mode = $this->getMode($feature);

        if ($mode === 'disabled') {
            return false;
        }

        if ($mode === 'full') {
            return true;
        }

        // Feature-specific sub-feature checks
        return match ($feature) {
            'docker' => match ($subFeature) {
                'system_socket' => $mode === 'full',
                'own_hosts' => in_array($mode, ['own', 'full']),
                'view' => in_array($mode, ['own', 'full']),
                default => false,
            },
            'tools' => match ($subFeature) {
                'ssh' => $mode === 'full',
                'ping' => in_array($mode, ['limited', 'full']),
                'dns' => in_array($mode, ['limited', 'full']),
                'whois' => in_array($mode, ['limited', 'full']),
                'port_check' => $mode === 'full',
                default => false,
            },
            default => $mode === 'full',
        };
    }
}
