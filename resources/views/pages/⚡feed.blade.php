<?php

use App\Models\Challenge;
use App\Models\ChallengeLike;
use App\Models\Submission;
use App\Models\SubmissionLike;
use App\Models\User;
use App\Notifications\ChallengeLikedNotification;
use App\Notifications\SubmissionLikedNotification;
use App\Notifications\UserFollowedYou;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Feed')] class extends Component {
    #[Computed]
    public function followingIds(): array
    {
        return auth()->user()->following()->pluck('users.id')->all();
    }

    #[Computed]
    public function suggestions()
    {
        $excludeIds = [...$this->followingIds, auth()->id()];

        return User::query()
            ->whereNotIn('id', $excludeIds)
            ->withCount(['createdChallenges', 'followers'])
            ->withCount([
                'userChallenges as submissions_count' => fn ($q) => $q->join('submissions', 'submissions.user_challenge_id', '=', 'user_challenges.id'),
            ])
            ->orderByDesc('followers_count')
            ->orderByDesc('submissions_count')
            ->orderByDesc('created_challenges_count')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function likedSubmissionIds(): array
    {
        return SubmissionLike::where('user_id', auth()->id())
            ->pluck('submission_id')
            ->all();
    }

    #[Computed]
    public function likedChallengeIds(): array
    {
        return ChallengeLike::where('user_id', auth()->id())
            ->pluck('challenge_id')
            ->all();
    }

    public function toggleSubmissionLike(int $submissionId): void
    {
        $existing = SubmissionLike::where('user_id', auth()->id())
            ->where('submission_id', $submissionId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            SubmissionLike::create([
                'user_id' => auth()->id(),
                'submission_id' => $submissionId,
            ]);

            $submission = Submission::with('userChallenge.user')->find($submissionId);
            $owner = $submission?->userChallenge->user;
            if ($owner && $owner->id !== auth()->id()) {
                $owner->notify(new SubmissionLikedNotification(auth()->user(), $submission));
            }
        }

        unset($this->items, $this->likedSubmissionIds);
    }

    public function toggleChallengeLike(int $challengeId): void
    {
        $existing = ChallengeLike::where('user_id', auth()->id())
            ->where('challenge_id', $challengeId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            ChallengeLike::create([
                'user_id' => auth()->id(),
                'challenge_id' => $challengeId,
            ]);

            $challenge = Challenge::with('creator')->find($challengeId);
            if ($challenge && $challenge->creator_id !== auth()->id()) {
                $challenge->creator->notify(new ChallengeLikedNotification(auth()->user(), $challenge));
            }
        }

        unset($this->items, $this->likedChallengeIds);
    }

    public function follow(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }

        $target = User::findOrFail($userId);
        $current = auth()->user();

        if (! $current->isFollowing($target)) {
            $current->following()->attach($target->id);
            $target->notify(new UserFollowedYou($current));
            Flux::toast(variant: 'success', text: __('Now following :name', ['name' => $target->name]));
        }

        unset($this->followingIds, $this->suggestions, $this->items);
    }

    #[Computed]
    public function items()
    {
        $ids = $this->followingIds;

        if (empty($ids)) {
            return collect();
        }

        $submissions = Submission::query()
            ->whereHas('userChallenge', fn ($q) => $q->whereIn('user_id', $ids))
            ->with(['userChallenge.user', 'userChallenge.challenge:id,title', 'challengeDay:id,day_number,prompt_text'])
            ->withCount(['likes', 'comments'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (Submission $s) => [
                'type' => 'submission',
                'at' => $s->created_at,
                'data' => $s,
            ]);

        $templates = Challenge::query()
            ->whereIn('creator_id', $ids)
            ->where('is_private', false)
            ->with(['creator'])
            ->withCount(['days', 'likes', 'comments'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Challenge $c) => [
                'type' => 'template',
                'at' => $c->created_at,
                'data' => $c,
            ]);

        return $submissions
            ->concat($templates)
            ->sortByDesc('at')
            ->take(40)
            ->values();
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-3xl space-y-6 p-6">
        <header class="space-y-1">
            <flux:heading size="xl">{{ __('Your feed') }}</flux:heading>
            <flux:text class="opacity-70">{{ __('Recent activity from people you follow.') }}</flux:text>
        </header>

        @if ($this->suggestions->isNotEmpty())
            <section class="space-y-3 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Suggestions for you') }}</flux:heading>
                    <flux:text size="xs" class="opacity-60">{{ __('Artists to discover') }}</flux:text>
                </div>
                <div class="flex gap-3 overflow-x-auto pb-2 -mx-1 px-1">
                    @foreach ($this->suggestions as $user)
                        <article class="flex w-44 shrink-0 flex-col items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-950 p-4 text-center">
                            <a href="{{ route('user.profile', $user->username) }}" wire:navigate>
                                <div class="size-16 overflow-hidden rounded-full ring-2 ring-zinc-700 bg-zinc-800">
                                    @if ($user->avatar_path)
                                        <img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover" />
                                    @else
                                        <div class="flex size-full items-center justify-center text-sm opacity-60">{{ $user->initials() }}</div>
                                    @endif
                                </div>
                            </a>
                            <div class="min-w-0 w-full">
                                <a href="{{ route('user.profile', $user->username) }}" wire:navigate class="block truncate text-sm font-semibold hover:underline">
                                    {{ $user->name }}
                                </a>
                                <div class="truncate text-xs opacity-60">@{{ $user->username }}</div>
                            </div>
                            <flux:text size="xs" class="opacity-60">
                                {{ $user->followers_count }} {{ __('followers') }}
                            </flux:text>
                            <flux:button
                                size="sm"
                                variant="primary"
                                icon="plus"
                                wire:click="follow({{ $user->id }})"
                                class="w-full"
                            >
                                {{ __('Follow') }}
                            </flux:button>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if (empty($this->followingIds))
            <div class="space-y-3 rounded-xl border border-dashed border-zinc-700 p-10 text-center">
                <flux:text>{{ __('You\'re not following anyone yet.') }}</flux:text>
                <flux:button :href="route('challenges.explore')" wire:navigate variant="primary" icon="globe-alt" size="sm">
                    {{ __('Discover challenges') }}
                </flux:button>
            </div>
        @elseif ($this->items->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-700 p-10 text-center">
                <flux:text>{{ __('Nothing new yet. Check back later.') }}</flux:text>
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($this->items as $item)
                    @if ($item['type'] === 'submission')
                        @php
                            $s = $item['data'];
                            $isLiked = in_array($s->id, $this->likedSubmissionIds, true);
                        @endphp
                        <li class="space-y-3 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                            <div class="flex items-center gap-2 text-sm">
                                <x-user-link :user="$s->userChallenge->user" :with-avatar="true" class="font-semibold" />
                                <span class="opacity-60">{{ __('submitted art for') }}</span>
                                <a href="{{ route('challenges.template', $s->userChallenge->challenge) }}" wire:navigate class="font-medium hover:underline">
                                    {{ $s->userChallenge->challenge->title }}
                                </a>
                                <span class="ml-auto text-xs opacity-50">{{ $s->created_at->diffForHumans() }}</span>
                            </div>
                            <a href="{{ route('art.show', $s) }}" wire:navigate class="block">
                                <img src="/storage/{{ $s->image_path }}" alt="" class="w-full rounded-lg object-cover" loading="lazy" />
                            </a>
                            <flux:text size="sm" class="opacity-70">
                                {{ __('Day :n', ['n' => $s->challengeDay->day_number]) }} · {{ $s->challengeDay->prompt_text }}
                            </flux:text>
                            @if ($s->caption)
                                <p class="text-sm italic opacity-85" title="{{ $s->caption }}">
                                    “{{ $s->caption }}”
                                </p>
                            @endif
                            <div class="flex items-center gap-2 pt-1">
                                <flux:button
                                    size="sm"
                                    :variant="$isLiked ? 'primary' : 'ghost'"
                                    icon="heart"
                                    wire:click="toggleSubmissionLike({{ $s->id }})"
                                >
                                    {{ $s->likes_count }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="chat-bubble-left"
                                    :href="route('art.show', $s)"
                                    wire:navigate
                                >
                                    {{ $s->comments_count }}
                                </flux:button>
                            </div>
                        </li>
                    @else
                        @php
                            $c = $item['data'];
                            $isLiked = in_array($c->id, $this->likedChallengeIds, true);
                        @endphp
                        <li class="space-y-2 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                            <div class="flex items-center gap-2 text-sm">
                                <x-user-link :user="$c->creator" :with-avatar="true" class="font-semibold" />
                                <span class="opacity-60">{{ __('created a new challenge') }}</span>
                                <span class="ml-auto text-xs opacity-50">{{ $c->created_at->diffForHumans() }}</span>
                            </div>
                            <a href="{{ route('challenges.template', $c) }}" wire:navigate>
                                <flux:heading size="lg" class="hover:underline">{{ $c->title }}</flux:heading>
                            </a>
                            <flux:text size="sm" class="opacity-70">
                                {{ __(':n days', ['n' => $c->duration_days]) }}
                            </flux:text>
                            @if ($c->has_palette && $c->palette_colors)
                                <div class="pt-1">
                                    <x-palette-preview :colors="$c->palette_colors" size="sm" />
                                </div>
                            @endif
                            <div class="flex items-center gap-2 pt-1">
                                <flux:button
                                    size="sm"
                                    :variant="$isLiked ? 'primary' : 'ghost'"
                                    icon="heart"
                                    wire:click="toggleChallengeLike({{ $c->id }})"
                                >
                                    {{ $c->likes_count }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="chat-bubble-left"
                                    :href="route('challenges.template', $c)"
                                    wire:navigate
                                >
                                    {{ $c->comments_count }}
                                </flux:button>
                            </div>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>
</div>
