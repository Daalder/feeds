<?php

namespace Daalder\Feeds\ServiceProviders;

use Daalder\Feeds\Listeners\PurgeExpiredFeedsDaily;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Pionect\Daalder\Events\Interval\DailyEvent;

/**
 * Class EventServiceProvider
 * @package Daalder\Feeds\ServiceProviders
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        DailyEvent::class => [
            PurgeExpiredFeedsDaily::class
        ],
    ];
}
