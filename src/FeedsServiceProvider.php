<?php

namespace Daalder\Feeds;

use Daalder\Feeds\Commands\GenerateFeedsCommand;
use Daalder\Feeds\Commands\PurgeOldExportFeedsCommand;
use Daalder\Feeds\ServiceProviders\EventServiceProvider;
use Daalder\Feeds\ServiceProviders\FeedsRouteServiceProvider;
use Daalder\Feeds\Services\VariationChecker;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Collection;
use Pionect\Daalder\Hooks\Facades\Hook;
use Pionect\Daalder\Menus\Item;

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
            __DIR__.'/../database/seeders' => database_path('seeders'),
            __DIR__.'/../public' => public_path()
        ]);

        $this->commands([
            GenerateFeedsCommand::class,
        ]);

        if(class_exists(Hook::class)){
            Hook::listen('main_menu.menu_item.create', function (Collection $items) {
                return $items->push(new Item('/feeds', 'Feeds', null, 'leak_add'));
            });
        }
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
        $this->app->singleton(VariationChecker::class);
        $this->app->register(FeedsRouteServiceProvider::class);
    }
}
