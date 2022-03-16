<?php

use Daalder\Feeds\Jobs\AdmarktFeed;

return [
    'bucket' => 'nubuiten-feeds',
    'enabled-feeds' => [
        AdmarktFeed::class,
    ],
    'enabled-stores-ids' => [
        1, 2
    ]
];
