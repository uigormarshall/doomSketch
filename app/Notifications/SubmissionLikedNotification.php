<?php

namespace App\Notifications;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubmissionLikedNotification extends Notification
{
    use Queueable;

    public function __construct(public User $liker, public Submission $submission) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'submission_liked',
            'user_id' => $this->liker->id,
            'user_name' => $this->liker->name,
            'user_username' => $this->liker->username,
            'submission_id' => $this->submission->id,
            'url' => route('art.show', $this->submission),
        ];
    }
}
