<?php

declare(strict_types=1);

namespace App\Domain\TronScan\Exceptions;

/**
 * Ответ 2xx, но структура не та, что ожидаем (нет token_transfers и т.п.).
 */
class TronScanResponseException extends TronScanException {}
