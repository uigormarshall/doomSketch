<?php

use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Explore challenges')] class extends Component {
    use WithPagination;

    #[Computed]
    public function challenges()
    {
        return Challenge::query()
            ->where('is_private', false)
            ->where('creator_id', '!=', Auth::id())
            ->with(['creator', 'originalChallenge:id,title'])
            ->withCount(['days', 'likes', 'comments'])
            ->latest()
            ->paginate(12);
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <header class="flex items-end justify-between">
            <div>
                <flux:heading size="xl">{{ __('Explore') }}</flux:heading>
                <flux:text>{{ __('Public challenges from the community.') }}</flux:text>
            </div>
            <flux:button :href="route('challenges.create')" wire:navigate variant="primary" icon="plus">
                {{ __('New challenge') }}
            </flux:button>
        </header>

        @if ($this->challenges->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-700 p-12 text-center">
                <flux:text>{{ __('No public challenges yet. Be the first to create one!') }}</flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->challenges as $challenge)
                    <article class="group relative aspect-[4/3] overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 transition hover:border-zinc-500">
                        {{-- Background: cover, palette stripes, or plain gradient --}}
                        @if ($challenge->coverUrl())
                            <img
                                src="{{ $challenge->coverUrl() }}"
                                alt=""
                                loading="lazy"
                                class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                        @elseif ($challenge->has_palette && $challenge->palette_colors)
                            <div class="absolute inset-0 flex">
                                @foreach ($challenge->palette_colors as $hex)
                                    <div class="flex-1" style="background-color: {{ $hex }}"></div>
                                @endforeach
                            </div>
                        @else
                            <div class="absolute inset-0 bg-gradient-to-br from-zinc-700 to-zinc-900"></div>
                        @endif

                        {{-- Darkening gradient for legibility --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/50 to-black/10 transition group-hover:from-black/95"></div>

                        {{-- Content --}}
                        <div class="absolute inset-0 flex flex-col justify-end gap-3 p-4">
                            <div class="space-y-1">
                                <a href="{{ route('challenges.template', $challenge) }}" wire:navigate class="block">
                                    <flux:heading size="lg" class="line-clamp-2 text-white drop-shadow hover:underline">
                                        {{ $challenge->title }}
                                    </flux:heading>
                                </a>
                                <div class="flex items-center gap-2 text-xs text-white/70">
                                    <span>{{ __('by :name', ['name' => $challenge->creator->name]) }}</span>
                                    <span>·</span>
                                    <span>{{ __(':n days', ['n' => $challenge->duration_days]) }}</span>
                                    <span class="inline-flex items-center gap-0.5"><flux:icon.heart variant="micro" />{{ $challenge->likes_count }}</span>
                                    <span class="inline-flex items-center gap-0.5"><flux:icon.chat-bubble-left variant="micro" />{{ $challenge->comments_count }}</span>
                                </div>
                                @if ($challenge->originalChallenge)
                                    <div class="text-[11px] text-white/50">
                                        {{ __('Cloned from :title', ['title' => $challenge->originalChallenge->title]) }}
                                    </div>
                                @endif
                            </div>

                            {{-- Palette + actions: always shown on touch, revealed on hover on desktop --}}
                            <div class="space-y-3 transition duration-200 lg:translate-y-1 lg:opacity-0 lg:group-hover:translate-y-0 lg:group-hover:opacity-100 lg:group-focus-within:translate-y-0 lg:group-focus-within:opacity-100">
                                @if ($challenge->has_palette && $challenge->palette_colors)
                                    <x-palette-preview :colors="$challenge->palette_colors" size="sm" />
                                @endif
                                <div class="flex gap-2">
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="arrow-right"
                                        class="flex-1"
                                        :href="route('challenges.template', $challenge)"
                                        wire:navigate
                                    >
                                        {{ __('Access') }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="document-duplicate"
                                        class="bg-white/10 text-white hover:bg-white/20"
                                        :href="route('challenges.clone', $challenge)"
                                        wire:navigate
                                    >
                                        {{ __('Copy') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div>{{ $this->challenges->links() }}</div>
        @endif
    </div>
</div>
