<?php

namespace Daalder\EnvironmentSyncer\Commands;

use Carbon\CarbonInterface;
use Daalder\EnvironmentSyncer\Exceptions\EnvironmentSyncerException;
use Daalder\EnvironmentSyncer\Jobs\SyncWordpress;
use Daalder\EnvironmentSyncer\Services\DatabaseSyncer;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Arr;
use Rahul900Day\LaravelConsoleSpinner\Spinner;

class GenerateFeeds extends Command
{
    use DispatchesJobs;

    protected $name = 'feeds:generate';
    protected $signature = 'feeds:generate';
    protected $description = 'Generate enabled feeds for enabled stores.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

    }
}
