<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Расписание
|--------------------------------------------------------------------------
|
| Раз в N минут (config('tronscan.schedule.cron'), по умолчанию каждые 5)
| ставим Job на каждый активный кошелёк — планировщик сам сеть не ждёт.
|
| Запуск: php artisan schedule:work (в docker compose — сервис `scheduler`).
|
*/

if (config('tronscan.schedule.enabled')) {
    Schedule::command('deposits:sync --queue --trigger=schedule')
        ->cron((string) config('tronscan.schedule.cron', '*/5 * * * *'))
        ->withoutOverlapping()
        ->description('Sync incoming USDT TRC-20 deposits from TronScan');
}
