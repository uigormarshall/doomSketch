<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChallengeCommentedNotification extends Notification
{
    use Queueable;

    public function __construct(public User $author, public Challenge $challenge) {}

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
            'type' => 'challenge_commented',
            'user_id' => $this->author->id,
            'user_name' => $this->author->name,
            'user_username' => $this->author->username,
            'challenge_id' => $this->challenge->id,
            'challenge_title' => $this->challenge->title,
            'url' => route('challenges.template', $this->challenge),
        ];
    }
}
