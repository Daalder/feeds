<?php

use Daalder\Feeds\Models\Feed\FeedsPermission;
use Illuminate\Support\Facades\Route;
use Daalder\Feeds\Http\Controllers\FeedsController;

    Route::group(['prefix' => 'feeds', 'middleware' => 'can:' . FeedsPermission::VIEW_FEEDS], function () {
        Route::get('', FeedsController::class . '@index');
        Route::post('/generate', [
            'middleware' => 'can:' . FeedsPermission::STORE_FEEDS,
            'uses' => FeedsController::class . '@store']);
    });

