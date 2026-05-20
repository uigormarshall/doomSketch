<?php

use App\Models\Challenge;
use App\Models\ChallengeComment;
use App\Models\ChallengeLike;
use App\Models\Submission;
use App\Notifications\ChallengeCommentedNotification;
use App\Notifications\ChallengeLikedNotification;
use App\Services\ChallengeService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Challenge')] class extends Component {
    public Challenge $challenge;

    public string $newComment = '';

    public string $startDate = '';

    public function mount(Challenge $challenge): void
    {
        if ($challenge->is_private && $challenge->creator_id !== Auth::id()) {
            abort(404);
        }

        $this->challenge = $challenge->load(['creator', 'days', 'originalChallenge.creator']);
        $this->startDate = now()->toDateString();
    }

    #[Computed]
    public function submissionsByDay()
    {
        return Submission::query()
            ->whereHas('userChallenge', fn ($q) => $q->where('challenge_id', $this->challenge->id))
            ->with(['userChallenge.user', 'challengeDay:id,day_number'])
            ->withCount(['likes', 'comments'])
            ->latest()
            ->limit(120)
            ->get()
            ->groupBy(fn (Submission $s) => $s->challengeDay->day_number)
            ->sortKeys();
    }

    #[Computed]
    public function submissionsCount(): int
    {
        return Submission::query()
            ->whereHas('userChallenge', fn ($q) => $q->where('challenge_id', $this->challenge->id))
            ->count();
    }

    #[Computed]
    public function comments()
    {
        return $this->challenge
            ->comments()
            ->with('user')
            ->latest()
            ->get();
    }

    #[Computed]
    public function likesCount(): int
    {
        return $this->challenge->likes()->count();
    }

    #[Computed]
    public function commentsCount(): int
    {
        return $this->challenge->comments()->count();
    }

    #[Computed]
    public function isLiked(): bool
    {
        return $this->challenge->isLikedBy(Auth::user());
    }

    #[Computed]
    public function isOwn(): bool
    {
        return $this->challenge->creator_id === Auth::id();
    }

    public function toggleLike(): void
    {
        $existing = ChallengeLike::where('user_id', Auth::id())
            ->where('challenge_id', $this->challenge->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ChallengeLike::create([
                'user_id' => Auth::id(),
                'challenge_id' => $this->challenge->id,
            ]);

            if ($this->challenge->creator_id !== Auth::id()) {
                $this->challenge->creator->notify(new ChallengeLikedNotification(Auth::user(), $this->challenge));
            }
        }

        unset($this->isLiked, $this->likesCount);
    }

    public function addComment(): void
    {
        $validated = $this->validate([
            'newComment' => ['required', 'string', 'max:500'],
        ]);

        ChallengeComment::create([
            'user_id' => Auth::id(),
            'challenge_id' => $this->challenge->id,
            'body' => $validated['newComment'],
        ]);

        if ($this->challenge->creator_id !== Auth::id()) {
            $this->challenge->creator->notify(new ChallengeCommentedNotification(Auth::user(), $this->challenge));
        }

        $this->newComment = '';
        unset($this->comments, $this->commentsCount);
    }

    public function deleteComment(int $commentId): void
    {
        $comment = ChallengeComment::where('id', $commentId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $comment->delete();
        unset($this->comments, $this->commentsCount);
    }

    public function openStartModal(): void
    {
        $this->startDate = now()->toDateString();
        Flux::modal('start-template-from-detail')->show();
    }

    public function confirmStart(ChallengeService $service): void
    {
        $validated = $this->validate([
            'startDate' => ['required', 'date'],
        ]);

        $userChallenge = $service->startChallenge(
            Auth::user(),
            $this->challenge,
            \Carbon\CarbonImmutable::parse($validated['startDate']),
        );

        Flux::modal('start-template-from-detail')->close();
        Flux::toast(variant: 'success', text: __('Challenge accepted.'));

        $this->redirectRoute('challenge.show', $userChallenge, navigate: true);
    }

}; ?>

