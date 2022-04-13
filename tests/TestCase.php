<?php

namespace Daalder\Feeds\Tests;

use Astrotomic\Translatable\TranslatableServiceProvider;
use Daalder\Feeds\FeedsServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Database\ConsoleServiceProvider;
use Pionect\Daalder\DaalderServiceProvider;
use Pionect\Daalder\ServiceProviders\ElasticScoutConfigServiceProvider;
use Pionect\Daalder\Tests\TestCase as DaalderTestCase;
use ScoutElastic\ScoutElasticServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends DaalderTestCase
{
    protected function refreshTestDatabase()
    {
        $locale = app()->getLocale();

        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('vendor:publish', [
                '--provider' => PermissionServiceProvider::class
            ]);

            // A Daalder migration (2021_09_18_175336_add_wizard_permissions.php) expects the permissions table to exist.
            // Make sure the migration for that is run before the Daalder one.
            $fromFileName = glob(__DIR__ . '/../vendor/orchestra/testbench-core/laravel/database/migrations/*_create_permission_tables.php')[0];
            $toFileName = __DIR__ . '/../vendor/orchestra/testbench-core/laravel/database/migrations/2021_09_18_000000_create_permission_tables.php';
            rename($fromFileName, $toFileName);

            $this->artisan('migrate:fresh', [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ]);

            $this->artisan('db:seed');
            // Do full ES sync now
            $this->artisan('elastic:sync --drop --create');

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        // The locale is modified in the artisan(migrate:fresh) command. Change it back.
        app()->setLocale($locale);

        $this->beginDatabaseTransaction();
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        foreach (File::files(__DIR__ . '/../vendor/pionect/daalder/config') as $config) {
            if ($config->getExtension() == 'php') {
                $key = str_replace('.php', '', $config->getFilename());
                $default = config()->get($key, []);
                config()->set($key, array_merge($default, require $config->getRealPath()));
            }
        }

        $orchestra = __DIR__ . '/../vendor/orchestra/testbench-core/laravel';
        $migrationDirectory = realpath(__DIR__ . '/../vendor/pionect/daalder/database/migrations');
        $migrations = array_diff(scandir($migrationDirectory), ['..', '.']);
        foreach ($migrations as $migration) {
            copy($migrationDirectory . '/' . $migration, $orchestra . '/database/migrations/' . $migration);
        }

        copy(__DIR__ . '/../vendor/pionect/daalder/tests/storage/oauth-private.key', $orchestra . '/storage/oauth-private.key');
        copy(__DIR__ . '/../vendor/pionect/daalder/tests/storage/oauth-public.key', $orchestra . '/storage/oauth-public.key');
    }

    protected function getPackageProviders($app): array
    {
        return [
            DaalderServiceProvider::class,
            ScoutServiceProvider::class,
            ElasticScoutConfigServiceProvider::class,
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            TranslatableServiceProvider::class,
            FeedsServiceProvider::class,
            ConsoleServiceProvider::class,
            ScoutElasticServiceProvider::class,
        ];
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

//        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
