<?php

namespace Daalder\Feeds\Jobs;

use Aws\S3\S3ClientInterface;
use Aws\S3\S3MultiRegionClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class PurgeOldExportFeeds.
 */
class PurgeExpiredFeeds implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** @var S3MultiRegionClient */
    protected $s3Client;

    /** @var string */
    protected $feedsBucket = '';

    /** @var string */
    protected $keepFor = '';

    /**
     * PurgeOldExportFeeds constructor.
     */
    public function __construct()
    {
        $this->feedsBucket = config('daalder-feeds.bucket');
        $this->keepFor = config('daalder-feeds.keep-feeds');
    }

    public function handle()
    {
        $this->s3Client = app(S3ClientInterface::class);
        $removeBefore = today()->sub($this->keepFor);
        $filesToRemove = collect();

        // Get iterator for all objects in feedsBucket
        $iterator = $this->s3Client->getIterator('ListObjects', [
            'Bucket' => $this->feedsBucket,
        ]);

        foreach($iterator as $file) {
            // Get filename including extention
            $fileName = Str::afterLast($file['Key'], '/');
            // Remove extention from filename
            $fileName = Str::beforeLast($fileName, '.');
            // Get the date from the filename
            $dateString = Str::afterLast($fileName, '_');

            // If date equals filename, there is no date in the filename.
            // That means this is a currently active feed file, so skip it.
            if($dateString !== $fileName) {
                $date = Carbon::parse($dateString);

                // If the feed is from before the cut-off date
                if($date->diffInDays($removeBefore, false) > 0) {
                    // Mark it for removal
                    $filesToRemove->push($file['Key']);
                }
            }
        }

        foreach($filesToRemove as $fileToRemove) {
            $this->s3Client->deleteObject([
                'Bucket' => $this->feedsBucket,
                'Key' => $fileToRemove,
            ]);
        }
    }
}
