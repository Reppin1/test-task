<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\SyncRunResource;
use App\Models\SyncRun;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestSyncRuns extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Последние прогоны синхронизации';

    public function table(Table $table): Table
    {
        return $table
            ->query(SyncRun::query()->with('wallet')->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->dateTime('Y-m-d H:i:s')->label('Started'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('trigger')->badge(),
                Tables\Columns\TextColumn::make('wallet.address')->label('Wallet')->limit(18)->placeholder('all active (batch)'),
                Tables\Columns\TextColumn::make('fetched_count')->label('Fetched')->alignCenter(),
                Tables\Columns\TextColumn::make('created_count')->label('Created')->alignCenter(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Открыть')
                    ->icon('heroicon-m-eye')
                    ->url(fn (SyncRun $record): string => SyncRunResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->poll('30s');
    }
}
