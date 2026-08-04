<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepositResource\Pages;

use App\Enums\DepositStatus;
use App\Filament\Resources\DepositResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDeposits extends ListRecords
{
    protected static string $resource = DepositResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'confirmed' => Tab::make('Confirmed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DepositStatus::Confirmed))
                ->badgeColor('success'),

            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DepositStatus::Pending))
                ->badgeColor('warning'),

            'ignored' => Tab::make('Ignored')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DepositStatus::Ignored))
                ->badgeColor('gray'),
        ];
    }
}
