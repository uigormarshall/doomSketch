<?php

use App\Enums\UserChallengeStatus;
use App\Models\ChallengeDay;
use App\Models\UserChallenge;
use App\Services\ChallengeService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Challenge progress')] class extends Component {
    use WithFileUploads;

    public UserChallenge $userChallenge;

    public ?int $uploadingDayId = null;

    public $image;

    public string $caption = '';

    public function mount(UserChallenge $userChallenge): void
    {
        abort_unless($userChallenge->user_id === Auth::id(), 403);

        $this->userChallenge = $userChallenge->load(['challenge.days', 'challenge.originalChallenge.creator', 'submissions']);
    }

    #[Computed]
    public function submissionsByDay(): array
    {
        return $this->userChallenge->submissions->keyBy('challenge_day_id')->all();
    }

    #[Computed]
    public function currentDayNumber(): int
    {
        $diff = $this->userChallenge->start_date->diffInDays(now()->startOfDay(), false);

        return max(1, min($this->userChallenge->challenge->duration_days, (int) floor($diff) + 1));
    }

    public function openUpload(int $dayId): void
    {
        $this->resetValidation();
        $this->reset(['image', 'caption']);
        $this->uploadingDayId = $dayId;

        $existing = $this->userChallenge->submissions->firstWhere('challenge_day_id', $dayId);
        if ($existing) {
            $this->caption = (string) ($existing->caption ?? '');
        }

        Flux::modal('upload-day')->show();
    }

    public function submit(ChallengeService $service): void
    {
        $validated = $this->validate([
            'uploadingDayId' => ['required', 'integer'],
            'image' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:200'],
        ]);

        $day = ChallengeDay::findOrFail($validated['uploadingDayId']);

        $service->submitArt(
            $this->userChallenge,
            $day,
            $validated['image'],
            $validated['caption'] ?: null,
        );

        $this->userChallenge->refresh()->load(['challenge.days', 'submissions']);

        $this->reset(['image', 'caption', 'uploadingDayId']);
        Flux::modal('upload-day')->close();
        Flux::toast(variant: 'success', text: __('Art submitted.'));
    }

    public function markCompleted(ChallengeService $service): void
    {
        $service->markCompleted($this->userChallenge);
        $this->userChallenge->refresh();
        Flux::toast(variant: 'success', text: __('Challenge marked as completed.'));
    }
}; ?>

<div>
    @php
        $challenge = $userChallenge->challenge;
        $submissionsByDay = $this->submissionsByDay;
        $currentDay = $this->currentDayNumber;
    @endphp

    <div class="mx-auto w-full max-w-6xl space-y-6 p-6">
        <header class="space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="xl">{{ $challenge->title }}</flux:heading>
                    <flux:text class="opacity-70">
                        {{ __('Started on :date', ['date' => $userChallenge->start_date->format('Y-m-d')]) }}
                        · {{ __(':n days', ['n' => $challenge->duration_days]) }}
                    </flux:text>
                </div>
                <flux:badge
                    :color="match ($userChallenge->status) {
                        UserChallengeStatus::Active => 'lime',
                        UserChallengeStatus::Completed => 'sky',
                        UserChallengeStatus::Abandoned => 'zinc',
                    }"
                >
                    {{ __(ucfirst($userChallenge->status->value)) }}
                </flux:badge>
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
                    <flux:text size="sm" class="opacity-70">
                        {{ $challenge->palette_name }}
                        · {{ __('click a color to copy') }}
                    </flux:text>
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

            @if ($userChallenge->status === UserChallengeStatus::Active && count($submissionsByDay) === $challenge->duration_days)
                <flux:button variant="primary" icon="check-circle" wire:click="markCompleted">
                    {{ __('Mark as completed') }}
                </flux:button>
            @endif
        </header>

        <flux:separator />

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7">
            @foreach ($challenge->days as $day)
                @php
                    $submission = $submissionsByDay[$day->id] ?? null;
                    $isCurrent = $day->day_number === $currentDay;
                @endphp
                <button
                    type="button"
                    wire:click="openUpload({{ $day->id }})"
                    class="group relative flex aspect-square flex-col overflow-hidden rounded-lg border bg-zinc-900 text-start transition hover:border-zinc-400
                        {{ $isCurrent ? 'border-lime-500 ring-1 ring-lime-500/40' : 'border-zinc-700' }}"
                >
                    @if ($submission)
                        <img
                            src="/storage/{{ $submission->image_path }}"
                            alt="{{ $submission->caption ?? $day->prompt_text }}"
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-transparent to-transparent"></div>
                        <div class="relative mt-auto p-2">
                            <div class="text-xs font-medium text-white/90">{{ __('Day :n', ['n' => $day->day_number]) }}</div>
                            <div class="line-clamp-2 text-xs text-white/70">{{ $day->prompt_text }}</div>
                            @if ($submission->caption)
                                <div class="mt-0.5 line-clamp-2 text-[10px] italic text-white/60" title="{{ $submission->caption }}">
                                    “{{ $submission->caption }}”
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex h-full flex-col justify-between p-2">
                            <div class="text-xs font-semibold opacity-70">{{ __('Day :n', ['n' => $day->day_number]) }}</div>
                            <div class="line-clamp-3 text-sm">{{ $day->prompt_text }}</div>
                            <div class="text-xs opacity-50 group-hover:opacity-100">{{ __('+ upload') }}</div>
                        </div>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <flux:modal name="upload-day" class="max-w-md">
        @php
            $uploadingDay = $uploadingDayId ? $challenge->days->firstWhere('id', $uploadingDayId) : null;
        @endphp
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">
                    {{ $uploadingDay ? __('Day :n — :prompt', ['n' => $uploadingDay->day_number, 'prompt' => $uploadingDay->prompt_text]) : '' }}
                </flux:heading>
                <flux:text size="sm" class="opacity-70">
                    {{ __('Upload your art (jpeg, png, webp · max 5MB).') }}
                </flux:text>
            </div>

            <flux:input wire:model="image" type="file" accept="image/*" :label="__('Image')" />

            @if ($image && ! $errors->has('image'))
                <img src="{{ $image->temporaryUrl() }}" alt="" class="max-h-64 w-full rounded object-contain" />
            @endif

            <flux:input wire:model="caption" :label="__('Caption (optional)')" maxlength="200" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" wire:click="submit">
                    {{ __('Submit art') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
