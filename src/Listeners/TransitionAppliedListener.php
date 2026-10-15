<?php

namespace Uspdev\Workflow\Listeners;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Uspdev\Workflow\Events\TransitionAppliedEvent;
use Uspdev\Workflow\Models\WorkflowDefinition;
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

        $notification = new TransitionAppliedNotification(
                transitionLabel: $event->transitionLabel,
                workflowObjectId: $workflowObj->id,
                fromPlace: $event->fromPlace,
                toPlace: $event->toPlace
            );

        /** @var WorkflowDefinition */
        $workflowDef = $workflowObj->definition;
        
        $users = User::all()->filter(function ($user) use ($event, $workflowDef) {
                return $workflowDef->verifyRole($event->dest['roles'], $user);
            });

        $emails = $event->dest['emails'];
        
        foreach($users as $user)
        {
            $user->notify($notification);
        }

        foreach($emails as $email)
        { 
            
            Notification::route('mail', $email)->notify($notification); 
        }
    }
}
