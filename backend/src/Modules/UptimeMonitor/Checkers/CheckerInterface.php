<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

interface CheckerInterface
{
    /**
     * Perform a check on the monitor
     *
     * @param array $monitor The monitor configuration
     * @return CheckResult The result of the check
     */
    public function check(array $monitor): CheckResult;

    /**
     * Get the supported monitor type(s)
     *
     * @return array List of supported types
     */
    public static function getSupportedTypes(): array;
}
