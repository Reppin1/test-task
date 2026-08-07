# Карта проекта: где что лежит

Документ отвечает на вопрос «куда смотреть, чтобы понять/поправить X».
Запуск и демо описаны в [README.md](README.md).

---

## 1. Слои и поток данных

```
Filament Resource ─┐
Artisan-команда   ─┼─→ SyncWalletDepositsJob ─→ DepositSyncService ─→ TronScanClient (interface)
Schedule          ─┘                                    │                      ├─ TronScanHttpClient  (реальный API)
                                                        │                      └─ FakeTronScanClient  (JSON-фикстура)
                                                        ↓
                                            deposits / sync_runs / wallets.last_synced_at
```

Правило: **вся логика синхронизации живёт в `DepositSyncService`**. Filament-ресурсы, команда и Job —
тонкие адаптеры, которые только выбирают кошельки и вызывают сервис.

---

## 2. Дерево (только то, что написано руками)

```
app/
├── Console/Commands/
│   └── SyncDepositsCommand.php          php artisan deposits:sync
├── Domain/
│   ├── Deposits/
│   │   ├── DepositSyncService.php       ★ ядро: тянет трансферы, пишет депозиты и sync_runs
│   │   ├── SyncResult.php               DTO итога прогона (fetched/created/ignored/skipped)
│   │   └── Exceptions/
│   │       └── WalletNotSyncableException.php
│   └── TronScan/
│       ├── Contracts/TronScanClient.php ★ контракт клиента (bind в TronScanServiceProvider)
│       ├── TronScanHttpClient.php       реальный HTTP: таймауты, retry, маппинг ошибок
│       ├── FakeTronScanClient.php       офлайн-драйвер поверх JSON-фикстуры
│       ├── DTO/
│       │   ├── Trc20Transfer.php        один трансфер: парсинг ответа TronScan
│       │   └── TransferPage.php         страница + total (пагинация)
│       ├── Support/
│       │   ├── TronAddress.php          base58check: валидация, decode/encode, hex → base58
│       │   └── TokenAmount.php          quant + decimals → десятичная строка (без float)
│       └── Exceptions/                  TronScanException и 4 наследника (429 / 4xx / 5xx / битый ответ)
├── Enums/
│   ├── ClientStatus.php                 active | blocked
│   ├── DepositStatus.php                pending | confirmed | ignored
│   ├── SyncStatus.php                   running | success | failed
│   └── SyncTrigger.php                  manual | schedule | command
├── Filament/
│   ├── Resources/
│   │   ├── ClientResource.php           + Pages/{List,Create,Edit,View} + RelationManagers/{Wallets,Deposits}
│   │   ├── WalletResource.php           + Pages/{List,Create,Edit,View} + RelationManagers/{Deposits,SyncRuns}
│   │   ├── DepositResource.php          read-only + Pages/{ListDeposits,ViewDeposit}
│   │   └── SyncRunResource.php          read-only + Pages/{ListSyncRuns,ViewSyncRun}
│   └── Widgets/
│       ├── DepositsOverview.php         4 плитки статистики на дашборде
│       └── LatestSyncRuns.php           таблица последних прогонов
├── Http/
│   ├── Controllers/Api/DepositController.php   GET /api/deposits, GET /api/deposits/{tx}
│   └── Resources/DepositJsonResource.php       сериализация депозита (amount — строкой)
├── Jobs/SyncWalletDepositsJob.php       очередь: tries=3, backoff, ShouldBeUnique
├── Models/{Client,Wallet,Deposit,SyncRun,User}.php
├── Providers/
│   ├── TronScanServiceProvider.php      ★ выбор драйвера по config('tronscan.driver')
│   └── Filament/AdminPanelProvider.php  панель /admin: бренд, цвета, группы навигации, виджеты
└── Rules/TronAddressRule.php            правило валидации TRON-адреса для форм

config/tronscan.php                      ★ единственный конфиг проекта: драйвер, ключ, контракт,
                                           таймауты, ретраи, пагинация, расписание, бизнес-флаги

database/
├── migrations/2026_08_04_0001..0004_*   clients → wallets → deposits → sync_runs
├── factories/{Client,Wallet,Deposit}Factory.php
├── seeders/
│   ├── DatabaseSeeder.php               вызывает два сидера ниже
│   ├── AdminUserSeeder.php              админ Filament из ADMIN_EMAIL/ADMIN_PASSWORD
│   └── DemoDeskSeeder.php               2 клиента (один blocked) + 3 кошелька
└── fixtures/tronscan/token_trc20_transfers.json   ★ фикстура: 5 трансферов, общая для fake-драйвера и тестов

routes/
├── web.php                              / → редирект на /admin
├── api.php                              read-only JSON API + throttle:60,1
└── console.php                          расписание: deposits:sync --queue каждые 5 минут

docker/php/
├── Dockerfile                           php:8.3-cli-alpine + pdo_mysql/bcmath/intl/zip/opcache/pcntl + composer
└── entrypoint.sh                        .env, composer install, APP_KEY, ожидание MySQL, migrate, seed

tests/
├── Concerns/InteractsWithTronScan.php   Http::fake на фикстуре + preventStrayRequests
├── Unit/{TronAddressTest,TokenAmountTest}.php
└── Feature/{DepositSyncServiceTest,SyncDepositsCommandTest,FakeTronScanDriverTest,
             DepositsApiTest,FilamentAdminTest,ExampleTest}.php   — 58 тестов, сеть замокана

docker-compose.yml                       app + queue + scheduler + mysql + redis
pint.json / phpstan.neon                 стиль (laravel preset + strict_types) и Larastan level 5
```

