<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SyncStatus: string implements HasColor, HasIcon, HasLabel
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Running => 'heroicon-m-arrow-path',
            self::Success => 'heroicon-m-check-circle',
            self::Failed => 'heroicon-m-x-circle',
        };
    }
}
