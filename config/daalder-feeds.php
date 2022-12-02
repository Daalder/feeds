<?php

use Daalder\Feeds\Jobs\Feeds\AdmarktFeed;
use Daalder\Feeds\Jobs\Feeds\BeslistFeed;
use Daalder\Feeds\Jobs\Feeds\BolFeed;
use Daalder\Feeds\Jobs\Feeds\GoogleFeed;
use Daalder\Feeds\Jobs\Feeds\NetrivalsFeed;
use Daalder\Feeds\Jobs\Feeds\ShoprFeed;
use Daalder\Feeds\Jobs\Feeds\TradeTrackerFeed;

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
    'upload-feeds' => env('UPLOAD_FEEDS', true)
];
