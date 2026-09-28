<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Api;

final class ApiResponse
{
    /**
     * @param array<string, mixed> $json
     */
    public function __construct(
        private readonly int $status,
        private readonly array $json,
        private readonly ?string $requestId = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return $this->json;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }
}
