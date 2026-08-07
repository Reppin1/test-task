# Crypto Deposit Desk

Мини-сервис учёта крипто-депозитов **USDT TRC-20**: клиенты, TRON-кошельки, входящие переводы и журнал синхронизаций.
Данные тянутся из **TronScan API** (read-only), админка — **Filament 3**.

> Приватные ключи не хранятся, транзакции не подписываются и не отправляются. Только чтение explorer API.

| Компонент | Версия |
|-----------|--------|
| PHP | 8.2+ (проверено на 8.3 в Docker и 8.4 локально) |
| Laravel | 11.55 |
| Filament | 3.3 |
| БД | MySQL 8 (в тестах — SQLite in-memory) |
| Очереди / кэш | Redis (`predis`) |
| Инфраструктура | Docker Compose: app + queue + scheduler + mysql + redis |
| Тесты | PHPUnit 10, 58 тестов |
| Качество | Pint (`laravel` preset + `declare_strict_types`), Larastan level 5 |

---

## 1. Быстрый старт (Docker)

```bash
cp .env.example .env
```

```bash
docker compose up -d --build
```

Контейнер `app` при старте сам: поставит зависимости (если нет `vendor/`), сгенерирует `APP_KEY`,
дождётся MySQL, прогонит `migrate --force` и `db:seed --force`.

Открыть админку: **http://localhost:8000/admin**

Логин по умолчанию (из `.env`, `ADMIN_EMAIL` / `ADMIN_PASSWORD`):

```
admin@example.com / password
```

Что поднимается:

| Сервис | Что делает | Порт наружу |
|--------|------------|-------------|
| `app` | `php artisan serve` + миграции и сидеры при старте | `8000` |
| `queue` | `php artisan queue:work redis` — обрабатывает sync-джобы | — |
| `scheduler` | `php artisan schedule:work` — раз в 5 минут ставит sync-джобы | — |
| `mysql` | MySQL 8, том `mysql-data` | `33061` |
| `redis` | Redis 7, том `redis-data` | `63791` |

Полезное:

```bash
docker compose logs -f app queue scheduler
```

```bash
docker compose exec app php artisan deposits:sync
```

```bash
docker compose exec app vendor/bin/phpunit
```

### Запуск без Docker

Нужны PHP 8.2+ и Composer. Redis/MySQL не обязательны — можно взять SQLite и `QUEUE_CONNECTION=sync`:

```bash
composer install && cp .env.example .env && php artisan key:generate
```

Затем в `.env`: `DB_CONNECTION=sqlite`, **`DB_DATABASE=database/database.sqlite`** (иначе Laravel создаст файл
БД с именем из `DB_DATABASE` в корне проекта), `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`, `SESSION_DRIVER=file`,
создать пустой файл `database/database.sqlite` и выполнить:

```bash
php artisan migrate --seed && php artisan serve
```

---

## 2. Демо-сценарий (2 минуты, без API-ключа)

По умолчанию в `.env.example` стоит `TRONSCAN_DRIVER=fake` — клиент TronScan подменяется офлайн-реализацией,
которая читает фикстуру `database/fixtures/tronscan/token_trc20_transfers.json`. Сеть и ключ не нужны.

1. `docker compose up -d --build`, дождаться в логах `app` строки `запускаю: php artisan serve`.
2. Зайти в http://localhost:8000/admin под `admin@example.com / password`.
3. **Wallets** → у любого кошелька нажать **Sync now** (или «Sync all active» в шапке).
   Задача уходит в Redis, её подхватывает контейнер `queue`, UI не блокируется.
4. **Deposits** — появились 4 записи на кошелёк: 2 `confirmed`, 1 `pending`, 1 `ignored` (нулевая сумма).
   Пятый трансфер из фикстуры — исходящий, он намеренно не попадает в депозиты.
5. Нажать **Sync now** ещё раз → новых строк нет (идемпотентность по `tx_hash`),
   в **Sync runs** появился второй прогон с `created = 0`.
6. Проверить фильтры на **Deposits** (client / wallet / status / диапазон дат, поиск по `tx_hash` и адресу)
   и ссылку на TronScan в колонке `Tx hash`.

То же самое из консоли:

```bash
docker compose exec app php artisan deposits:sync
```

```
+---------+---------+---------+---------+----------+
| fetched | created | ignored | skipped | sync_run |
+---------+---------+---------+---------+----------+
| 10      | 8       | 2       | 2       | 1        |
+---------+---------+---------+---------+----------+
```

### Демо на живом API

1. Получить ключ: https://tronscan.org → профиль → **API Keys** (бесплатный тариф).
2. В `.env`: `TRONSCAN_DRIVER=http` и `TRONSCAN_API_KEY=<ваш ключ>`.
3. `docker compose restart app queue scheduler` и снова нажать **Sync now**.

