<?php

namespace Daalder\Feeds\Events;


use Pionect\Daalder\Models\Product\Product;
use Pionect\Daalder\Models\Store\Store;

class AfterCreatingFeedRow
{

    /**
     * @param string $vendor
     * @param Store $store
     * @param array $feedRow
     * @param Product $product
     */
    public function __construct(string $vendor, Store $store, array $feedRow, Product $product)
    {
        $this->vendor = $vendor;
        $this->store = $store;
        $this->feedRow = $feedRow;
        $this->product = $product;

    }

    /**
     * @return string
     */
    public function getVendor()
    {
        return $this->vendor;
    }

    /**
     * @return Store
     */
    public function getStore()
    {
        return $this->store;
    }

    /**
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @return string
     */
    public function getFeedRow()
    {
        return $this->feedRow;
    }

    /**
     * @param array $feedRow
     * @return void
     */
    public function setFeedRow(array $feedRow)
    {
        $this->feedRow = $feedRow;
    }
}