<div>
    <div class="mx-auto w-full max-w-4xl space-y-8 p-6">
        <header class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <flux:heading size="xl">{{ $challenge->title }}</flux:heading>
                    <flux:text class="opacity-70">
                        {{ __('by') }} <x-user-link :user="$challenge->creator" class="font-medium" />
                        · {{ __(':n days', ['n' => $challenge->duration_days]) }}
                        @if ($challenge->is_private)
                            · <flux:badge size="sm" color="zinc" icon="lock-closed" inline>{{ __('Private') }}</flux:badge>
                        @endif
                    </flux:text>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button
                        size="sm"
                        :variant="$this->isLiked ? 'primary' : 'ghost'"
                        icon="heart"
                        wire:click="toggleLike"
                    >
                        {{ $this->likesCount }}
                    </flux:button>
                </div>
            </div>

            @if ($challenge->originalChallenge)
                <flux:text size="sm" class="opacity-70">
                    {!! __('Cloned from :title by :name', [
                        'title' => '<a href="'.route('challenges.template', $challenge->originalChallenge).'" wire:navigate class="underline">'.e($challenge->originalChallenge->title).'</a>',
                        'name' => e($challenge->originalChallenge->creator->name),
                    ]) !!}
                </flux:text>
            @endif

            @if ($challenge->description)
                <flux:text class="opacity-80">{{ $challenge->description }}</flux:text>
            @endif

            @if ($challenge->has_palette && $challenge->palette_colors)
                <div class="space-y-2 rounded-lg border border-zinc-700 p-3">
                    @if ($challenge->palette_name)
                        <flux:text size="sm" class="opacity-70">
                            {{ $challenge->palette_name }} · {{ __('click a color to copy') }}
                        </flux:text>
                    @endif
                    <x-palette-preview :colors="$challenge->palette_colors" size="lg" :clickable="true" />
                    @if ($challenge->palette_source_url)
                        <flux:text size="xs" class="opacity-60">
                            <a
                                href="{{ $challenge->palette_source_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 underline hover:opacity-100"
                            >
                                <flux:icon.arrow-top-right-on-square variant="micro" />
                                {{ __('Palette source') }}
                            </a>
                        </flux:text>
                    @endif
                </div>
            @endif

            @if (! $this->isOwn)
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="primary" wire:click="openStartModal">
                        {{ __('Accept') }}
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        icon="document-duplicate"
                        :href="route('challenges.clone', $challenge)"
                        wire:navigate
                    >
                        {{ __('Clone and customize') }}
                    </flux:button>
                </div>
            @else
                <flux:text size="sm" class="opacity-60">{{ __('You created this challenge.') }}</flux:text>
            @endif
        </header>

        <flux:separator />

        <section class="space-y-3">
            <flux:heading size="lg">{{ __('Daily prompts') }}</flux:heading>
            <ol class="space-y-1">
                @foreach ($challenge->days as $day)
                    <li class="flex gap-3 rounded-md border border-zinc-700 bg-zinc-900 p-3">
                        <span class="font-mono text-sm opacity-60">{{ str_pad($day->day_number, 2, '0', STR_PAD_LEFT) }}</span>
                        <span>{{ $day->prompt_text }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($this->submissionsCount > 0)
            <flux:separator />

            <section
                class="space-y-4"
                x-data="{ active: null, open(s) { this.active = s; document.body.style.overflow = 'hidden'; }, close() { this.active = null; document.body.style.overflow = ''; } }"
                x-on:keydown.escape.window="close()"
            >
                <flux:heading size="lg">
                    {{ __('Community progress') }}
                    <span class="opacity-50">({{ $this->submissionsCount }})</span>
                </flux:heading>
                <flux:text size="sm" class="opacity-70">
                    {{ __('Recent art submitted by participants of this challenge.') }}
                </flux:text>

                <div class="space-y-6">
                    @foreach ($challenge->days as $day)
                        @php $daySubmissions = $this->submissionsByDay[$day->day_number] ?? collect(); @endphp
                        @if ($daySubmissions->isNotEmpty())
                            <div class="space-y-2">
                                <flux:text size="sm" class="font-semibold">
                                    {{ __('Day :n', ['n' => $day->day_number]) }} · {{ $day->prompt_text }}
                                    <span class="opacity-60">({{ $daySubmissions->count() }})</span>
                                </flux:text>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                                    @foreach ($daySubmissions as $submission)
                                        @php
                                            $submissionPayload = [
                                                'img' => '/storage/'.$submission->image_path,
                                                'user' => $submission->userChallenge->user->name,
                                                'day' => $day->day_number,
                                                'prompt' => $day->prompt_text,
                                                'caption' => $submission->caption,
                                                'url' => route('art.show', $submission),
                                                'likes' => $submission->likes_count,
                                                'comments' => $submission->comments_count,
                                            ];
                                        @endphp
                                        <button
                                            type="button"
                                            x-on:click='open(@json($submissionPayload))'
                                            class="group relative block aspect-square overflow-hidden rounded-lg border border-zinc-700 transition hover:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-lime-500"
                                            aria-label="{{ __('View art by :name', ['name' => $submission->userChallenge->user->name]) }}"
                                        >
                                            <img
                                                src="/storage/{{ $submission->image_path }}"
                                                alt="{{ $submission->caption ?? $day->prompt_text }}"
                                                class="absolute inset-0 h-full w-full object-cover transition group-hover:scale-105"
                                                loading="lazy"
                                            />
                                            <div class="absolute inset-x-0 bottom-0 flex flex-col gap-1 bg-gradient-to-t from-black/85 to-transparent p-2 pointer-events-none">
                                                <div class="flex items-end justify-between gap-1">
                                                    <div class="flex min-w-0 items-center gap-1">
                                                        <span class="inline-block size-5 overflow-hidden rounded-full bg-zinc-700">
                                                            @if ($submission->userChallenge->user->avatar_path)
                                                                <img src="{{ $submission->userChallenge->user->avatarUrl() }}" alt="" class="size-full object-cover" />
                                                            @else
                                                                <span class="flex size-full items-center justify-center text-[10px] uppercase text-white">{{ $submission->userChallenge->user->initials() }}</span>
                                                            @endif
                                                        </span>
                                                        <span class="truncate text-xs text-white/90">{{ $submission->userChallenge->user->name }}</span>
                                                    </div>
                                                    @if ($submission->likes_count > 0 || $submission->comments_count > 0)
                                                        <div class="flex items-center gap-2 text-xs text-white/80">
                                                            @if ($submission->likes_count > 0)
                                                                <span class="inline-flex items-center gap-0.5">
                                                                    <flux:icon.heart variant="micro" />{{ $submission->likes_count }}
                                                                </span>
                                                            @endif
                                                            @if ($submission->comments_count > 0)
                                                                <span class="inline-flex items-center gap-0.5">
                                                                    <flux:icon.chat-bubble-left variant="micro" />{{ $submission->comments_count }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                                @if ($submission->caption)
                                                    <div class="line-clamp-2 text-[10px] italic text-white/75" title="{{ $submission->caption }}">
                                                        “{{ $submission->caption }}”
                                                    </div>
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <template x-teleport="body">
                    <div
                        x-show="active"
                        x-transition.opacity
                        x-cloak
                        x-on:click="close()"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
                        role="dialog"
                        aria-modal="true"
                    >
                        <button
                            type="button"
                            x-on:click.stop="close()"
                            class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20"
                            aria-label="{{ __('Close') }}"
                        >
                            <flux:icon.x-mark class="size-6" />
                        </button>

                        <figure
                            x-show="active"
                            x-on:click.stop
                            class="flex max-h-full max-w-5xl flex-col items-center gap-3"
                        >
                            <img
                                :src="active?.img"
                                :alt="active?.caption || active?.prompt"
                                class="max-h-[80vh] w-auto max-w-full rounded-lg object-contain"
                            />
                            <figcaption class="space-y-2 text-center text-white">
                                <div class="text-sm opacity-80">
                                    <span x-text="`{{ __('Day') }} ${active?.day} · ${active?.prompt}`"></span>
                                </div>
                                <div class="text-xs opacity-60">
                                    <span x-text="`{{ __('by') }} ${active?.user}`"></span>
                                </div>
                                <template x-if="active?.caption">
                                    <p class="mt-2 max-w-xl text-sm italic opacity-80" x-text="active.caption"></p>
                                </template>
                                <div class="flex items-center justify-center gap-3 pt-2 text-xs">
                                    <span class="inline-flex items-center gap-1 opacity-70">
                                        <flux:icon.heart variant="micro" /><span x-text="active?.likes ?? 0"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 opacity-70">
                                        <flux:icon.chat-bubble-left variant="micro" /><span x-text="active?.comments ?? 0"></span>
                                    </span>
                                    <a
                                        :href="active?.url"
                                        wire:navigate
                                        x-on:click="close()"
                                        class="rounded bg-white/10 px-3 py-1 text-white transition hover:bg-white/20"
                                    >
                                        {{ __('Open page') }}
                                    </a>
                                </div>
                            </figcaption>
                        </figure>
                    </div>
                </template>
            </section>
        @endif

        <flux:separator />

        <section class="space-y-4">
            <flux:heading size="lg">
                {{ __('Comments') }}
                <span class="opacity-50">({{ $this->commentsCount }})</span>
            </flux:heading>

            <form wire:submit="addComment" class="space-y-2">
                <flux:textarea
                    wire:model="newComment"
                    :placeholder="__('Share your thoughts...')"
                    rows="2"
                    maxlength="500"
                />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" size="sm">
                        {{ __('Post comment') }}
                    </flux:button>
                </div>
            </form>

            @if ($this->comments->isEmpty())
                <flux:text class="opacity-60">{{ __('No comments yet. Be the first!') }}</flux:text>
            @else
                <ul class="space-y-3">
                    @foreach ($this->comments as $comment)
                        <li class="rounded-lg border border-zinc-700 bg-zinc-900 p-4">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <x-user-link :user="$comment->user" :with-avatar="true" avatar-size="xs" class="text-sm font-semibold" />
                                    <flux:text size="xs" class="opacity-50">{{ $comment->created_at->diffForHumans() }}</flux:text>
                                </div>
                                @if ($comment->user_id === Auth::id())
                                    <flux:button
                                        variant="ghost"
                                        size="xs"
                                        icon="trash"
                                        wire:click="deleteComment({{ $comment->id }})"
                                        wire:confirm="{{ __('Delete this comment?') }}"
                                    />
                                @endif
                            </div>
                            <flux:text>{{ $comment->body }}</flux:text>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <flux:modal name="start-template-from-detail" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Accept this challenge') }}</flux:heading>
            <flux:text>{{ __('Pick the date you want to count as day 1.') }}</flux:text>
            <flux:input wire:model="startDate" type="date" :label="__('Start date')" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" wire:click="confirmStart">
                    {{ __('Start') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
