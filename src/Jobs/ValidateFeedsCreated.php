<?php

namespace Daalder\Feeds\Jobs;

use Daalder\Feeds\Mail\FeedErrorEmail;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Pionect\Daalder\Models\Store\Store;

class ValidateFeedsCreated
{
    use Dispatchable;

    /** @var string[] */
    protected $feeds;

    /** @var int[] */
    protected $enabledStoreCodes;

    public function __construct()
    {
        $this->feeds = config('daalder-feeds.enabled-feeds');
        $this->enabledStoreCodes = config('daalder-feeds.enabled-store-codes');
    }

    public function handle()
    {
        $stores = Store::query()->whereIn('code', $this->enabledStoreCodes)->get();
        $invalidFeeds = [];

        foreach ($this->feeds as $feed) {
            foreach ($stores as $store) {
                // Get variables from temporary feed instance
                $feedInstance = (new $feed($store));
                $feedName = $feedInstance->vendor;
                $feedType = $feedInstance->type;

                // Prepare path to file on S3
                $targetDirectory = $store->code.'/'.$feedName;
                $targetFileName = $feedName.'.'.$feedType;
                $targetPath = $targetDirectory.'/'.$targetFileName;

                $previousFeedName = $feedInstance->vendor.'_'.today()->subDay()->toDateString().'.'.$feedType;
                $previousFeedUrl = '/'.$targetDirectory.'/'.$previousFeedName;

                if (! Storage::disk(config('daalder-feeds.disk'))->exists($targetPath)) {
                    $invalidFeeds[] = [
                        'storeCode' => $store->code,
                        'feedName' => $feedName,
                        'lastDate' => null,
                        'previousFeedName' => $previousFeedName,
                        'previousFeedUrl' => $previousFeedUrl,
                    ];

                    continue;
                }

                // Get last modified date from current feed file
                $lastModifiedDate = Storage::disk(config('daalder-feeds.disk'))->lastModified($targetPath);
                $lastModifiedDate = Carbon::createFromTimestamp($lastModifiedDate);

                // If the file was not modified today, add it to the $missingFeeds array
                if ($lastModifiedDate->diffInDays(today()) !== 0) {
                    $invalidFeeds[] = [
                        'storeCode' => $store->code,
                        'feedName' => $feedName,
                        'lastDate' => $lastModifiedDate->toDateTimeString(),
                        'previousFeedName' => $previousFeedName,
                        'previousFeedUrl' => $previousFeedUrl,
                    ];
                }
            }
        }

        $missingFeeds = collect($invalidFeeds)->whereNull('lastDate');
        $outdatedFeeds = collect($invalidFeeds)->whereNotNull('lastDate');

        if (count($missingFeeds) > 0 || count($outdatedFeeds) > 0) {
            Mail::send(new FeedErrorEmail($missingFeeds, $outdatedFeeds));
        }
    }
}
