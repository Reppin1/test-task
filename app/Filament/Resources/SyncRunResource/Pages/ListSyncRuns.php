<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncRunResource\Pages;

use App\Filament\Resources\SyncRunResource;
use Filament\Resources\Pages\ListRecords;

class ListSyncRuns extends ListRecords
{
    protected static string $resource = SyncRunResource::class;
}
