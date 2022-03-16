<?php

namespace App\Jobs\Feeds;

use App\Mail\FeedErrorEmail;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
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
    /** @var S3Client */
    protected $s3Client;

    protected $feedsBucket = 'nubuiten-feeds';

    protected $feeds = [
        'admarkt', 'beslist', 'bol', 'google', 'netrivals', 'shopr', 'tradetracker'
    ];

    public function handle()
    {
        $this->storeRepository = app(StoreRepository::class);
        $this->s3Client = app(S3ClientInterface::class);

        // Get iterator for all objects in feedsBucket
        $iterator = $this->s3Client->getIterator('ListObjects', [
            'Bucket' => $this->feedsBucket,
        ]);

        // Iterate objects and match store/feed
        $feedsFound = [];
        foreach ($iterator as $object) {
            $pathParts = explode('/', $object['Key']);
            $feed = $pathParts[0];
            $extension = '.'.array_last(explode('.', $pathParts[1]));
            $storeCode = Str::replace($extension, '', $pathParts[1]);

            $feedsFound[$feed] = array_merge($feedsFound[$feed] ?? [], [
                $storeCode => $object,
            ]);
        }

        // Iterate all feed-store combinations that should have a file on S3
        $missingFeeds = [];
        $outdatedFeeds = [];

        foreach ($this->feeds as $feed) {
            /** @var Store $store */
            foreach ($this->storeRepository->all() as $store) {
                // If the file wasn't found, save it as missing
                if (array_has($feedsFound, $feed) === false || array_has($feedsFound[$feed], $store->code) === false) {
                    $missingFeeds[$feed] = array_merge($missingFeeds[$feed] ?? [], [$store->code]);
                } else {
                    // Else, check the timestamp
                    $awsDateTime = $feedsFound[$feed][$store->code]['LastModified'];
                    $dateTime = Carbon::createFromTimestamp($awsDateTime->getTimestamp(),
                        $awsDateTime->getTimezone()->getName());

                    // If object is older than 24 hours, the generation must've failed
                    if ($dateTime->diffInHours(now()) > 24) {
                        $outdatedFeeds[$feed] = array_merge($outdatedFeeds[$feed] ?? [],
                            [$store->code => $dateTime->toDateString()]);
                    }
                }
            }
        }

        if (count($missingFeeds) > 0 || count($outdatedFeeds) > 0) {
            Mail::send(new FeedErrorEmail(collect($missingFeeds), collect($outdatedFeeds)));
        }
    }
}
