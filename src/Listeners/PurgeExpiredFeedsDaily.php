<?php

namespace Daalder\Feeds\Listeners;

use Daalder\Feeds\Jobs\PurgeExpiredFeeds;
use Pionect\Daalder\Events\Interval\DailyEvent;

class PurgeExpiredFeedsDaily
{
    /**
     * Handle the event.
     *
     * @param DailyEvent $event
     * @return void
     */
    public function handle(DailyEvent $event)
    {
        dispatch(new PurgeExpiredFeeds());
    }
}
