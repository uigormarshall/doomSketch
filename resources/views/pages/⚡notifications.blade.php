<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifications')] class extends Component {
    #[Computed(persist: false)]
    public function notifications()
    {
        return Auth::user()->notifications()->latest()->limit(100)->get();
    }

    #[Computed(persist: false)]
    public function actors()
    {
        $ids = $this->notifications->pluck('data.user_id')->filter()->unique();

        return User::whereIn('id', $ids)->get(['id', 'name', 'username', 'avatar_path'])->keyBy('id');
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        unset($this->notifications);
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-2xl space-y-4 p-6">
        <header class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>
            @if ($this->notifications->whereNull('read_at')->isNotEmpty())
                <flux:button size="sm" variant="ghost" wire:click="markAllRead">
                    {{ __('Mark all read') }}
                </flux:button>
            @endif
        </header>

        @if ($this->notifications->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-700 p-10 text-center">
                <flux:text class="opacity-70">{{ __('No notifications yet.') }}</flux:text>
            </div>
        @else
            <ul class="divide-y divide-zinc-800 rounded-xl border border-zinc-700 bg-zinc-900">
                @foreach ($this->notifications as $notification)
                    @php
                        $d = $notification->data;
                        $actor = $this->actors[$d['user_id'] ?? null] ?? null;
                    @endphp
                    <li>
                        <a
                            href="{{ $d['url'] ?? '#' }}"
                            wire:navigate
                            class="flex items-start gap-3 px-4 py-3 transition hover:bg-zinc-800 {{ $notification->read_at ? 'opacity-60' : '' }}"
                        >
                            <x-notification-avatar :user="$actor" :unread="! $notification->read_at" />
                            <div class="flex-1">
                                <flux:text size="sm">
                                    <strong>{{ $d['user_name'] ?? __('Someone') }}</strong>
                                    @switch($d['type'] ?? '')
                                        @case('follow') {{ __('started following you.') }} @break
                                        @case('challenge_liked') {{ __('liked your challenge :title.', ['title' => $d['challenge_title'] ?? '']) }} @break
                                        @case('challenge_commented') {{ __('commented on your challenge :title.', ['title' => $d['challenge_title'] ?? '']) }} @break
                                        @case('submission_liked') {{ __('liked your art.') }} @break
                                        @case('submission_commented') {{ __('commented on your art.') }} @break
                                    @endswitch
                                </flux:text>
                                <flux:text size="xs" class="opacity-50">{{ $notification->created_at->diffForHumans() }}</flux:text>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
