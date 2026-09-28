<?php

declare(strict_types=1);

namespace Ibochkarev\Msp3DeliverySkeleton\Exception;

use RuntimeException;
use Throwable;

final class DeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}
