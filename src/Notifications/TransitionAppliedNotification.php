<?php

namespace Uspdev\Workflow\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use function PHPUnit\Framework\isArray;

class TransitionAppliedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $transitionLabel,
        public int $workflowObjectId,
        public array|string $fromPlace,
        public array|string $toPlace,
    )
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
        public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $toPlaceString = '';
        $fromPlaceString = '';
        
        is_array($this->toPlace) ? $toPlaceString = implode(', ', $this->toPlace) : $toPlaceString = $this->toPlace;

        is_array($this->fromPlace) ? $fromPlaceString = implode(', ', $this->fromPlace) : $fromPlaceString = $this->fromPlace;


        return (new MailMessage)
            ->line("A transição '{$this->transitionLabel}' foi aplicada ao objeto de workflow com ID '{$this->workflowObjectId}', o tirando de '{$fromPlaceString}' para '{$toPlaceString}'.")
            ->action('Ver objeto de workflow', route('workflows.showObject', ['id' => $this->workflowObjectId]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
