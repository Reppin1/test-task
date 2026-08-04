<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | http — реальные запросы к TronScan API (нужен TRONSCAN_API_KEY);
    | fake — офлайн-режим: ответы берутся из фикстуры (демо/разработка без ключа).
    |
    | Биндинг драйвера: App\Providers\TronScanServiceProvider.
    |
    */
    'driver' => env('TRONSCAN_DRIVER', 'http'),

    'base_url' => env('TRONSCAN_BASE_URL', 'https://apilist.tronscanapi.com'),

    'api_key' => env('TRONSCAN_API_KEY'),

    /*
    | Эндпоинт списка TRC-20 трансферов.
    | GET /api/token_trc20/transfers?relatedAddress=...&contract_address=...&limit=&start=
    */
    'transfers_endpoint' => '/api/token_trc20/transfers',

    /*
    | Контракт USDT TRC-20 (mainnet), 6 decimals.
    */
    'usdt_contract' => env('USDT_TRC20_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),

    'usdt_decimals' => (int) env('USDT_TRC20_DECIMALS', 6),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('TRONSCAN_TIMEOUT', 15),
    'connect_timeout' => (int) env('TRONSCAN_CONNECT_TIMEOUT', 5),
    'retry_times' => (int) env('TRONSCAN_RETRY_TIMES', 3),
    'retry_sleep_ms' => (int) env('TRONSCAN_RETRY_SLEEP_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | Пагинация синхронизации
    |--------------------------------------------------------------------------
    |
    | За один прогон забираем максимум page_size * max_pages трансферов.
    |
    */
    'page_size' => (int) env('TRONSCAN_PAGE_SIZE', 50),
    'max_pages' => (int) env('TRONSCAN_MAX_PAGES', 5),

    /*
    | Путь к фикстуре для fake-драйвера (и для тестов).
    */
    'fixture_path' => env(
        'TRONSCAN_FIXTURE_PATH',
        database_path('fixtures/tronscan/token_trc20_transfers.json')
    ),

    /*
    |--------------------------------------------------------------------------
    | Валидация TRON-адреса
    |--------------------------------------------------------------------------
    |
    | strict = true — полная base58check-проверка (префикс 0x41 + sha256 checksum).
    | strict = false — только формат (T + 33 base58-символа).
    |
    */
    'strict_address_checksum' => (bool) env('TRON_STRICT_ADDRESS_CHECKSUM', true),

    /*
    |--------------------------------------------------------------------------
    | Расписание
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        'enabled' => (bool) env('DEPOSITS_SYNC_SCHEDULE_ENABLED', true),
        'cron' => env('DEPOSITS_SYNC_CRON', '*/5 * * * *'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Бизнес-правила
    |--------------------------------------------------------------------------
    |
    | manual_sync_for_inactive_wallets — разрешён ли ручной sync неактивного
    | кошелька из админки/команды (в schedule/batch он не участвует всегда).
    |
    */
    'manual_sync_for_inactive_wallets' => (bool) env('MANUAL_SYNC_FOR_INACTIVE_WALLETS', true),

    'explorer_tx_url' => 'https://tronscan.org/#/transaction/',
    'explorer_address_url' => 'https://tronscan.org/#/address/',
];
