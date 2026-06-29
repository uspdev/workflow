<?php

namespace Uspdev\Workflow;

use Uspdev\Workflow\Console\Commands\WorkflowSync;
use Illuminate\Support\ServiceProvider;
use Uspdev\Workflow\Providers\EventServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{

    public static $resolvers = [];

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->register(EventServiceProvider::class);

        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/workflow.php' => config_path('uspdev-workflow.php'),
        ], 'workflow-config');

        // Publish migrations
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'workflow-migrations');
        }
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'uspdev-workflow');

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');


        // Publica o comando de WorkflowSync
        $this->commands([
            WorkflowSync::class,
        ]);

        $this->registerInternalResolvers();
    }

    protected function registerInternalResolvers()
    {
        // Registra os resolvers padrão do ecossistema USP
        self::registerResolver('user_by_codpes', function ($value) {
            // Busca o ID do usuário local usando o número USP enviado pelo form
            return \App\Models\User::where('codpes', $value)->first()?->id;
        });

        self::registerResolver('direct', function ($value) {
            return $value; // Apenas retorna o valor sem mudar nada
        });
    }

    /**
     * Permite que outros sistemas da USP registrem seus próprios resolvers
     */
    public static function registerResolver(string $name, callable $callback): void
    {
        self::$resolvers[$name] = $callback;
    }

    /**
     * Executa um resolver registrado
     */
    public static function resolve(string $name, mixed $value): mixed
    {
        if (!isset(self::$resolvers[$name])) {
            // Se não achar o resolver, por segurança, retorna o valor bruto
            return $value;
        }

        return call_user_func(self::$resolvers[$name], $value);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/uspdev-workflow.php',
            'uspdev-workflow'
        );

        $this->app->singleton('workflow', function ($app) {
            return new WorkflowService();
        });
        $this->app->alias('workflow', WorkflowService::class);
    }
}
