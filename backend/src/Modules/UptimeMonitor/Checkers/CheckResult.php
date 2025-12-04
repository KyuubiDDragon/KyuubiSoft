<?php

declare(strict_types=1);

namespace App\Modules\UptimeMonitor\Checkers;

class CheckResult
{
    public function __construct(
        public readonly string $status, // 'up' or 'down'
        public readonly ?int $responseTime = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $data = null // Extended data (players, version, etc.)
    ) {}

    public function isUp(): bool
    {
        return $this->status === 'up';
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'response_time' => $this->responseTime,
            'status_code' => $this->statusCode,
            'error_message' => $this->errorMessage,
            'data' => $this->data,
        ];
    }
}
