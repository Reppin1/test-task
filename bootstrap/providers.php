<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\TronScanServiceProvider;

return [
    AppServiceProvider::class,
    TronScanServiceProvider::class,
    AdminPanelProvider::class,
];
