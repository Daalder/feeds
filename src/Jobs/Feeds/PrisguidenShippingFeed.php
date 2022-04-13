<?php

namespace Daalder\Feeds\Jobs\Feeds;

use Pionect\Daalder\Models\Product\Product;

/**
 * @see https://prisguiden.no/om-oss/nyheter-og-presse/slik-gjoer-du-produktene-tilgjengelig-paa-prisguiden-277
 * Class PrisguidenShippingFeed
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

    protected function productToFeedRow(Product $product)
    {
        // TODO: Implement generate() method.
    }
}
