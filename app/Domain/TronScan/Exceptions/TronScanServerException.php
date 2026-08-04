<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Exceptions;

use Throwable;

/**
 * 5xx / сетевые сбои TronScan — имеет смысл ретраить.
 */
class TronScanServerException extends TronScanException
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
