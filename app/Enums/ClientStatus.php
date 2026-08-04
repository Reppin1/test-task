<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ClientStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Blocked = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Blocked => 'Blocked',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Blocked => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-m-check-circle',
            self::Blocked => 'heroicon-m-no-symbol',
        };
    }

    public function isBlocked(): bool
    {
        return $this === self::Blocked;
    }
}
