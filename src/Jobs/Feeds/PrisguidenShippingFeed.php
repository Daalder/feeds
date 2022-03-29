<?php

namespace Daalder\Feeds\Jobs\Feeds;

/**
 * @see https://prisguiden.no/om-oss/nyheter-og-presse/slik-gjoer-du-produktene-tilgjengelig-paa-prisguiden-277
 * Class PrisguidenFeed
 */
class PrisguidenShippingFeed extends Feed
{
    /** @var string */
    public $type = 'csv';

    /** @var string */
    public $vendor = 'prisguiden-shipping';
    
    /** @var string[] */
    public $fieldNames = [
        'ean',
        'varehus',
        'lagerstatus',
    ];

    protected function generate()
    {
        // TODO: Implement generate() method.
    }
}