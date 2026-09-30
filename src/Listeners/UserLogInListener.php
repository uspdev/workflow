<?php

namespace Uspdev\Workflow\Listeners;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Uspdev\SenhaunicaSocialite\Events\SenhaunicaUsuarioLogado;
use Uspdev\Workflow\Enums\WorkflowStatus;
use Uspdev\Workflow\Models\WorkflowDefinition;


class UserLogInListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SenhaunicaUsuarioLogado $event): void
    {
        $workflowDefs = WorkflowDefinition::all()->where('status', WorkflowStatus::PUBLISHED);
        foreach($workflowDefs as $workflowDef)
        {
            $workflowDef->bindRoleWithPerms($event->getUser());
        }
    }
}