Адреса в сидере — публичные TRON-адреса с реальной историей USDT, так что депозиты придут настоящие.
Без ключа TronScan жёстко режет частоту запросов: 429 превращается в `TronScanRateLimitException`,
прогон помечается `failed`, текст ошибки виден в **Sync runs**.

---

## 3. Команды

```bash
php artisan deposits:sync
```

| Вариант | Что делает |
|---------|------------|
| `deposits:sync` | Синхронно обходит **все активные** кошельки, пишет один batch-прогон (`sync_runs.wallet_id = null`) |
| `deposits:sync 5` | Один кошелёк по ID |
| `deposits:sync TAUN6Fwrnw...` | Один кошелёк по TRON-адресу |
| `deposits:sync --queue` | Ставит по `SyncWalletDepositsJob` на каждый активный кошелёк |
| `deposits:sync --trigger=schedule` | Помечает прогон источником `schedule` (используется планировщиком) |

Расписание (`routes/console.php`): каждые 5 минут (`DEPOSITS_SYNC_CRON`) выполняется
`deposits:sync --queue --trigger=schedule` с `withoutOverlapping()`. Выключается флагом
`DEPOSITS_SYNC_SCHEDULE_ENABLED=false`.

Очередь: `php artisan queue:work redis` (в Docker — сервис `queue`).

---

## 4. Модель данных

```
clients 1──n wallets 1──n deposits
                  └──n sync_runs   (wallet_id NULL = batch-прогон по всем активным)
```

| Таблица | Ключевые поля | Индексы |
|---------|---------------|---------|
| `clients` | `uuid` (публичный id), `name`, `email`, `status: active\|blocked`, `notes` | unique `uuid`, unique `email`, index `status` |
| `wallets` | `client_id`, `address` (TRON base58), `label`, `is_active`, `last_synced_at` | unique `address`, index `is_active`, составной `(client_id, is_active)`, FK |
| `deposits` | `wallet_id`, `tx_hash`, `from_address`, `to_address`, `amount decimal(36,6)`, `token_symbol`, `contract_address`, `block_timestamp`, `status: pending\|confirmed\|ignored`, `raw_payload json` | **unique `tx_hash`**, index `(wallet_id, block_timestamp)`, `(status, block_timestamp)`, FK |
| `sync_runs` | `wallet_id?`, `trigger: manual\|schedule\|command`, `status: running\|success\|failed`, `fetched_count`, `created_count`, `error_message`, `started_at`, `finished_at` | index `status`, `trigger`, `(wallet_id, created_at)`, FK |

Деньги — только `decimal(36,6)` в БД и строки в PHP (`'amount' => 'decimal:6'`).
Конвертация «сырых» единиц TronScan (`quant`) в десятичную строку — посимвольный сдвиг точки
в `TokenAmount::fromBaseUnits()`, без `float` и без зависимости от `ext-bcmath`.

---

## 5. Интеграция с TronScan

**Выбранный эндпоинт** (обоснование — [ADR-001](docs/ADR-001-tronscan-endpoint.md)):

```http
GET https://apilist.tronscanapi.com/api/token_trc20/transfers
  ?relatedAddress={wallet}
  &contract_address=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t
  &limit=50
  &start=0

Header: TRON-PRO-API-KEY: {TRONSCAN_API_KEY}
```

**Маппинг ответа → `deposits`:**

| TronScan | Deposit | Примечание |
|----------|---------|------------|
| `transaction_id` | `tx_hash` | ключ идемпотентности (unique) |
| `from_address` / `to_address` | `from_address` / `to_address` | строка создаётся только если `to_address == wallet.address` |
| `quant` + `tokenInfo.tokenDecimal` | `amount` | `12500000` при 6 decimals → `'12.500000'` |
| `block_ts` (мс) | `block_timestamp` | миллисекунды распознаются по величине значения |
| `contract_ret` / `finalResult` / `confirmed` | `status` | не `SUCCESS` или `confirmed = false` → `pending` |
| `tokenInfo.tokenAbbr`, `contract_address` | `token_symbol`, `contract_address` | чужой контракт отбрасывается |
| элемент ответа (урезанный) | `raw_payload` | сохраняем только полезные поля, не весь ответ |

**Как устроен клиент:**

* контракт `App\Domain\TronScan\Contracts\TronScanClient` — две реализации, биндинг в `TronScanServiceProvider`
  по `config('tronscan.driver')`: `http` → `TronScanHttpClient`, `fake` → `FakeTronScanClient`;
* таймауты (`TRONSCAN_TIMEOUT`, `TRONSCAN_CONNECT_TIMEOUT`) и retry с паузой
  (`TRONSCAN_RETRY_TIMES`, `TRONSCAN_RETRY_SLEEP_MS`); ретраятся только сетевые сбои, 429 и 5xx;
