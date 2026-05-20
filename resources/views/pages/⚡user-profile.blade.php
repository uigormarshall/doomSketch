<?php

use App\Models\Submission;
use App\Models\User;
use App\Notifications\UserFollowedYou;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Profile')] class extends Component {
    public User $profile;

    #[Url(as: 'tab')]
    public string $tab = 'journeys';

    public function mount(User $user): void
    {
        $this->profile = $user;
    }

    #[Computed]
    public function isOwn(): bool
    {
        return auth()->id() === $this->profile->id;
    }

    #[Computed]
    public function isFollowing(): bool
    {
        return ! $this->isOwn && auth()->user()->isFollowing($this->profile);
    }

    #[Computed]
    public function followersCount(): int
    {
        return $this->profile->followers()->count();
    }

    #[Computed]
    public function followingCount(): int
    {
        return $this->profile->following()->count();
    }

    public function toggleFollow(): void
    {
        if ($this->isOwn) {
            return;
        }

        $current = auth()->user();

        if ($current->isFollowing($this->profile)) {
            $current->following()->detach($this->profile->id);
            Flux::toast(text: __('Unfollowed :name', ['name' => $this->profile->name]));
        } else {
            $current->following()->attach($this->profile->id);
            $this->profile->notify(new UserFollowedYou($current));
            Flux::toast(variant: 'success', text: __('Now following :name', ['name' => $this->profile->name]));
        }

        unset($this->isFollowing, $this->followersCount);
    }

    #[Computed]
    public function journeys()
    {
        return $this->profile
            ->userChallenges()
            ->with(['challenge:id,title,duration_days,has_palette,palette_colors,palette_name'])
            ->withCount('submissions')
            ->latest()
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function templates()
    {
        return $this->profile
            ->createdChallenges()
            ->when(! $this->isOwn, fn ($q) => $q->where('is_private', false))
            ->with('originalChallenge:id,title')
            ->withCount(['days', 'likes', 'comments'])
            ->latest()
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function gallery()
    {
        return Submission::query()
            ->whereHas('userChallenge', fn ($q) => $q->where('user_id', $this->profile->id))
            ->with(['userChallenge.challenge:id,title', 'challengeDay:id,day_number,prompt_text'])
            ->withCount(['likes', 'comments'])
            ->latest()
            ->limit(60)
            ->get();
    }

    #[Computed]
    public function recentActivity()
    {
        if (! $this->isOwn) {
            return collect();
        }

        return $this->profile->notifications()->latest()->limit(8)->get();
    }

    #[Computed]
    public function activityActors()
    {
        $ids = $this->recentActivity->pluck('data.user_id')->filter()->unique();

        return User::whereIn('id', $ids)->get(['id', 'name', 'username', 'avatar_path'])->keyBy('id');
    }

    #[Computed]
    public function unreadActivityCount(): int
    {
        if (! $this->isOwn) {
            return 0;
        }

        return $this->profile->unreadNotifications()->count();
    }

    /**
     * Activity calendar: returns ['counts' => [Y-m-d => int], 'days' => [...]]
     * with 53 weeks ending today.
     *
     * @return array{counts: array<string,int>, weeks: array<int, array<int, array{date: string, count: int, in_range: bool}>>, total: int, max: int}
     */
    #[Computed]
    public function activityCalendar(): array
    {
        $end = CarbonImmutable::now()->startOfDay();
        $start = $end->subWeeks(52)->startOfWeek(CarbonImmutable::SUNDAY);

        $counts = Submission::query()
            ->whereHas('userChallenge', fn ($q) => $q->where('user_id', $this->profile->id))
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($s) => $s->created_at->format('Y-m-d'))
            ->map(fn ($g) => $g->count())
            ->all();

        $weeks = [];
        $cursor = $start;
        $weekIndex = 0;

        while ($cursor <= $end || $cursor->dayOfWeek !== CarbonImmutable::SATURDAY) {
            $date = $cursor->format('Y-m-d');
            $weeks[$weekIndex][$cursor->dayOfWeek] = [
                'date' => $date,
                'count' => $counts[$date] ?? 0,
                'in_range' => $cursor <= $end,
            ];

            if ($cursor->dayOfWeek === CarbonImmutable::SATURDAY) {
                $weekIndex++;
            }

            $cursor = $cursor->addDay();

            if ($weekIndex > 53) {
                break;
            }
        }

        return [
            'counts' => $counts,
            'weeks' => $weeks,
            'total' => array_sum($counts),
            'max' => empty($counts) ? 0 : max($counts),
        ];
    }

    #[Computed]
    public function templatesCount(): int
    {
        return $this->profile->createdChallenges()
            ->when(! $this->isOwn, fn ($q) => $q->where('is_private', false))
            ->count();
    }

    #[Computed]
    public function journeysCount(): int
    {
        return $this->profile->userChallenges()->count();
    }

    #[Computed]
    public function gallerySize(): int
    {
        return Submission::whereHas('userChallenge', fn ($q) => $q->where('user_id', $this->profile->id))->count();
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-5xl space-y-8 p-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="size-24 overflow-hidden rounded-full ring-2 ring-zinc-700 bg-zinc-800 flex items-center justify-center shrink-0">
                @if ($profile->avatarUrl())
                    <img src="{{ $profile->avatarUrl() }}" alt="" class="size-full object-cover" />
                @else
                    <flux:heading size="xl" class="opacity-60">{{ $profile->initials() }}</flux:heading>
                @endif
            </div>
            <div class="flex-1 space-y-2">
                <div>
                    <flux:heading size="xl">{{ $profile->name }}</flux:heading>
                    <flux:text class="opacity-60">@{{ $profile->username }}</flux:text>
                </div>
                @if ($profile->bio)
                    <flux:text>{{ $profile->bio }}</flux:text>
                @endif
                <div class="flex flex-wrap gap-4 pt-2 text-sm">
                    <span><strong>{{ $this->templatesCount }}</strong> <span class="opacity-70">{{ __('templates') }}</span></span>
                    <span><strong>{{ $this->journeysCount }}</strong> <span class="opacity-70">{{ __('journeys') }}</span></span>
                    <span><strong>{{ $this->gallerySize }}</strong> <span class="opacity-70">{{ __('artworks') }}</span></span>
                    <span><strong>{{ $this->followersCount }}</strong> <span class="opacity-70">{{ __('followers') }}</span></span>
                    <span><strong>{{ $this->followingCount }}</strong> <span class="opacity-70">{{ __('following') }}</span></span>
                </div>
            </div>
            <div class="shrink-0">
                @if ($this->isOwn)
                    <flux:button :href="route('profile.edit')" wire:navigate variant="ghost" icon="cog" size="sm">
                        {{ __('Edit profile') }}
                    </flux:button>
                @else
                    <flux:button
                        wire:click="toggleFollow"
                        :variant="$this->isFollowing ? 'ghost' : 'primary'"
                        :icon="$this->isFollowing ? 'check' : 'plus'"
                        size="sm"
                    >
                        {{ $this->isFollowing ? __('Following') : __('Follow') }}
                    </flux:button>
                @endif
            </div>
        </header>

        @php $cal = $this->activityCalendar; @endphp
        <section class="space-y-3 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <flux:heading size="lg">
                    {{ __(':n submissions in the last year', ['n' => $cal['total']]) }}
                </flux:heading>
                <flux:text size="xs" class="opacity-60">{{ __('Each square is a day. Darker = more submissions.') }}</flux:text>
            </div>

            @php
                $levels = function (int $count, int $max): int {
                    if ($count === 0) {
                        return 0;
                    }
                    if ($max <= 1) {
                        return 1;
                    }
                    $ratio = $count / $max;
                    return match (true) {
                        $ratio <= 0.25 => 1,
                        $ratio <= 0.5 => 2,
                        $ratio <= 0.75 => 3,
                        default => 4,
                    };
                };
                $palette = [
                    0 => 'bg-zinc-800',
                    1 => 'bg-lime-900',
                    2 => 'bg-lime-700',
                    3 => 'bg-lime-500',
                    4 => 'bg-lime-300',
                ];
            @endphp

            <div class="overflow-x-auto">
                <div class="flex gap-[3px]">
                    @foreach ($cal['weeks'] as $week)
                        <div class="flex flex-col gap-[3px]">
                            @for ($d = 0; $d < 7; $d++)
                                @php $cell = $week[$d] ?? null; @endphp
                                @if ($cell && $cell['in_range'])
                                    @php $lvl = $levels($cell['count'], $cal['max']); @endphp
                                    <span
                                        class="block size-3 rounded-sm {{ $palette[$lvl] }}"
                                        title="{{ $cell['date'] }} · {{ $cell['count'] }} {{ __('submissions') }}"
                                    ></span>
                                @else
                                    <span class="block size-3 rounded-sm opacity-0"></span>
                                @endif
                            @endfor
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 text-xs opacity-60">
                <span>{{ __('Less') }}</span>
                @foreach ([0, 1, 2, 3, 4] as $lvl)
                    <span class="block size-3 rounded-sm {{ $palette[$lvl] }}"></span>
                @endforeach
                <span>{{ __('More') }}</span>
            </div>
        </section>

        @if ($this->isOwn && $this->recentActivity->isNotEmpty())
            <section class="space-y-3 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">
                        {{ __('Your recent activity') }}
                        @if ($this->unreadActivityCount > 0)
                            <flux:badge size="sm" color="lime" inline>{{ $this->unreadActivityCount }} {{ __('new') }}</flux:badge>
                        @endif
                    </flux:heading>
                    <flux:link :href="route('notifications.index')" wire:navigate size="sm">
                        {{ __('See all') }}
                    </flux:link>
                </div>
                <ul class="space-y-1">
                    @foreach ($this->recentActivity as $notification)
                        @php
                            $d = $notification->data;
                            $actor = $this->activityActors[$d['user_id'] ?? null] ?? null;
                        @endphp
                        <li>
                            <a
                                href="{{ $d['url'] ?? '#' }}"
                                wire:navigate
                                class="flex items-center gap-2 rounded px-2 py-1.5 text-sm transition hover:bg-zinc-800 {{ $notification->read_at ? 'opacity-60' : '' }}"
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
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <nav class="flex gap-1 border-b border-zinc-700">
            @foreach ([
                'journeys' => __('Journeys'),
                'templates' => __('Templates'),
                'gallery' => __('Gallery'),
            ] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="-mb-px border-b-2 px-4 py-2 text-sm transition
                        {{ $tab === $key ? 'border-lime-500 text-white' : 'border-transparent opacity-60 hover:opacity-100' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        @if ($tab === 'journeys')
            @if ($this->journeys->isEmpty())
                <flux:text class="py-8 text-center opacity-60">{{ __('No journeys yet.') }}</flux:text>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($this->journeys as $uc)
                        <article class="space-y-2 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                            <flux:heading size="lg">
                                <a href="{{ route('challenges.template', $uc->challenge) }}" wire:navigate class="hover:underline">
                                    {{ $uc->challenge->title }}
                                </a>
                            </flux:heading>
                            <flux:text size="sm" class="opacity-70">
                                {{ $uc->submissions_count }}/{{ $uc->challenge->duration_days }} {{ __('days') }}
                                · {{ __(ucfirst($uc->status->value)) }}
                            </flux:text>
                            @if ($uc->challenge->has_palette && $uc->challenge->palette_colors)
                                <x-palette-preview :colors="$uc->challenge->palette_colors" size="sm" />
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        @endif

        @if ($tab === 'templates')
            @if ($this->templates->isEmpty())
                <flux:text class="py-8 text-center opacity-60">{{ __('No templates yet.') }}</flux:text>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->templates as $c)
                        <article class="space-y-2 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                            <flux:heading size="lg">
                                <a href="{{ route('challenges.template', $c) }}" wire:navigate class="hover:underline">
                                    {{ $c->title }}
                                </a>
                            </flux:heading>
                            <flux:text size="sm" class="opacity-70">{{ __(':n days', ['n' => $c->duration_days]) }}</flux:text>
                            @if ($c->has_palette && $c->palette_colors)
                                <x-palette-preview :colors="$c->palette_colors" size="sm" />
                            @endif
                            <div class="flex items-center gap-3 text-xs opacity-60">
                                <span class="inline-flex items-center gap-1"><flux:icon.heart variant="micro" />{{ $c->likes_count }}</span>
                                <span class="inline-flex items-center gap-1"><flux:icon.chat-bubble-left variant="micro" />{{ $c->comments_count }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        @endif

        @if ($tab === 'gallery')
            @if ($this->gallery->isEmpty())
                <flux:text class="py-8 text-center opacity-60">{{ __('No artworks yet.') }}</flux:text>
            @else
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @foreach ($this->gallery as $submission)
                        <a
                            href="{{ route('art.show', $submission) }}"
                            wire:navigate
                            class="group relative block aspect-square overflow-hidden rounded-lg border border-zinc-700 transition hover:border-zinc-400"
                            title="{{ $submission->challengeDay->prompt_text }}"
                        >
                            <img
                                src="/storage/{{ $submission->image_path }}"
                                alt=""
                                class="absolute inset-0 h-full w-full object-cover transition group-hover:scale-105"
                                loading="lazy"
                            />
                            @if ($submission->likes_count > 0 || $submission->comments_count > 0)
                                <div class="absolute right-1 top-1 flex items-center gap-1 rounded-md bg-black/70 px-1.5 py-0.5 text-xs text-white">
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
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 to-transparent p-2">
                                <div class="truncate text-xs text-white/80">
                                    {{ __('Day :n', ['n' => $submission->challengeDay->day_number]) }} · {{ $submission->userChallenge->challenge->title }}
                                </div>
                                @if ($submission->caption)
                                    <div class="line-clamp-2 text-[10px] italic text-white/70" title="{{ $submission->caption }}">
                                        “{{ $submission->caption }}”
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
