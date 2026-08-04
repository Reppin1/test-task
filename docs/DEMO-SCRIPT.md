# Сценарий демо: что показать и что заскринить

Задание требует короткий скринкаст **или** 5–10 скриншотов админки + список команд.
Ниже — готовый порядок действий: пройти по шагам и снять кадры.

Перед началом: `cp .env.example .env` → `docker compose up -d --build` →
дождаться в `docker compose logs -f app` строки `запускаю: php artisan serve`.
Драйвер по умолчанию `fake`, поэтому ключ TronScan не нужен.

---

## Кадры

| # | Экран | Что должно быть видно |
|---|-------|-----------------------|
| 1 | `docker compose ps` в терминале | пять сервисов: app, queue, scheduler, mysql, redis |
| 2 | http://localhost:8000/admin/login | форма входа, бренд «Crypto Deposit Desk» |
| 3 | Dashboard | 4 плитки статистики + таблица последних прогонов (до синхронизации — «никогда») |
| 4 | Clients | 2 клиента, у Northwind Capital — красный badge `Blocked`, счётчики кошельков/депозитов |
| 5 | Clients → карточка клиента | relation-менеджеры Wallets и Deposits |
| 6 | Wallets | 3 кошелька, toggle `Active`, кнопка **Sync now**, кнопка «Sync all active» в шапке |
| 7 | Момент после **Sync now** | зелёное уведомление «Sync queued» |
| 8 | Deposits | 4+ записи, badges статусов, вкладки All/Confirmed/Pending/Ignored, у клиента Northwind — пометка blocked |
| 9 | Deposits → фильтры | открытая панель фильтров: client, wallet, status, диапазон дат |
| 10 | Deposits → карточка депозита | сумма, статус, ссылка «Открыть в TronScan», раскрытый Raw payload |
| 11 | Sync runs | два прогона по одному кошельку: первый `created = 4`, второй `created = 0` (идемпотентность) |
| 12 | Терминал | вывод `php artisan deposits:sync` (таблица fetched/created/ignored/skipped) |

---

## Команды для показа

```bash
docker compose exec app php artisan deposits:sync
```

```bash
docker compose exec app php artisan deposits:sync --queue
```

```bash
docker compose exec app php artisan deposits:sync TAUN6FwrnwwmaEqYcckffC7wYmbaS6cBiX
```

```bash
docker compose exec app php artisan schedule:list
```

```bash
docker compose exec app vendor/bin/phpunit
```

```bash
curl "http://localhost:8000/api/deposits?status=confirmed&per_page=5"
```

---

## Что проговорить голосом (если скринкаст)

1. Слои: Filament / команда / расписание → Job → `DepositSyncService` → интерфейс `TronScanClient`
   с двумя реализациями (http и fake).
2. Идемпотентность: проверка + unique-индекс `deposits.tx_hash` + `ShouldBeUnique` на джобе —
   второй прогон даёт `created = 0`.
3. Деньги: `decimal(36,6)` и строки, `TokenAmount::fromBaseUnits()` вместо float.
4. Ошибки: 429/5xx ретраятся, 4xx — нет; любой исход виден в `sync_runs`, а не только в логах.
5. Тесты: 57 штук, сеть замокана `Http::fake()` + `preventStrayRequests()`.