★ — файлы, с которых стоит начинать чтение.

---

## 3. «Хочу поправить X — где это?»

| Задача | Файл |
|--------|------|
| Сменить эндпоинт / параметры запроса к TronScan | `config/tronscan.php` (`transfers_endpoint`, `page_size`) и `app/Domain/TronScan/TronScanHttpClient.php` |
| Изменить маппинг полей ответа в депозит | `app/Domain/TronScan/DTO/Trc20Transfer.php` (парсинг) и `DepositSyncService::store()` (запись) |
| Поменять правило статуса депозита (`pending`/`confirmed`/`ignored`) | `DepositSyncService::resolveStatus()` |
| Изменить поведение при `amount <= 0` | там же, `resolveStatus()` + описание в README §6 |
| Добавить/убрать ретраи, таймауты | `config/tronscan.php` → `TronScanHttpClient::request()` |
| Другой формат ошибок TronScan | `TronScanHttpClient::mapStatus()` + классы в `app/Domain/TronScan/Exceptions/` |
| Отключить сеть для демо | `.env`: `TRONSCAN_DRIVER=fake` (реализация — `FakeTronScanClient`) |
| Поменять демо-данные фикстуры | `database/fixtures/tronscan/token_trc20_transfers.json` (используется и в тестах!) |
| Частота расписания | `.env` `DEPOSITS_SYNC_CRON` → `routes/console.php` |
| Настройки очереди (`tries`, `backoff`, уникальность) | `app/Jobs/SyncWalletDepositsJob.php` |
| Колонки/фильтры таблицы депозитов | `app/Filament/Resources/DepositResource.php` |
| Кнопка «Sync now» | `WalletResource::table()` (строка + bulk), `WalletResource/Pages/{ListWallets,EditWallet,ViewWallet}.php`, `ClientResource/RelationManagers/WalletsRelationManager.php` |
| Правила доступа в админку | `app/Models/User.php::canAccessPanel()` |
| Валидация TRON-адреса (строгость checksum) | `app/Rules/TronAddressRule.php`, `app/Domain/TronScan/Support/TronAddress.php`, флаг `TRON_STRICT_ADDRESS_CHECKSUM` |
| Схема БД, индексы | `database/migrations/2026_08_04_*` |
| Демо-клиенты и кошельки | `database/seeders/DemoDeskSeeder.php` |
| Поля JSON API | `app/Http/Resources/DepositJsonResource.php`, фильтры — `app/Http/Controllers/Api/DepositController.php` |
| Состав docker-сервисов | `docker-compose.yml`, образ — `docker/php/Dockerfile`, старт — `docker/php/entrypoint.sh` |

---

## 4. Где что проверяется тестами

| Поведение | Тест |
|-----------|------|
| Парсинг фикстуры → депозиты, повторный sync → 0 новых | `tests/Feature/DepositSyncServiceTest.php` |
| Ошибки API (429, 4xx) → доменные исключения и `failed`-прогон | там же |
| Правила: blocked-клиент, неактивный кошелёк, только входящие | там же |
| Команда `deposits:sync` во всех режимах | `tests/Feature/SyncDepositsCommandTest.php` |
| Режим `fake` без сети, отсутствие коллизий `tx_hash` | `tests/Feature/FakeTronScanDriverTest.php` |
| Страницы админки, «Sync now», валидация адреса в форме | `tests/Feature/FilamentAdminTest.php` |
| JSON API | `tests/Feature/DepositsApiTest.php` |
| Адреса и денежные суммы (юнит) | `tests/Unit/TronAddressTest.php`, `tests/Unit/TokenAmountTest.php` |

---

## 5. Файлы фреймворка, которые тоже трогали

| Файл | Что изменено |
|------|--------------|
| `bootstrap/app.php` | подключён `routes/api.php` с префиксом `api` |
| `bootstrap/providers.php` | зарегистрирован `TronScanServiceProvider` |
| `app/Models/User.php` | реализован `FilamentUser::canAccessPanel()` |
| `routes/web.php` | `/` → редирект на `/admin` |
| `phpunit.xml` | SQLite in-memory, `TRONSCAN_DRIVER=http` + тестовый ключ, ретраи выключены |
| `composer.json` | filament, predis, larastan; `audit.block-insecure = false` (см. README §11) |
| `.gitattributes` | `*.sh` всегда с LF — иначе entrypoint ломается в контейнере при клоне на Windows |
| `.gitignore` | добавлены `/database/*.sqlite`, `/.claude` |
