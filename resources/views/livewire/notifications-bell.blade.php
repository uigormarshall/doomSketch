<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed(persist: false)]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    #[Computed(persist: false)]
    public function recent()
    {
        return Auth::user()->notifications()->latest()->limit(10)->get();
    }

    #[Computed(persist: false)]
    public function actors()
    {
        $ids = $this->recent->pluck('data.user_id')->filter()->unique();

        return User::whereIn('id', $ids)->get(['id', 'name', 'username', 'avatar_path'])->keyBy('id');
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        unset($this->unreadCount, $this->recent);
    }

    public function markRead(string $id): void
    {
        Auth::user()->notifications()->where('id', $id)->update(['read_at' => now()]);
        unset($this->unreadCount, $this->recent);
    }
}; ?>

<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" size="sm" icon="bell" class="relative">
        @if ($this->unreadCount > 0)
            <span class="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
    </flux:button>

    <flux:menu class="w-80">
        <div class="flex items-center justify-between px-2 py-1">
            <flux:text class="font-semibold">{{ __('Notifications') }}</flux:text>
            @if ($this->unreadCount > 0)
                <flux:link size="xs" wire:click.prevent="markAllRead" class="cursor-pointer">
                    {{ __('Mark all read') }}
                </flux:link>
            @endif
        </div>
        <flux:menu.separator />

        @if ($this->recent->isEmpty())
            <div class="px-2 py-4 text-center">
                <flux:text size="sm" class="opacity-60">{{ __('No notifications yet.') }}</flux:text>
            </div>
        @else
            @foreach ($this->recent as $notification)
                @php
                    $d = $notification->data;
                    $actor = $this->actors[$d['user_id'] ?? null] ?? null;
                @endphp
                <a
                    href="{{ $d['url'] ?? '#' }}"
                    wire:click="markRead('{{ $notification->id }}')"
                    wire:navigate
                    class="flex items-center gap-2 px-2 py-2 text-sm transition hover:bg-zinc-800 {{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    <x-notification-avatar :user="$actor" size="sm" :unread="! $notification->read_at" />
                    <div class="flex-1">
                        <div>
                            <strong>{{ $d['user_name'] ?? __('Someone') }}</strong>
                            @switch($d['type'] ?? '')
                                @case('follow') {{ __('started following you.') }} @break
                                @case('challenge_liked') {{ __('liked your challenge :title.', ['title' => $d['challenge_title'] ?? '']) }} @break
                                @case('challenge_commented') {{ __('commented on your challenge :title.', ['title' => $d['challenge_title'] ?? '']) }} @break
                                @case('submission_liked') {{ __('liked your art.') }} @break
                                @case('submission_commented') {{ __('commented on your art.') }} @break
                            @endswitch
                        </div>
                        <div class="text-xs opacity-50">{{ $notification->created_at->diffForHumans() }}</div>
                    </div>
                </a>
            @endforeach

            <flux:menu.separator />
            <flux:menu.item :href="route('notifications.index')" wire:navigate icon="inbox">
                {{ __('See all') }}
            </flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
