<?php

namespace Daalder\Feeds\Commands;

use Daalder\Feeds\Services\FeedsHandler;
use Illuminate\Console\Command;
use Throwable;

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


    /**
     * The console command signature
     *
     * @var string
     */
    protected $signature = 'feeds:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the enabled feeds for the enabled stores.';

    private FeedsHandler $feedsHandler;

    public function __construct(FeedsHandler $feedsHandler)
    {
        parent::__construct();
        $this->feedsHandler = $feedsHandler;
    }

    public function handle(): void
    {
        try {
            [$feeds, $stores] = $this->feedsHandler->generateFeeds();
            $this->info('Queued '. count($feeds) * $stores->count() . ' feeds.');
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
    }
}
