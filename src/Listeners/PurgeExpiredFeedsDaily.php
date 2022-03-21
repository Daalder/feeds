<?php

namespace Daalder\Feeds\Listeners;

use Daalder\Feeds\Jobs\PurgeExpiredFeeds;

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
