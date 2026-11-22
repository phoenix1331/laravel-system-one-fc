<?php

declare(strict_types=1);

namespace App\Providers;

use App\Engine\EngineConfig;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * @phpstan-import-type EngineConfigArray from EngineConfig
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The engine never reads Laravel config itself; it gets this typed copy instead.
        $this->app->singleton(EngineConfig::class, function (Application $app): EngineConfig {
            /** @var EngineConfigArray $config */
            $config = $app->make('config')->get('engine');

            return EngineConfig::fromArray($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
