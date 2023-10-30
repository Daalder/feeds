<?php

namespace Daalder\Feeds\Tests;

use Astrotomic\Translatable\TranslatableServiceProvider;
use Daalder\Feeds\FeedsServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Pionect\Daalder\DaalderServiceProvider;
use Pionect\Daalder\ServiceProviders\ElasticScoutConfigServiceProvider;
use Pionect\Daalder\Services\CustomerToken\CustomerTokenResolver;
use Pionect\Daalder\Tests\TestCase as DaalderTestCase;
use Pionect\Daalder\Tests\TestCustomerTokenResolver;
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
        foreach (File::files(daalder_path('config')) as $config) {
            if ($config->getExtension() == 'php') {
                $key = str_replace('.php', '', $config->getFilename());
                $default = config()->get($key, []);
                config()->set($key, array_merge($default, require $config->getRealPath()));
            }
        }

        $app['config']->set('app.faker_locale', 'nl_NL');

        $orchestra = __DIR__ . '/../vendor/orchestra/testbench-core/laravel';
        $migrationDirectory = realpath(__DIR__ . '/../vendor/pionect/daalder/database/migrations');
        $migrations = array_diff(scandir($migrationDirectory), ['..', '.']);
        foreach ($migrations as $migration) {
            copy($migrationDirectory . '/' . $migration, $orchestra . '/database/migrations/' . $migration);
        }

        copy(__DIR__ . '/../vendor/pionect/daalder/tests/storage/oauth-private.key', $orchestra . '/storage/oauth-private.key');
        copy(__DIR__ . '/../vendor/pionect/daalder/tests/storage/oauth-public.key', $orchestra . '/storage/oauth-public.key');

        // Fonts directory is required for the DOMPDF package
        if(!file_exists(__DIR__.'/../vendor/orchestra/testbench-core/laravel/storage/fonts/')) {
            mkdir(__DIR__.'/../vendor/orchestra/testbench-core/laravel/storage/fonts/');
        }

        $app->singleton(CustomerTokenResolver::class, TestCustomerTokenResolver::class);
    }

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [FeedsServiceProvider::class]);
    }

    /**
     * Teardown the test environment
     */
    protected function tearDown(): void
    {
        // Remove all feeds that were generated in the last test
        if(File::exists(storage_path('feeds'))) {
            File::deleteDirectory(storage_path('feeds'));
        }

        parent::tearDown();
    }
}
