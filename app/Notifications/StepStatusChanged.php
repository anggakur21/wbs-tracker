<?php

namespace App\Notifications;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * In-app notice about a WBS step's review: submitted (to TIF admins), accepted or returned
 * for revision (to the partner's Project Managers). Shown under the bell in the sidebar.
 */
class StepStatusChanged extends Notification
{
    public const SUBMITTED = 'submitted';

    public const ACCEPTED = 'accepted';

    public const RETURNED = 'returned';

    public function __construct(
        private readonly string $event,
        private readonly Activity $activity,
        private readonly User $actor,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $deliverable = $this->activity->deliverable;
        $project = $deliverable->project;

        return [
            'event' => $this->event,
            'title' => match ($this->event) {
                self::SUBMITTED => 'Submitted for review: '.$this->activity->name,
                self::ACCEPTED => 'Accepted: '.$this->activity->name,
                self::RETURNED => 'Returned for revision: '.$this->activity->name,
            },
            'message' => match ($this->event) {
                self::SUBMITTED => $this->actor->name.' submitted this step with evidence. Review and accept it or return it.',
                self::ACCEPTED => $this->actor->name.' accepted the evidence. The step is done.',
                self::RETURNED => $this->actor->name.' returned it: '.$this->reason,
            },
            'where' => $project->name.' · '.$deliverable->name,
            'activity_id' => $this->activity->id,
            'url' => route('deliverables.show', $deliverable).'#act-'.$this->activity->id,
            'actor' => $this->actor->name,
        ];
    }
}
