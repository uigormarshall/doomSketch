<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserFollowedYou extends Notification
{
    use Queueable;

    public function __construct(public User $follower) {}

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
            'type' => 'follow',
            'user_id' => $this->follower->id,
            'user_name' => $this->follower->name,
            'user_username' => $this->follower->username,
            'url' => route('user.profile', $this->follower->username),
        ];
    }
}
