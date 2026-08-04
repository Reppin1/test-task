<?php

declare(strict_types=1);

namespace App\Filament\Resources\WalletResource\RelationManagers;

use App\Enums\SyncStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SyncRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'syncRuns';

    protected static ?string $title = 'Sync runs';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->dateTime('Y-m-d H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('trigger')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('fetched_count')->label('Fetched')->alignCenter(),
                Tables\Columns\TextColumn::make('created_count')->label('Created')->alignCenter(),
                Tables\Columns\TextColumn::make('error_message')->limit(40)->placeholder('—')->tooltip(fn (?string $state): ?string => $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(SyncStatus::class),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25]);
    }
}
