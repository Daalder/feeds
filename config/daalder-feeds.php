<?php

return [
    'bucket' => '',
    'disk' => env('FEEDS_DISK', 'local'),
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
    'feeds-queue' => env('FEEDS_QUEUE', 'feeds'),
];
