<?php

namespace Daalder\Feeds\Http\Requests;

use Illuminate\Validation\Rule;
use Pionect\Daalder\Http\Requests\Request;

class StoreFeedsRequest extends Request
{


    /**
     * @return array
     */
    public function rules(): array
    {
        $approvedVendors = array_map(function($vendor) {
            return class_basename($vendor);
        }, config('daalder-feeds.enabled-feeds'));
        $approvedStores =  config('daalder-feeds.enabled-store-codes');

        return [
            'vendors' => ['sometimes', 'nullable', 'array', Rule::in($approvedVendors)],
            'stores' => ['sometimes', 'nullable', 'array', Rule::in($approvedStores)]
        ];
    }
}
