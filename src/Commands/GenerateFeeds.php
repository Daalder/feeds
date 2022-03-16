<?php

namespace Daalder\Feeds\Commands;

use Illuminate\Console\Command;
use Pionect\Daalder\Models\Store\Store;

/**
 * Class GenerateFeeds.
 */
class GenerateFeeds extends Command
{
    use DispatchesJobs;

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
        $feeds = config('feeds.enabled-feeds');
        $stores = Store::query()->whereIn('id', config('feeds.enabled-stores-ids'));

        foreach($feeds as $feed) {
            foreach($stores as $store) {
                $feedName = get_class_name($feed);
                $this->info('Start '.$feedName.' for '.$store->code.'.');

                dispatch($feed::dispatch($store));
            }
        }
    }
}
