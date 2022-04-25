<?php

namespace Daalder\Feeds\Commands;

use Daalder\Feeds\Jobs\ValidateFeedsCreated;
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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $feeds = config('daalder-feeds.enabled-feeds');
        $stores = Store::query()
            ->whereIn('code', config('daalder-feeds.enabled-store-codes'))
            ->get();

        $batch = [];

        foreach($feeds as $feed) {
            foreach($stores as $store) {
                $batch[] = new $feed($store);
            }
        }

        Bus::batch($batch)
            ->name('Generate feeds')
            ->finally(function(Batch $batch) {
                if(config('daalder-feeds.validate-feeds.enabled') === true) {
                    ValidateFeedsCreated::dispatchSync();
                }

                event(new CommandHeartBeat('feeds-generated'));
            })
            ->onQueue('medium')
            ->allowFailures()
            ->dispatch();

        $this->info('Queued '. count($feeds) * $stores->count() . ' feeds.');
    }
}