* доменные исключения: `TronScanRateLimitException` (429), `TronScanServerException` (5xx/сеть),
  `TronScanRequestException` (4xx), `TronScanResponseException` (2xx с неожиданной структурой);
* API-ключ живёт только в `.env`, в репозитории его нет.

**Идемпотентность и гонки.** Быстрый путь — `where('tx_hash', ...)->exists()`. Настоящая гарантия —
unique-индекс на `deposits.tx_hash`: при параллельном прогоне двух воркеров
`UniqueConstraintViolationException` перехватывается и трансфер считается пропущенным, а не ошибкой.
Дополнительно `SyncWalletDepositsJob` реализует `ShouldBeUnique` (`uniqueFor = 600`),
поэтому два прогона по одному кошельку не идут параллельно.

---

## 6. Бизнес-правила

1. **Заблокированный клиент** (`status = blocked`) — депозиты всё равно сохраняются.
   В админке имя такого клиента показывается красным badge с иконкой и подсказкой «Client is blocked»
   (списки Wallets и Deposits, карточка депозита).
2. **`amount <= 0`** — строка **создаётся** со статусом `ignored`. Так остаётся аудит-след того,
   что трансфер видели и сознательно не учли; в отчётные суммы `ignored` не входит.
3. **Неактивный кошелёк** (`is_active = false`) не участвует в `schedule`/batch-синхронизации.
   Ручной sync из админки и команды — **разрешён** (флаг `MANUAL_SYNC_FOR_INACTIVE_WALLETS`, по умолчанию `true`;
   при `false` сервис бросает `WalletNotSyncableException`, а кнопка «Sync now» скрывается).
4. **Только входящие**: трансфер попадает в `deposits` лишь при `to_address == wallet.address`
   и совпадении контракта с `USDT_TRC20_CONTRACT`. Исходящие считаются `skipped`.
5. **Прогоны**: `syncWallet()` пишет `sync_runs` с `wallet_id`, `syncActiveWallets()` — один прогон
   с `wallet_id = null`. Падение одного кошелька в batch не останавливает остальные: прогон завершается
   статусом `failed`, а в `error_message` собираются ошибки по кошелькам.

---

## 7. Админка (Filament)

| Ресурс | Возможности |
|--------|-------------|
| **Clients** | CRUD, фильтр по статусу, счётчики кошельков/депозитов, страница просмотра, relation-менеджеры Wallets (CRUD + «Sync now») и Deposits (read-only) |
| **Wallets** | CRUD, привязка к клиенту, валидация TRON-адреса с checksum, toggle `is_active` прямо в таблице, действие **Sync now** (одиночное, массовое и «Sync all active» в шапке), фильтры client / active / never synced, relation-менеджеры Deposits и Sync runs |
| **Deposits** | read-only список и карточка, вкладки All/Confirmed/Pending/Ignored, фильтры по client, wallet, status и диапазону дат, поиск по `tx_hash`, адресу кошелька и `from_address`, ссылка на `https://tronscan.org/#/transaction/{hash}`, badge в навигации с числом `pending`, raw payload в карточке |
| **Sync runs** | read-only журнал: статус, триггер, кошелёк (или «all active (batch)»), fetched/created, длительность, текст ошибки; фильтры по статусу/триггеру, «только batch», «с новыми депозитами»; автообновление раз в 30 с |
| **Dashboard** | виджет-статистика (сумма подтверждённых USDT, число депозитов и pending, активные кошельки, время последней успешной синхронизации) + таблица последних прогонов |

---

## 8. JSON API (бонус)

Read-only, без авторизации (демо), rate limit 60 req/min:

```bash
curl "http://localhost:8000/api/deposits?wallet=TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX&status=confirmed&per_page=10"
```

```bash
curl "http://localhost:8000/api/deposits/8f2c0a5f4b3e4d1a9c7b6e5d4c3b2a1908f7e6d5c4b3a29180f7e6d5c4b3a291"
```

Параметры: `wallet` (ID или адрес), `client` (ID или uuid), `status`, `from`, `until`, `per_page` (1..100).
`amount` сериализуется строкой, чтобы не терять точность в JS-клиентах.

---

## 9. Тесты

```bash
vendor/bin/phpunit
```

```
OK (58 tests, 165 assertions)
```

Сеть в тестах не используется: везде `Http::fake()` + `Http::preventStrayRequests()`.

