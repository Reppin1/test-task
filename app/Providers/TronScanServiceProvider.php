<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\TronScan\Contracts\TronScanClient;
use App\Domain\TronScan\FakeTronScanClient;
use App\Domain\TronScan\TronScanHttpClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

/**
 * Биндит контракт TronScanClient на реализацию по config('tronscan.driver'):
 *   http — TronScanHttpClient (реальный API),
 *   fake — FakeTronScanClient (JSON-фикстура, работает без ключа и сети).
 */
class TronScanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TronScanClient::class, function (Application $app): TronScanClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('tronscan');

            return match ($config['driver'] ?? 'http') {
                'fake' => new FakeTronScanClient($config),
                default => new TronScanHttpClient($app->make(Factory::class), $config),
            };
        });
    }
}
