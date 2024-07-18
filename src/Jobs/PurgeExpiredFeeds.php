<?php

namespace Daalder\Feeds\Jobs;

use Aws\S3\S3ClientInterface;
use Aws\S3\S3MultiRegionClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class PurgeOldExportFeeds.
 */
class PurgeExpiredFeeds implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** @var string */
    protected $keepFor = '';

    /**
     * PurgeOldExportFeeds constructor.
     */
    public function __construct()
    {
        $this->keepFor = config('daalder-feeds.keep-feeds');
    }

    public function handle()
    {
        $removeBefore = today()->sub($this->keepFor);
        $filesToRemove = collect();

        // Get the filepaths for all the feeds in the Storage disk
        $feedFilePaths = Storage::disk(config('daalder-feeds.disk'))->allFiles("feeds");

        foreach($feedFilePaths as $filePath) {
            // Get filename including extention
            $fileName = Str::afterLast($filePath, '/');
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
                    $filesToRemove->push($filePath);
                }
            }
        }

        foreach($filesToRemove as $fileToRemove) {
            Storage::disk(config('daalder-feeds.disk'))->delete($fileToRemove);
        }
    }
}
