<?php

namespace Daalder\Feeds\Services;


use Daalder\Feeds\Jobs\ValidateFeedsCreated;
use Illuminate\Bus\Batch;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Pionect\Daalder\Events\CommandHeartBeat;
use Pionect\Daalder\Models\Store\Store;

class FeedsHandler
{

    /**
     * @param $feeds
     * @param $stores
     * @return array
     * @throws \Throwable
     */
    private function processFeedsJobs($feeds, $stores): array
    {
        $batch = [];

        foreach ($feeds as $feed) {
            foreach ($stores as $store) {
                $batch[] = new $feed($store);
            }
        }

        Bus::batch($batch)
            ->name('Generate feeds')
            ->finally(function (Batch $batch) {
                if (config('daalder-feeds.validate-feeds.enabled') === true) {
                    ValidateFeedsCreated::dispatchSync();
                }

                event(new CommandHeartBeat('feeds-generated'));
            })
            ->onQueue('medium')
            ->allowFailures()
            ->dispatch();

        return [$feeds, $stores];
    }

    /**
     * @param array $params
     * @return array
     * @throws \Throwable
     */
    public function generateFeeds(array $params = []): array
    {
        $chosenVendors = Arr::get($params, 'vendors');
        if($chosenVendors) {
            $selectedFeeds = array_map(function ($vendor) {
                return 'Daalder\Feeds\Jobs\Feeds\\' . $vendor;
            }, $chosenVendors);
        }

        $feeds = $selectedFeeds ?? config('daalder-feeds.enabled-feeds');

        $storeNames = Arr::get($params, 'stores') ?: config('daalder-feeds.enabled-store-codes');
        $stores = Store::query()
            ->whereIn('code', $storeNames)
            ->get();
        return $this->processFeedsJobs($feeds, $stores);
    }
}
