<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DepositController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Read-only JSON API
|--------------------------------------------------------------------------
|
| GET /api/deposits?wallet=T...&status=confirmed&client=<uuid>&from=&until=&per_page=
| GET /api/deposits/{tx_hash}
|
| Аутентификации нет намеренно (см. README, раздел «Ограничения»):
| эндпоинт read-only и задуман как демо. Для прода — Sanctum + policy.
|
*/

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/deposits', [DepositController::class, 'index'])->name('api.deposits.index');
    Route::get('/deposits/{txHash}', [DepositController::class, 'show'])->name('api.deposits.show');
});