| Файл | Что проверяет |
|------|---------------|
| `tests/Unit/TronAddressTest.php` | base58check-валидация: реальные адреса проходят, битый checksum / длина / алфавит / ETH-адрес — нет; hex → base58 |
| `tests/Unit/TokenAmountTest.php` | конвертация `quant` → десятичная строка, точность, которую теряет `float`, отрицательные и мусорные значения |
| `tests/Feature/DepositSyncServiceTest.php` | фикстура → 4 депозита с верными суммами и статусами; исходящий пропущен; повторный sync → 0 новых; запись `sync_runs` и `last_synced_at`; batch только по активным; 429/4xx → доменные исключения и `failed`-прогон; депозиты заблокированного клиента; запрет ручного sync неактивного кошелька; заголовок `TRON-PRO-API-KEY` и параметры запроса |
| `tests/Feature/SyncDepositsCommandTest.php` | `deposits:sync` во всех вариантах (все / по ID / по адресу / `--queue` / `--trigger`), неизвестный кошелёк → exit 1, Job делегирует в сервис |
| `tests/Feature/FakeTronScanDriverTest.php` | режим `TRONSCAN_DRIVER=fake`: депозиты создаются без единого HTTP-запроса, два кошелька не конфликтуют по unique `tx_hash` |
| `tests/Feature/DepositsApiTest.php` | фильтры API, строковый `amount`, 404, валидация параметров |
| `tests/Feature/FilamentAdminTest.php` | smoke всех страниц панели, редирект гостя на login, фильтр таблицы депозитов, действие «Sync now» ставит Job, форма кошелька отбивает битый адрес |

Статический анализ и стиль:

```bash
vendor/bin/pint --test
```

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

---

## 10. Конфигурация (`.env`)

| Переменная | По умолчанию | Назначение |
|------------|--------------|------------|
| `TRONSCAN_DRIVER` | `fake` | `http` — живой API, `fake` — фикстура (демо без ключа) |
| `TRONSCAN_API_KEY` | пусто | ключ TronScan; только в `.env` |
| `TRONSCAN_BASE_URL` | `https://apilist.tronscanapi.com` | базовый URL |
| `USDT_TRC20_CONTRACT` | `TR7NHqje…Lj6t` | контракт USDT TRC-20 |
| `USDT_TRC20_DECIMALS` | `6` | decimals токена |
| `TRONSCAN_TIMEOUT` / `TRONSCAN_CONNECT_TIMEOUT` | `15` / `5` | таймауты, сек |
| `TRONSCAN_RETRY_TIMES` / `TRONSCAN_RETRY_SLEEP_MS` | `3` / `500` | ретраи и пауза между ними |
| `TRONSCAN_PAGE_SIZE` / `TRONSCAN_MAX_PAGES` | `50` / `5` | пагинация: максимум 250 трансферов за прогон |
| `TRON_STRICT_ADDRESS_CHECKSUM` | `true` | строгая base58check-валидация адресов |
| `DEPOSITS_SYNC_SCHEDULE_ENABLED` / `DEPOSITS_SYNC_CRON` | `true` / `*/5 * * * *` | планировщик |
| `MANUAL_SYNC_FOR_INACTIVE_WALLETS` | `true` | разрешён ли ручной sync неактивного кошелька |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | `admin@example.com` / `password` | учётка, создаваемая сидером |

---

## 11. Что осталось за рамками

* **Роли `admin` / `viewer` и policies.** Публичной регистрации нет, `User::canAccessPanel()` пускает
  любого существующего пользователя — это точка расширения под роли.
* **JSON API без аутентификации** — намеренно, чтобы демо проверялось одним `curl`.
  Для прода: Sanctum + policy на `Deposit`.
* **Horizon** не подключён: хватает `queue:work` в отдельном контейнере, метрики прогонов видны в `sync_runs`.
* **Пагинация TronScan ограничена** `TRONSCAN_PAGE_SIZE × TRONSCAN_MAX_PAGES` (250 трансферов за прогон).
  Для кошельков с длинной историей нужен «курсор» по `block_ts` последнего известного депозита.
* **Вебхуков нет** — только pull, как и требует задание.
* **Laravel 11.x** зафиксирован по требованию задания; ветка уже EOL, поэтому в `composer.json`
  отключён `audit.block-insecure` — иначе Composer отказывается ставить 11.x. Для боевого проекта
  правильным решением был бы апгрейд на актуальную ветку фреймворка.
* Frontend-сборка (Vite/npm) не нужна: Filament отдаёт собственные скомпилированные ассеты из `public/`.

---

## 12. Документация репозитория

* [`STRUCTURE.md`](STRUCTURE.md) — карта проекта: где что лежит и зачем.
* [`docs/ADR-001-tronscan-endpoint.md`](docs/ADR-001-tronscan-endpoint.md) — почему выбран этот эндпоинт
  и как решена идемпотентность.
* [`docs/DEMO-SCRIPT.md`](docs/DEMO-SCRIPT.md) — сценарий скринкаста и список скриншотов для сдачи.
