<?php

namespace Daalder\Feeds\ServiceProviders;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class FeedsRouteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $middleware = hook('authenticated-middleware',
            ['web', 'auth', 'global_view_shares', 'language_switch', 'set_store']);

        $group_attributes = [
            'domain' => Str::after(config('app.url'), '://'),
            'middleware' => $middleware,
        ];
        Route::group($group_attributes, function () {
            require __DIR__ . '/../../routes/feeds.php';
        });
    }

    public function register()
    {
        //
    }
}
