<?php

namespace Uspdev\Forms\Providers;

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

        // Adiciona o item "Formulários" no menu se a chave uspdev-forms estiver disponível
        Event::listen(function (UspThemeParseKey $event) {
            if (isset($event->item['key']) && $event->item['key'] == 'uspdev-forms') {
                $event->item = [
                    'text' => '<span class="text-danger"><i class="fas fa-clipboard-list"></i></span>',
                    'url' => route('form-definitions.index'),
                    'title' => 'Formulários',
                    'can' => config('uspdev-forms.adminGate'), // controla permissão via Gate
                ];
            }
            return $event->item;
        });
    }
}
