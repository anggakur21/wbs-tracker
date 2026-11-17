<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to a partner's Project Manager when a TIF admin returns a WBS step for revision.
 * Sent alongside the in-app notice (StepStatusChanged), only to people with an email address.
 */
class StepReturnedEmail extends Notification
{
    public function __construct(
        private readonly Activity $activity,
        private readonly User $admin,
        private readonly string $reason,
        private readonly bool $wasDone = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deliverable = $this->activity->deliverable;
        $project = $deliverable->project;
        $url = route('deliverables.show', $deliverable).'#act-'.$this->activity->id;

        return (new MailMessage)
            ->subject('Revision needed: '.$this->activity->name.' ('.$project->name.')')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->admin->name.' (TIF PMO) '.($this->wasDone ? 'reopened' : 'returned').' a WBS step for revision. It is back In Progress.')
            ->line('**Step:** '.$this->activity->name)
            ->line('**Project:** '.$project->name.' · '.$deliverable->name)
            ->line('**Reason:**')
            ->line($this->reason)
            ->action('Open the step', $url)
            ->line('Revise the document, upload the new version, then submit the step for review again.')
            ->salutation('TIF PMO · WBS Tracker');
    }
}
