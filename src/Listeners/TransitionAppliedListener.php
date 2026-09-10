<?php

namespace Uspdev\Workflow\Listeners;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Uspdev\Workflow\Events\TransitionAppliedEvent;
use Uspdev\Workflow\Notifications\TransitionAppliedNotification;


class TransitionAppliedListener implements ShouldQueue
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
    public function handle(TransitionAppliedEvent $event): void
    {
        $workflowObj = $event->workflowObject;
        
        $role_users = User::all()->filter(function ($user) use ($event) {
            return $user->hasAnyRole($event->destRoles);
            });

        foreach($role_users as $user)
        {
            $user->notify(new TransitionAppliedNotification(
                transitionLabel: $event->transitionLabel,
                workflowObjectId: $workflowObj->id,
                fromPlace: $event->fromPlace,
                toPlace: $event->toPlace
            ));
        }
    }
}
