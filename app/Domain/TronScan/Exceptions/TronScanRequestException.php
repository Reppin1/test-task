<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Exceptions;

use Throwable;

/**
 * 4xx от TronScan (кроме 429): неверный адрес, отсутствующий/битый API-ключ и т.п.
 */
class TronScanRequestException extends TronScanException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
