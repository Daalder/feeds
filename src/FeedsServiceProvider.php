<?php

namespace Daalder\Feeds;

use Daalder\Feeds\Commands\GenerateFeedsCommand;
use Daalder\Feeds\Commands\PurgeOldExportFeedsCommand;
use Daalder\Feeds\ServiceProviders\EventServiceProvider;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

/**
 * Class FeedsServiceProvider
 *
 * @package Feeds
 */
class FeedsServiceProvider extends ServiceProvider
{
    /**
     * Boot FeedsServiceProvider
     */
    public function boot()
    {
        parent::boot();

        $this->loadViewsFrom( __DIR__ . '/../' . 'resources/views', 'daalder-feeds');
        $this->loadMigrationsFrom(__DIR__ . '/../' . 'database/migrations', 'daalder-feeds');

        $this->publishes([
            __DIR__.'/../config/daalder-feeds.php' => config_path('daalder-feeds.php'),
        ]);

        $this->commands([
            GenerateFeedsCommand::class,
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/daalder-feeds.php', 'daalder-feeds');

        $this->app->register(EventServiceProvider::class);
    }
}
