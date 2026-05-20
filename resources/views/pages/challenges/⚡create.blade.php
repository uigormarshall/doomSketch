<?php

use App\Models\Challenge;
use App\Models\ChallengeDay;
use App\Support\PaletteParser;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('New challenge')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    public int $duration_days = 7;

    /** @var array<int, string> */
    public array $prompts = ['', '', '', '', '', '', ''];

    public bool $is_private = false;

    public bool $has_palette = true;

    public string $palette_name = '';

    /** @var array<int, string> */
    public array $palette_colors = ['#0f380f', '#306230', '#8bac0f', '#9bbc0f'];

    public string $palette_source_url = '';

    public $paletteFile;

    public $coverFile;

    public ?string $coverPath = null;

    public ?int $originalChallengeId = null;

    public ?string $clonedFromTitle = null;

    public string $importJson = '';

    public function mount(?Challenge $challenge = null): void
    {
        if (! $challenge?->exists) {
            return;
        }

        if ($challenge->is_private && $challenge->creator_id !== Auth::id()) {
            abort(404);
        }

        $challenge->loadMissing('days');

        $this->originalChallengeId = $challenge->id;
        $this->clonedFromTitle = $challenge->title;

        $this->title = $challenge->title;
        $this->description = (string) ($challenge->description ?? '');
        $this->coverPath = $challenge->cover_path;
        $this->duration_days = $challenge->duration_days;
        $this->prompts = $challenge->days->pluck('prompt_text')->all();
        $this->has_palette = $challenge->has_palette;
        $this->palette_name = (string) ($challenge->palette_name ?? '');
        $this->palette_colors = $challenge->palette_colors ?? [];
        $this->palette_source_url = (string) ($challenge->palette_source_url ?? '');
    }

    #[Computed]
    public function jsonImportEnabled(): bool
    {
        return (bool) config('features.import_challenge_json');
    }

    public function openJsonImport(): void
    {
        abort_unless($this->jsonImportEnabled, 404);

        $this->resetErrorBag('importJson');
        $this->importJson = '';
        Flux::modal('import-json')->show();
    }

    public function importFromJson(): void
    {
        abort_unless($this->jsonImportEnabled, 404);

        $decoded = json_decode($this->importJson, true);

        if (! is_array($decoded)) {
            $this->addError('importJson', __('Invalid JSON.'));

            return;
        }

        $validator = Validator::make($decoded, [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:31'],
            'prompts' => ['required', 'array', 'min:1', 'max:31'],
            'prompts.*' => ['required', 'string', 'max:120'],
            'is_private' => ['sometimes', 'boolean'],
            'has_palette' => ['sometimes', 'boolean'],
            'palette_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'palette_colors' => ['sometimes', 'nullable', 'array', 'max:16'],
            'palette_colors.*' => ['string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'palette_source_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $validator->after(function ($v) use ($decoded) {
            $duration = (int) ($decoded['duration_days'] ?? 0);
            $prompts = $decoded['prompts'] ?? [];

            if (is_array($prompts) && count($prompts) !== $duration) {
                $v->errors()->add('prompts', __('Prompts length must equal duration_days.'));
            }
        });

        if ($validator->fails()) {
            $this->addError('importJson', $validator->errors()->first());

            return;
        }

        $data = $validator->validated();

        $this->title = $data['title'];
        $this->description = $data['description'] ?? '';
        $this->duration_days = $data['duration_days'];
        $this->prompts = array_values($data['prompts']);
        $this->is_private = (bool) ($data['is_private'] ?? false);
        $this->has_palette = (bool) ($data['has_palette'] ?? false);

        if ($this->has_palette) {
            $this->palette_name = $data['palette_name'] ?? '';
            $this->palette_colors = array_map('strtolower', $data['palette_colors'] ?? []);
            $this->palette_source_url = $data['palette_source_url'] ?? '';
        } else {
            $this->palette_name = '';
            $this->palette_colors = [];
            $this->palette_source_url = '';
        }

        Flux::modal('import-json')->close();
        Flux::toast(variant: 'success', text: __('JSON imported. Review before saving.'));
    }

    public function updatedDurationDays(int $value): void
    {
        $value = max(1, min(31, $value));
        $this->duration_days = $value;
        $this->prompts = array_pad(array_slice($this->prompts, 0, $value), $value, '');
    }

    public function addColor(): void
    {
        if (count($this->palette_colors) < 16) {
            $this->palette_colors[] = '#ffffff';
        }
    }

    public function removeColor(int $index): void
    {
        unset($this->palette_colors[$index]);
        $this->palette_colors = array_values($this->palette_colors);
    }

    public function updatedPaletteFile(): void
    {
        $this->validate([
            'paletteFile' => ['file', 'max:64', 'extensions:hex,txt'],
        ], [], [
            'paletteFile' => __('palette file'),
        ]);

        $originalName = $this->paletteFile->getClientOriginalName();

        try {
            $colors = PaletteParser::parseHex(file_get_contents($this->paletteFile->getRealPath()));
        } catch (\InvalidArgumentException) {
            $this->reset('paletteFile');
            $this->addError('paletteFile', __('No valid hex colors found in the file.'));

            return;
        }

        $this->palette_colors = $colors;

        if ($this->palette_name === '') {
            $this->palette_name = pathinfo($originalName, PATHINFO_FILENAME);
        }

        $this->reset('paletteFile');
        Flux::toast(variant: 'success', text: __('Imported :n colors.', ['n' => count($colors)]));
    }

    public function updatedCoverFile(): void
    {
        $this->validate([
            'coverFile' => ['image', 'max:5120'],
        ], [], [
            'coverFile' => __('cover'),
        ]);
    }

    public function removeCover(): void
    {
        $this->reset('coverFile');
        $this->coverPath = null;
    }

    public function save(): void
    {
        $rules = [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:31'],
            'prompts' => ['required', 'array', 'size:'.$this->duration_days],
            'prompts.*' => ['required', 'string', 'max:120'],
            'is_private' => ['boolean'],
            'has_palette' => ['boolean'],
            'coverFile' => ['nullable', 'image', 'max:5120'],
        ];

        if ($this->has_palette) {
            $rules['palette_name'] = ['required', 'string', 'max:60'];
            $rules['palette_colors'] = ['required', 'array', 'min:1', 'max:16'];
            $rules['palette_colors.*'] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules['palette_source_url'] = ['nullable', 'url', 'max:500'];
        }

        $validated = $this->validate($rules, [
            'prompts.*.required' => __('Each day needs a prompt.'),
            'palette_colors.*.regex' => __('Use the format #rrggbb.'),
        ]);

        $challenge = Challenge::create([
            'creator_id' => Auth::id(),
            'original_challenge_id' => $this->resolveOriginalChallengeId(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'cover_path' => $this->resolveCoverPath(),
            'duration_days' => $validated['duration_days'],
            'is_private' => $validated['is_private'],
            'has_palette' => $validated['has_palette'],
            'palette_name' => $this->has_palette ? $validated['palette_name'] : null,
            'palette_colors' => $this->has_palette ? $validated['palette_colors'] : null,
            'palette_source_url' => $this->has_palette ? ($validated['palette_source_url'] ?: null) : null,
        ]);

        foreach ($validated['prompts'] as $i => $text) {
            ChallengeDay::create([
                'challenge_id' => $challenge->id,
                'day_number' => $i + 1,
                'prompt_text' => $text,
            ]);
        }

        Flux::toast(variant: 'success', text: __('Challenge created.'));

        $this->redirectRoute('my-challenges', navigate: true);
    }

    private function resolveOriginalChallengeId(): ?int
    {
        if ($this->originalChallengeId === null) {
            return null;
        }

        $source = Challenge::find($this->originalChallengeId);

        if (! $source || ($source->is_private && $source->creator_id !== Auth::id())) {
            return null;
        }

        return $source->id;
    }

    /**
     * Resolves the cover to persist: a freshly uploaded file wins; otherwise a
     * cloned source cover is duplicated to an independent path so the clone
     * never shares a file with the original.
     */
    private function resolveCoverPath(): ?string
    {
        if ($this->coverFile) {
            return $this->coverFile->store('covers/'.Auth::id(), 'public');
        }

        if ($this->coverPath && Storage::disk('public')->exists($this->coverPath)) {
            $extension = pathinfo($this->coverPath, PATHINFO_EXTENSION) ?: 'jpg';
            $copy = 'covers/'.Auth::id().'/'.Str::uuid().'.'.$extension;
            Storage::disk('public')->copy($this->coverPath, $copy);

            return $copy;
        }

        return null;
    }
}; ?>

<div class="mx-auto w-full max-w-3xl space-y-8 p-6">
        <header class="flex items-start justify-between gap-3">
            <div class="space-y-1">
                <flux:heading size="xl">
                    {{ $clonedFromTitle ? __('Customize a copy') : __('New challenge') }}
                </flux:heading>
                @if ($clonedFromTitle)
                    <flux:text>
                        {{ __('Cloning ":title". Edit anything below before saving — the original stays untouched.', ['title' => $clonedFromTitle]) }}
                    </flux:text>
                @else
                    <flux:text>{{ __('Set up a timed art journey with daily prompts.') }}</flux:text>
                @endif
            </div>
            @if ($this->jsonImportEnabled)
                <flux:button
                    type="button"
                    variant="ghost"
                    size="sm"
                    icon="code-bracket"
                    wire:click="openJsonImport"
                >
                    {{ __('Import from JSON') }}
                </flux:button>
            @endif
        </header>

        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="title" :label="__('Title')" type="text" required maxlength="100" autofocus />

            <flux:textarea wire:model="description" :label="__('Description')" rows="3" maxlength="500" />

            <div class="space-y-2">
                <flux:label>{{ __('Cover image (optional)') }}</flux:label>
                <flux:text size="sm" class="opacity-60">
                    {{ __('Shown as the card background on Explore. jpeg, png, webp · max 5MB.') }}
                </flux:text>

                @php
                    $coverPreview = null;
                    if ($coverFile && ! $errors->has('coverFile')) {
                        $coverPreview = $coverFile->temporaryUrl();
                    } elseif (! $coverFile && $coverPath) {
                        $coverPreview = '/storage/'.$coverPath;
                    }
                @endphp

                @if ($coverPreview)
                    <div class="relative overflow-hidden rounded-lg border border-zinc-700">
                        <img src="{{ $coverPreview }}" alt="" class="aspect-[16/9] w-full object-cover" />
                        <flux:button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            wire:click="removeCover"
                            class="absolute right-2 top-2 bg-black/60"
                        >
                            {{ __('Remove') }}
                        </flux:button>
                    </div>
                @endif

                <flux:input wire:model="coverFile" type="file" accept="image/*" />
                @error('coverFile')
                    <flux:text size="sm" class="text-red-400">{{ $message }}</flux:text>
                @enderror
            </div>

            <flux:input
                wire:model.live="duration_days"
                :label="__('Duration (days)')"
                type="number"
                min="1"
                max="31"
                required
            />

            <fieldset class="space-y-3">
                <flux:legend>{{ __('Daily prompts') }}</flux:legend>
                <div class="space-y-2">
                    @foreach ($prompts as $i => $prompt)
                        <flux:input
                            wire:model="prompts.{{ $i }}"
                            :label="__('Day :n', ['n' => $i + 1])"
                            type="text"
                            maxlength="120"
                            required
                        />
                    @endforeach
                </div>
            </fieldset>

            <flux:separator />

            <flux:switch wire:model.live="has_palette" :label="__('Restricted color palette')" />

            @if ($has_palette)
                <div class="space-y-4 rounded-lg border border-zinc-700 p-4">
                    <flux:input
                        wire:model="palette_name"
                        :label="__('Palette name')"
                        type="text"
                        placeholder="{{ __('e.g., Game Boy, Pico-8') }}"
                        maxlength="60"
                    />

                    <div class="space-y-2">
                        <flux:label>{{ __('Import palette (.hex / .txt)') }}</flux:label>
                        <flux:text size="sm" class="opacity-60">
                            {{ __('One hex code per line. Compatible with Lospec HEX files.') }}
                        </flux:text>
                        <flux:input
                            wire:model="paletteFile"
                            type="file"
                            accept=".hex,.txt,text/plain"
                        />
                        @error('paletteFile')
                            <flux:text size="sm" class="text-red-400">{{ $message }}</flux:text>
                        @enderror
                    </div>

                    <flux:input
                        wire:model="palette_source_url"
                        :label="__('Palette source URL (optional)')"
                        type="url"
                        placeholder="https://lospec.com/palette-list/..."
                        maxlength="500"
                    />

                    <div class="space-y-2">
                        <flux:label>{{ __('Colors (hex)') }}</flux:label>
                        <div class="space-y-2">
                            @foreach ($palette_colors as $i => $color)
                                <div class="flex items-center gap-2">
                                    <input
                                        type="color"
                                        wire:model.live.debounce.300ms="palette_colors.{{ $i }}"
                                        aria-label="{{ __('Pick color :n', ['n' => $i + 1]) }}"
                                        class="h-8 w-8 shrink-0 cursor-pointer rounded border-0 bg-transparent p-0 ring-1 ring-zinc-600 appearance-none [&::-webkit-color-swatch]:rounded [&::-webkit-color-swatch]:border-0 [&::-webkit-color-swatch-wrapper]:p-0 [&::-moz-color-swatch]:rounded [&::-moz-color-swatch]:border-0"
                                    />
                                    <flux:input
                                        wire:model.live.debounce.300ms="palette_colors.{{ $i }}"
                                        type="text"
                                        placeholder="#000000"
                                        class="flex-1"
                                    />
                                    <flux:button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="removeColor({{ $i }})"
                                    />
                                </div>
                            @endforeach
                        </div>
                        <flux:button type="button" variant="ghost" size="sm" icon="plus" wire:click="addColor">
                            {{ __('Add color') }}
                        </flux:button>
                    </div>
                </div>
            @endif

            <flux:switch wire:model="is_private" :label="__('Private (hidden from Explore)')" />

            <div class="flex items-center justify-end gap-3">
                <flux:button type="button" variant="ghost" :href="route('my-challenges')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Create challenge') }}
                </flux:button>
            </div>
        </form>

        @if ($this->jsonImportEnabled)
            <flux:modal name="import-json" class="max-w-2xl">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Import from JSON') }}</flux:heading>
                        <flux:text size="sm" class="opacity-70">
                            {{ __('Paste a JSON payload (e.g. generated by a local LLM) to pre-fill the form. Fields will be overwritten.') }}
                        </flux:text>
                    </div>

                    <flux:textarea
                        wire:model="importJson"
                        rows="14"
                        placeholder='{"title":"...","duration_days":7,"prompts":[...],"has_palette":true,"palette_name":"...","palette_colors":["#rrggbb"]}'
                        class="font-mono text-xs"
                    />

                    @error('importJson')
                        <flux:text size="sm" class="text-red-400">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="button" variant="primary" wire:click="importFromJson">
                            {{ __('Apply') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
</div>
