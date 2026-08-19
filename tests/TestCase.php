<?php

namespace Uspdev\Workflow\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Uspdev\Forms\FormServiceProvider;
use Uspdev\Workflow\WorkflowServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FormServiceProvider::class,
            WorkflowServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('activitylog', [
            'enabled' => false,
            'delete_records_older_than_days' => 365,
            'default_log_name' => 'default',
            'default_auth_driver' => null,
            'subject_returns_soft_deleted_models' => false,
            'activity_model' => \Spatie\Activitylog\Models\Activity::class,
            'table_name' => 'activity_log',
            'database_connection' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }
}
