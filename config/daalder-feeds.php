<?php

use Daalder\Feeds\Jobs\AdmarktFeed;
use Daalder\Feeds\Jobs\BeslistFeed;
use Daalder\Feeds\Jobs\BolFeed;
use Daalder\Feeds\Jobs\GoogleFeed;
use Daalder\Feeds\Jobs\NetrivalsFeed;
use Daalder\Feeds\Jobs\ShoprFeed;
use Daalder\Feeds\Jobs\TradeTrackerFeed;

return [
    'bucket' => 'daalder-feeds-testing',
    'enabled-feeds' => [
        AdmarktFeed::class,
        BeslistFeed::class,
        BolFeed::class,
        GoogleFeed::class,
        NetrivalsFeed::class,
        ShoprFeed::class,
        TradeTrackerFeed::class,
    ],
    'enabled-stores-ids' => [
        1, 2
    ],
    'validate-feeds' => [
        'enabled' => true,
        'email-addresses' => [],
        'receiver-names' => [],
    ]
];
