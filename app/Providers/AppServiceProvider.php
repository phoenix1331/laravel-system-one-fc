<?php

declare(strict_types=1);

namespace App\Providers;

use App\Engine\EngineConfig;
use App\Managers\SystemOneManager;
use App\SystemOne\SystemOneClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

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

        $this->app->bind(SystemOneClient::class, function (Application $app): SystemOneClient {
            $config = $app->make('config');

            return new SystemOneClient(
                $app->make(HttpFactory::class),
                (string) $config->get('manager.ollama_url'),
                (string) $config->get('manager.model'),
                (int) $config->get('manager.timeout'),
            );
        });

        $this->app->bind(SystemOneManager::class, function (Application $app): SystemOneManager {
            $config = $app->make('config');
            /** @var array<string, array<string, mixed>> $questions */
            $questions = $config->get('manager.questions');

            return new SystemOneManager(
                $app->make(SystemOneClient::class),
                $questions,
                $app->make(LoggerInterface::class),
                substitutionsFromMinute: (int) $config->get('manager.substitutions_from_minute'),
            );
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
