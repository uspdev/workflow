<?php
namespace Uspdev\Workflow;

use Uspdev\Workflow\Console\Commands\WorkflowSync;
use Illuminate\Support\ServiceProvider;
use Uspdev\Workflow\Providers\EventServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__.'/../config/workflow.php' => config_path('uspdev-workflow.php'),
        ], 'workflow-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'workflow-migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views','uspdev-workflow');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Publica o comando de WorkflowSync
        $this->commands([
            WorkflowSync::class,
        ]);

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/uspdev-workflow.php', 'uspdev-workflow'
        );
    }
}
