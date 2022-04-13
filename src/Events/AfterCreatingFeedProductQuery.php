<?php

namespace Daalder\Feeds\Events;

use Illuminate\Database\Eloquent\Builder;
use Pionect\Daalder\Models\Basket\BasketEntry;
use Pionect\Daalder\Models\Price\Price;

class AfterCreatingFeedProductQuery
{
    /** @var string $feedClass */
    private $feedClass;

    /** @var Builder $productsQuery */
    private $productsQuery;

    /**
     * @param  BasketEntry  $basketEntry
     * @param  Price  $price
     */
    public function __construct(string $feedClass, Builder $productsQuery)
    {
        $this->feedClass = $feedClass;
        $this->productsQuery = $productsQuery;
    }

    /**
     * @return string
     */
    public function getFeedClass()
    {
        return $this->feedClass;
    }

    /**
     * @return Builder
     */
    public function getProductsQuery()
    {
        return $this->productsQuery;
    }

    /**
     * @param Builder $productsQuery
     */
    public function setProductsQuery(Builder $productsQuery)
    {
        $this->productsQuery = $productsQuery;
    }
}
