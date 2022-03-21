<?php

use Daalder\Feeds\Jobs\AdmarktFeed;
use Daalder\Feeds\Jobs\BeslistFeed;
use Daalder\Feeds\Jobs\BolFeed;
use Daalder\Feeds\Jobs\GoogleFeed;
use Daalder\Feeds\Jobs\NetrivalsFeed;
use Daalder\Feeds\Jobs\ShoprFeed;
use Daalder\Feeds\Jobs\TradeTrackerFeed;

return [
    'bucket' => '',
    'enabled-feeds' => [],
    'enabled-store-codes' => [],
    // Format:
    // 'field-overwrites' => [ 'vendor' => [ 'fieldname' => 'fieldvalue' ] ]
    'field-overwrites' => [],
    'validate-feeds' => [
        'enabled' => true,
        'email-addresses' => [],
        'receiver-names' => [],
    ],
    'keep-feeds' => '7 days',
];
