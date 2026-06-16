<?php

namespace Uspdev\Workflow\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Uspdev\UspTheme\Events\UspThemeParseKey;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        // parent::boot();

        // Adiciona o item "Workflows" no menu se a chave uspdev-workflow estiver disponível
        Event::listen(function (UspThemeParseKey $event) {
            if (isset($event->item['key']) && $event->item['key'] == 'uspdev-workflow') {
                $event->item = [
                    'text' => '<span class="text-danger">Workflows</span>',
                    'url' => route('workflows.list-definitions'),
                    'title' => 'Workflows',
                    'can' => config('uspdev-workflow.adminGate'), // controla permissão via Gate
                ];
            }
            return $event->item;
        });
    }
}
