<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DepositStatus: string implements HasColor, HasIcon, HasLabel
{
    /** Транзакция ещё не подтверждена сетью (contract_ret != SUCCESS или confirmed = false). */
    case Pending = 'pending';

    /** Подтверждённый входящий перевод. */
    case Confirmed = 'confirmed';

    /** Служебная/нулевая сумма — в балансе не учитываем. */
    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Ignored => 'Ignored',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Ignored => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Confirmed => 'heroicon-m-check-badge',
            self::Ignored => 'heroicon-m-minus-circle',
        };
    }
}
