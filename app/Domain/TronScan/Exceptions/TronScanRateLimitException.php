<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Exceptions;

/**
 * HTTP 429 — упёрлись в rate limit (обычно = нет TRONSCAN_API_KEY).
 */
class TronScanRateLimitException extends TronScanServerException {}
