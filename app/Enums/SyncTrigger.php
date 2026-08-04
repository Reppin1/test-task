<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SyncTrigger: string implements HasColor, HasLabel
{
    /** Кнопка «Sync now» в Filament. */
    case Manual = 'manual';

    /** Планировщик (schedule). */
    case Schedule = 'schedule';

    /** Артизан-команда deposits:sync. */
    case Command = 'command';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Schedule => 'Schedule',
            self::Command => 'Command',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'primary',
            self::Schedule => 'info',
            self::Command => 'gray',
        };
    }
}
