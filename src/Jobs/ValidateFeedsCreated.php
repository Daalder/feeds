<?php

namespace Daalder\Feeds\Jobs;

use Aws\S3\S3ClientInterface;
use Aws\S3\S3MultiRegionClient;
use Daalder\Feeds\Mail\FeedErrorEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Pionect\Daalder\Models\Store\Repositories\StoreRepository;
use Pionect\Daalder\Models\Store\Store;

class ValidateFeedsCreated
{
    use Dispatchable;

    /** @var S3MultiRegionClient */
    protected $s3Client;

    /** @var string[] */
    protected $feeds;

    /** @var integer[] */
    protected $enabledStoreCodes;
    
    /** @var string */
    protected $feedsBucket = '';

    public function __construct()
    {
        $this->feedsBucket = config('daalder-feeds.bucket');
        $this->feeds = config('daalder-feeds.enabled-feeds');
        $this->enabledStoreCodes = config('daalder-feeds.enabled-store-codes');
    }

    public function handle()
    {
        $this->s3Client = app(S3ClientInterface::class);

        $stores = Store::query()->whereIn('code', $this->enabledStoreCodes)->get();
        $invalidFeeds = [];
        
        foreach($this->feeds as $feed) {
            foreach($stores as $store) {
                // Get variables from temporary feed instance
                $feedInstance = (new $feed($store));
                $feedName = $feedInstance->vendor;
                $feedType = $feedInstance->type;
                
                // Prepare path to file on S3
                $targetDirectory = $store->code.'/'.$feedName;
                $targetFileName = $feedName.'.'.$feedType;
                $targetPath = $targetDirectory .'/'. $targetFileName;

                try {
                    // Get the currently active feed file for this feed/store combination
                    $currentFeed = $this->s3Client->getObject([
                        'Bucket' => $this->feedsBucket,
                        "Key" => $targetPath,
                    ]);
                } catch(\Exception $e) {
                    // If the file is missing, add it to the $missingFeeds array
                    $invalidFeeds[] = [
                        'storeCode' => $store->code,
                        'feedName' => $feedName,
                        'lastDate' => null,
                    ];
                    continue;
                }

                // Get last modified date from current feed file
                $lastModifiedDate = $currentFeed->get('LastModified');
                $lastModifiedDate = Carbon::createFromTimestamp($lastModifiedDate->getTimestamp());
                
                // If the file was not modified today, add it to the $missingFeeds array
                if($lastModifiedDate->diffInDays(today()) !== 0) {
                    $invalidFeeds[] = [
                        'storeCode' => $store->code,
                        'feedName' => $feedName,
                        'lastDate' => $lastModifiedDate->toDateTimeString(),
                    ];
                }
            }
        }
        
        $missingFeeds = collect($invalidFeeds)->whereNull('lastDate');
        $outdatedFeeds = collect($invalidFeeds)->whereNotNull('lastDate');

        if (count($missingFeeds) > 0 || count($outdatedFeeds) > 0) {
            Mail::send(new FeedErrorEmail(collect($missingFeeds), collect($outdatedFeeds)));
        }
    }
}
