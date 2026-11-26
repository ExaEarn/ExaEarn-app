<?php

declare(strict_types=1);

namespace App\Domain\ExaPay\Offline;

use RuntimeException;

final class OfflinePaymentException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $safeMessage,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($safeMessage);
    }
}
