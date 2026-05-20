<?php

namespace App\Notifications;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubmissionCommentedNotification extends Notification
{
    use Queueable;

    public function __construct(public User $author, public Submission $submission) {}

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
            'type' => 'submission_commented',
            'user_id' => $this->author->id,
            'user_name' => $this->author->name,
            'user_username' => $this->author->username,
            'submission_id' => $this->submission->id,
            'url' => route('art.show', $this->submission),
        ];
    }
}
