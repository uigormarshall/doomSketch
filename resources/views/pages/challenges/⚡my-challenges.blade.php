<?php

use App\Enums\UserChallengeStatus;
use App\Models\Challenge;
use App\Services\ChallengeService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My challenges')] class extends Component {
    public ?int $selectedChallengeId = null;

    public string $startDate = '';

    public function mount(): void
    {
        $this->startDate = now()->toDateString();
    }

    #[Computed]
    public function journeys()
    {
        return Auth::user()
            ->userChallenges()
            ->with(['challenge', 'submissions'])
            ->withCount('submissions')
            ->latest()
            ->get();
    }

    #[Computed]
    public function templates()
    {
        return Auth::user()
            ->createdChallenges()
            ->with('originalChallenge:id,title')
            ->withCount('days')
            ->latest()
            ->get();
    }

    public function openStartModal(int $challengeId): void
    {
        $this->selectedChallengeId = $challengeId;
        $this->startDate = now()->toDateString();
        Flux::modal('start-template')->show();
    }

    public function confirmStart(ChallengeService $service): void
    {
        $validated = $this->validate([
            'startDate' => ['required', 'date'],
            'selectedChallengeId' => ['required', 'integer', 'exists:challenges,id'],
        ]);

        $challenge = Challenge::where('creator_id', Auth::id())
            ->findOrFail($validated['selectedChallengeId']);

        $userChallenge = $service->startChallenge(
            Auth::user(),
            $challenge,
            \Carbon\CarbonImmutable::parse($validated['startDate']),
        );

        Flux::modal('start-template')->close();
        Flux::toast(variant: 'success', text: __('Challenge started.'));

        $this->redirectRoute('challenge.show', $userChallenge, navigate: true);
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-5xl space-y-10 p-6">
        <section class="space-y-4">
            <header class="flex items-end justify-between">
                <div>
                    <flux:heading size="xl">{{ __('My journeys') }}</flux:heading>
                    <flux:text>{{ __('Your active and completed challenge runs.') }}</flux:text>
                </div>
                <flux:button :href="route('challenges.explore')" wire:navigate variant="ghost" icon="globe-alt">
                    {{ __('Explore') }}
                </flux:button>
            </header>

            @if ($this->journeys->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-700 p-10 text-center">
                    <flux:text>{{ __('You haven\'t started any challenge yet.') }}</flux:text>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($this->journeys as $uc)
                        <a
                            href="{{ route('challenge.show', $uc) }}"
                            wire:navigate
                            class="block space-y-2 rounded-xl border border-zinc-700 bg-zinc-900 p-4 transition hover:border-zinc-500"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <flux:heading size="lg">{{ $uc->challenge->title }}</flux:heading>
                                <flux:badge
                                    size="sm"
                                    :color="match ($uc->status) {
                                        UserChallengeStatus::Active => 'lime',
                                        UserChallengeStatus::Completed => 'sky',
                                        UserChallengeStatus::Abandoned => 'zinc',
                                    }"
                                >
                                    {{ __(ucfirst($uc->status->value)) }}
                                </flux:badge>
                            </div>
                            <flux:text size="sm" class="opacity-70">
                                {{ __('Started on :date', ['date' => $uc->start_date->format('Y-m-d')]) }}
                            </flux:text>
                            <flux:text size="sm">
                                {{ __(':done of :total days submitted', [
                                    'done' => $uc->submissions_count,
                                    'total' => $uc->challenge->duration_days,
                                ]) }}
                            </flux:text>
                            @if ($uc->challenge->has_palette && $uc->challenge->palette_colors)
                                <div class="space-y-1 pt-1">
                                    @if ($uc->challenge->palette_name)
                                        <flux:text size="xs" class="opacity-60">{{ $uc->challenge->palette_name }}</flux:text>
                                    @endif
                                    <x-palette-preview :colors="$uc->challenge->palette_colors" size="sm" />
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <header class="flex items-end justify-between">
                <div>
                    <flux:heading size="xl">{{ __('My templates') }}</flux:heading>
                    <flux:text>{{ __('Challenges you created.') }}</flux:text>
                </div>
                <flux:button :href="route('challenges.create')" wire:navigate variant="primary" icon="plus">
                    {{ __('New challenge') }}
                </flux:button>
            </header>

            @if ($this->templates->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-700 p-10 text-center">
                    <flux:text>{{ __('You haven\'t created any challenge template yet.') }}</flux:text>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($this->templates as $c)
                        <article class="space-y-2 rounded-xl border border-zinc-700 bg-zinc-900 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <a href="{{ route('challenges.template', $c) }}" wire:navigate>
                                    <flux:heading size="lg" class="hover:underline">{{ $c->title }}</flux:heading>
                                </a>
                                @if ($c->is_private)
                                    <flux:badge size="sm" color="zinc" icon="lock-closed">
                                        {{ __('Private') }}
                                    </flux:badge>
                                @endif
                            </div>
                            @if ($c->originalChallenge)
                                <flux:text size="xs" class="opacity-60">
                                    {{ __('Cloned from :title', ['title' => $c->originalChallenge->title]) }}
                                </flux:text>
                            @endif
                            <flux:text size="sm" class="opacity-70">
                                {{ __(':n days', ['n' => $c->duration_days]) }}
                            </flux:text>
                            @if ($c->has_palette && $c->palette_colors)
                                <x-palette-preview :colors="$c->palette_colors" size="sm" />
                            @endif
                            <div class="pt-2">
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="openStartModal({{ $c->id }})"
                                >
                                    {{ __('Start this challenge') }}
                                </flux:button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <flux:modal name="start-template" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Start this challenge') }}</flux:heading>
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
