<?php

namespace Daalder\Feeds\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class PurgeOldExportFeedsCommand.
 */
class PurgeOldExportFeedsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'feeds:purge';

    protected $signature = 'feeds:purge {days=7}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old export feeds before x days.';

    /**
     * GenerateExportFeedsCommand constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $till = now()->subDays($this->argument('days'));

        $deletedFileCount = 0;

        $files = \File::allFiles(storage_path().'/feeds');

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $date = Str::before(Str::afterLast($file->getFilename(), '_'), '.');
            if ((new Carbon($date))->isBefore($till)) {
                try {
                    unlink($file->getRealPath());
                    $deletedFileCount++;
                } catch (\Exception $e) {
                    // File could not be removed.
                }
            }
        }

        $this->info($deletedFileCount.' feeds removed.');

        return 0;
    }
}
