<?php

namespace Daalder\Feeds\Commands;

use App\Jobs\Feeds\Feed;
use Aws\S3\S3ClientInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pionect\Daalder\Models\Store\Repositories\StoreRepository;
use Pionect\Daalder\Models\Store\Store;
use ReflectionClass;

/**
 * Class GenerateExportFeedsCommand.
 */
class RollbackExportFeedCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'feeds:rollback';

    protected $signature = 'feeds:rollback {feed} {days} {store?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback an export feed.';

    /**
     * @var StoreRepository
     */
    private $storeRepository;

    /**
     * @var S3ClientInterface
     */
    private $s3Client;

    /**
     * GenerateExportFeedsCommand constructor.
     *
     * @param StoreRepository $storeRepository
     */
    public function __construct(StoreRepository $storeRepository)
    {
        parent::__construct();

        $this->storeRepository = $storeRepository;
        $this->s3Client = app(S3ClientInterface::class);
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $feed = $this->argument('feed');
        $days = $this->argument('days');
        $this->info('Generating '.$feed.' feed.');

        $reflection = new ReflectionClass(Feed::class);
        $namespace = $reflection->getNamespaceName();

        $jobClass = $namespace.'\\'.Str::studly($feed).'Feed';

        if (class_exists($jobClass)) {
            if ($this->argument('store')) {
                $s = $this->argument('store');
                $store = $this->storeRepository->findByCode($s) ?: $this->storeRepository->find($s);
                if ($store) {
                    $this->info('Rolling back feed '.$feed.' for '.$store->code.'.');
                    $this->rollback($feed, $store, $days);
                } else {
                    $this->error('The store: '.$s.' doesn\'t exist.');
                }
            } else {
                foreach ($this->storeRepository->all() as $store) {
                    $this->info('Rolling back feed '.$feed.' for '.$store->code.'.');
                    $this->rollback($feed, $store, $days);
                }
            }
        } else {
            $this->error('The feed: '.$feed.' doesn\'t exist.');
        }
    }

    /**
     * @param $feed
     * @param $store
     * @param $days
     */
    private function rollback($feed, $store, $days) {
        $date = today()->subDays($days)->toDateString();

        $previousFeedFile = $store->code.'_'.$date;
        $previousFeedFile = storage_path().'/feeds/'.$feed.'/'.$previousFeedFile;

        $type = '';
        if(File::exists($previousFeedFile.'.txt')) {
            $type = '.txt';
        } else if(File::exists($previousFeedFile.'.csv')) {
            $type = '.csv';
        } else {
            $this->error("There is no $store->code $feed feed file for $date");
        }

        $this->putToS3($previousFeedFile.$type, $feed.'/'.$store->code.$type);
    }

    /**
     * @param $source
     * @param $destination
     */
    private function putToS3($source, $destination)
    {
        $this->s3Client->putObject(
            [
                'Bucket' => 'nubuiten-feeds',
                'Key' => $destination,
                'SourceFile' => $source,
                'ACL' => 'public-read',
            ]
        );
    }
}
