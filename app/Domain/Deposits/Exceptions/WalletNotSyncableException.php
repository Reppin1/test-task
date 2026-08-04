<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Exceptions;

use App\Models\Wallet;
use RuntimeException;

/**
 * Кошелёк нельзя синхронизировать (неактивен, а ручной sync запрещён конфигом).
 */
class WalletNotSyncableException extends RuntimeException
{
    public static function inactive(Wallet $wallet): self
    {
        return new self(sprintf(
            'Wallet #%d (%s) is inactive and manual sync is disabled (MANUAL_SYNC_FOR_INACTIVE_WALLETS=false).',
            $wallet->id,
            $wallet->address,
        ));
    }
}
