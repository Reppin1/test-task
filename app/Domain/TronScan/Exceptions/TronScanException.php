<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Exceptions;

use RuntimeException;

/**
 * Базовое доменное исключение интеграции с TronScan.
 */
class TronScanException extends RuntimeException {}
