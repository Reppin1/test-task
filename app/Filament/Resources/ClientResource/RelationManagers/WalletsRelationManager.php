<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Enums\SyncTrigger;
use App\Jobs\SyncWalletDepositsJob;
use App\Models\Wallet;
use App\Rules\TronAddressRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WalletsRelationManager extends RelationManager
{
    protected static string $relationship = 'wallets';

    protected static ?string $recordTitleAttribute = 'address';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('address')
                ->label('TRON address')
                ->required()
                ->maxLength(64)
                ->rules([new TronAddressRule])
                ->unique(ignoreRecord: true)
                ->helperText('base58, начинается с «T», 34 символа.'),

            Forms\Components\TextInput::make('label')
                ->maxLength(255),

            Forms\Components\Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->columns([
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->copyable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('label')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('deposits_count')
                    ->counts('deposits')
                    ->label('Deposits')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('never')
                    ->since()
                    ->label('Last sync'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync now')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (Wallet $record): void {
                        SyncWalletDepositsJob::dispatch($record->id, SyncTrigger::Manual);

                        Notification::make()
                            ->title('Sync queued')
                            ->body($record->address)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
