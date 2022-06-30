<?php

use Daalder\Feeds\Models\Feed\FeedsPermission;
use Illuminate\Support\Facades\Route;
use Daalder\Feeds\Http\Controllers\FeedsController;
use Illuminate\Support\Str;

$middleware = hook('authenticated-middleware',
    ['web', 'auth', 'global_view_shares', 'language_switch', 'set_store']);

$group_attributes = [
    'domain' => Str::after(config('app.url'), '://'),
    'middleware' => $middleware,
];

Route::group($group_attributes, function () {
    Route::group(['prefix' => 'feeds', 'middleware' => 'can:' . FeedsPermission::VIEW_FEEDS], function () {
        Route::get('', FeedsController::class . '@index');
        Route::post('/generate', [
            'middleware' => 'can:' . FeedsPermission::STORE_FEEDS,
            'uses' => FeedsController::class . '@store']);
    });
});


