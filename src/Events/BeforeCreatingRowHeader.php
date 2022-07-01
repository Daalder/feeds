<?php

namespace Daalder\Feeds\Events;


use Pionect\Daalder\Models\Store\Store;

class BeforeCreatingRowHeader
{

    /**
     * @param string $vendor
     * @param array $fieldNames
     */
    public function __construct(string $vendor, Store $store, array $fieldNames)
    {
        $this->vendor = $vendor;
        $this->store = $store;
        $this->fieldNames = $fieldNames;

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
     * @return string
     */
    public function getFieldNames()
    {
        return $this->fieldNames;
    }

    /**
     * @param array $fieldNames
     * @return void
     */
    public function setFieldNames(array $fieldNames)
    {
        $this->fieldNames = $fieldNames;
    }
}

