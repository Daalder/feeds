<?php

namespace Daalder\Feeds;

use Daalder\Feeds\Commands\GenerateFeeds;
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


        $this->publishes([
            __DIR__.'/../config/feeds.php' => config_path('feeds.php'),
        ]);

        $this->commands([
            GenerateFeeds::class,
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/feeds.php', 'feeds');
    }
}
