<?php

namespace Daalder\Feeds\Commands;

use Daalder\Feeds\Jobs\ValidateFeedsCreated;
use Daalder\Feeds\Services\FeedsHandler;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Pionect\Daalder\Events\CommandHeartBeat;
use Pionect\Daalder\Models\Store\Store;

/**
 * Class GenerateFeeds.
 */
class GenerateFeedsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'feeds:generate';

    protected $signature = 'feeds:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the enabled feeds for the enabled stores.';
    /**
     * @var FeedsHandler
     */
    private $feedsHandler;

    public function __construct(FeedsHandler $feedsHandler)
    {
        parent::__construct();
        $this->feedsHandler = $feedsHandler;
    }


    /**
     * @return void
     * @throws \Throwable
     */
    public function handle()
    {
        [$feeds, $stores] = $this->feedsHandler->generateFeeds();


        $this->info('Queued '. count($feeds) * $stores->count() . ' feeds.');
    }
}
