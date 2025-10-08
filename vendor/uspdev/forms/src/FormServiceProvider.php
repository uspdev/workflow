<?php

namespace Uspdev\Forms;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use \Spatie\Activitylog\Models\Activity;
use Uspdev\Forms\Providers\EventServiceProvider;

class FormServiceProvider extends ServiceProvider
{
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
            __DIR__ . '/../config/uspdev-forms.php' => config_path('uspdev-forms.php'),
        ], 'forms-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'forms-migrations');

        // Load migrations
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'uspdev-forms');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Uspdev\Forms\Console\Commands\FormDemo::class,
            ]);
        }

        // Registra a diretiva
        // para chamar use @submissionsTable($form) 
        Blade::directive('submissionsTable', function ($form) {
            return "<?php echo view('uspdev-forms::partials.submissions-table', ['form' => $form])->render(); ?>";
        });

        // https://github.com/spatie/laravel-activitylog/issues/39
        Activity::saving(function (Activity $activity) {
            $activity->properties = $activity->properties->put('agent', [
                'ip' => Request()->ip()
            ]);
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/uspdev-forms.php',
            'uspdev-forms'
        );
    }
}
