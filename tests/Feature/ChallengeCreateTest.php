<?php

use App\Models\Challenge;
use App\Models\ChallengeDay;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('creates a challenge with a palette source url', function () {
    $user = User::factory()->create();
    Auth::login($user);

    Livewire::test('pages::challenges.create')
        ->set('title', 'My Pixel Challenge')
        ->set('description', 'Daily pixels')
        ->set('duration_days', 3)
        ->set('prompts', ['Sun', 'Moon', 'Star'])
        ->set('has_palette', true)
        ->set('palette_name', 'Game Boy')
        ->set('palette_colors', ['#0f380f', '#306230', '#8bac0f', '#9bbc0f'])
        ->set('palette_source_url', 'https://lospec.com/palette-list/nintendo-gameboy')
        ->call('save');

    $challenge = Challenge::firstWhere('title', 'My Pixel Challenge');

    expect($challenge)->not->toBeNull()
        ->and($challenge->palette_source_url)->toBe('https://lospec.com/palette-list/nintendo-gameboy')
        ->and($challenge->palette_colors)->toBe(['#0f380f', '#306230', '#8bac0f', '#9bbc0f'])
        ->and($challenge->days)->toHaveCount(3);
});

it('rejects an invalid palette source url', function () {
    $user = User::factory()->create();
    Auth::login($user);

    Livewire::test('pages::challenges.create')
        ->set('title', 'X')
        ->set('duration_days', 1)
        ->set('prompts', ['a'])
        ->set('has_palette', true)
        ->set('palette_name', 'X')
        ->set('palette_colors', ['#000000'])
        ->set('palette_source_url', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['palette_source_url' => 'url']);
});

it('imports a hex palette file into palette_colors', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $file = UploadedFile::fake()->createWithContent(
        'gameboy.hex',
        "ff0000\n00ff00\n0000ff\n",
    );

    $component = Livewire::test('pages::challenges.create')
        ->set('has_palette', true)
        ->set('paletteFile', $file);

    expect($component->get('palette_colors'))->toBe(['#ff0000', '#00ff00', '#0000ff'])
        ->and($component->get('palette_name'))->toBe('gameboy');
});

it('does not overwrite an existing palette name on import', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $file = UploadedFile::fake()->createWithContent('whatever.hex', "ff0000\n00ff00\n");

    $component = Livewire::test('pages::challenges.create')
        ->set('has_palette', true)
        ->set('palette_name', 'Custom Name')
        ->set('paletteFile', $file);

    expect($component->get('palette_name'))->toBe('Custom Name');
});

it('errors when the imported file has no valid colors', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $file = UploadedFile::fake()->createWithContent('bad.hex', "; just comments\nnope\n");

    Livewire::test('pages::challenges.create')
        ->set('has_palette', true)
        ->set('paletteFile', $file)
        ->assertHasErrors('paletteFile');
});

it('imports a full challenge payload from JSON', function () {
    config(['features.import_challenge_json' => true]);
    $user = User::factory()->create();
    Auth::login($user);

    $payload = json_encode([
        'title' => 'Semanal de Ilustração',
        'description' => 'Uma semana de ilustrações criativas.',
        'duration_days' => 7,
        'prompts' => ['Edifício antigo', 'Sombra na cidade', 'Vitrine vintage', 'Logotipo retro', 'Ponte velha', 'Arquitetura futurista', 'Cidade à noite'],
        'is_private' => false,
        'has_palette' => true,
        'palette_name' => 'Retro Pixel',
        'palette_colors' => ['#000000', '#7b68ee', '#2f4f4f', '#ffffff'],
        'palette_source_url' => 'https://lospec.com/palette-list/retro-pixel',
    ]);

    $component = Livewire::test('pages::challenges.create')
        ->set('importJson', $payload)
        ->call('importFromJson')
        ->assertHasNoErrors();

    expect($component->get('title'))->toBe('Semanal de Ilustração')
        ->and($component->get('duration_days'))->toBe(7)
        ->and($component->get('prompts'))->toHaveCount(7)
        ->and($component->get('palette_colors'))->toBe(['#000000', '#7b68ee', '#2f4f4f', '#ffffff'])
        ->and($component->get('palette_source_url'))->toBe('https://lospec.com/palette-list/retro-pixel');
});

it('rejects JSON whose prompts length differs from duration_days', function () {
    config(['features.import_challenge_json' => true]);
    $user = User::factory()->create();
    Auth::login($user);

    $payload = json_encode([
        'title' => 'Mismatch',
        'duration_days' => 5,
        'prompts' => ['only', 'three', 'prompts'],
        'has_palette' => false,
    ]);

    Livewire::test('pages::challenges.create')
        ->set('importJson', $payload)
        ->call('importFromJson')
        ->assertHasErrors('importJson');
});

it('rejects malformed JSON', function () {
    config(['features.import_challenge_json' => true]);
    $user = User::factory()->create();
    Auth::login($user);

    Livewire::test('pages::challenges.create')
        ->set('importJson', '{not: valid json')
        ->call('importFromJson')
        ->assertHasErrors('importJson');
});

it('hides the import button when the feature flag is off', function () {
    config(['features.import_challenge_json' => false]);
    $user = User::factory()->create();
    Auth::login($user);

    Livewire::test('pages::challenges.create')
        ->assertDontSee('Import from JSON');
});

it('aborts the import action when the feature flag is off', function () {
    config(['features.import_challenge_json' => false]);
    $user = User::factory()->create();
    Auth::login($user);

    Livewire::test('pages::challenges.create')
        ->set('importJson', '{}')
        ->call('importFromJson')
        ->assertStatus(404);
});

it('pre-fills the create form when cloning a source challenge', function () {
    $user = User::factory()->create();
    Auth::login($user);

    $source = Challenge::factory()->withPalette()->create([
        'title' => 'Original Journey',
        'duration_days' => 3,
    ]);
    foreach (['Alpha', 'Beta', 'Gamma'] as $i => $text) {
        ChallengeDay::factory()->create([
            'challenge_id' => $source->id,
            'day_number' => $i + 1,
            'prompt_text' => $text,
        ]);
    }

    $component = Livewire::test('pages::challenges.create', ['challenge' => $source]);

    expect($component->get('originalChallengeId'))->toBe($source->id)
        ->and($component->get('clonedFromTitle'))->toBe('Original Journey')
        ->and($component->get('title'))->toBe('Original Journey')
        ->and($component->get('duration_days'))->toBe(3)
        ->and($component->get('prompts'))->toBe(['Alpha', 'Beta', 'Gamma'])
        ->and($component->get('palette_colors'))->toBe($source->palette_colors);
});

it('creates an independent customized clone keeping the lineage', function () {
    $owner = User::factory()->create();
    $source = Challenge::factory()->create([
        'creator_id' => $owner->id,
        'title' => 'Source',
        'duration_days' => 2,
    ]);
    ChallengeDay::factory()->create(['challenge_id' => $source->id, 'day_number' => 1, 'prompt_text' => 'One']);
    ChallengeDay::factory()->create(['challenge_id' => $source->id, 'day_number' => 2, 'prompt_text' => 'Two']);

    $cloner = User::factory()->create();
    Auth::login($cloner);

    Livewire::test('pages::challenges.create', ['challenge' => $source])
        ->set('title', 'My Customized Copy')
        ->set('prompts', ['Edited one', 'Edited two'])
        ->set('has_palette', false)
        ->call('save');

    $clone = Challenge::firstWhere('title', 'My Customized Copy');

    expect($clone)->not->toBeNull()
        ->and($clone->id)->not->toBe($source->id)
        ->and($clone->creator_id)->toBe($cloner->id)
        ->and($clone->original_challenge_id)->toBe($source->id)
        ->and($clone->days->pluck('prompt_text')->all())->toBe(['Edited one', 'Edited two']);

    // original is untouched
    expect($source->fresh()->days->pluck('prompt_text')->all())->toBe(['One', 'Two']);
});

it('404s when cloning a private challenge owned by someone else', function () {
    $owner = User::factory()->create();
    $private = Challenge::factory()->private()->create(['creator_id' => $owner->id]);

    Auth::login(User::factory()->create());

    Livewire::test('pages::challenges.create', ['challenge' => $private])
        ->assertStatus(404);
});

it('drops a tampered original_challenge_id pointing to a private foreign challenge', function () {
    $owner = User::factory()->create();
    $private = Challenge::factory()->private()->create(['creator_id' => $owner->id]);

    $attacker = User::factory()->create();
    Auth::login($attacker);

    Livewire::test('pages::challenges.create')
        ->set('originalChallengeId', $private->id)
        ->set('title', 'Sneaky')
        ->set('duration_days', 1)
        ->set('prompts', ['x'])
        ->set('has_palette', false)
        ->call('save');

    expect(Challenge::firstWhere('title', 'Sneaky')->original_challenge_id)->toBeNull();
});

it('stores an uploaded cover image', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Auth::login($user);

    Livewire::test('pages::challenges.create')
        ->set('title', 'With Cover')
        ->set('duration_days', 1)
        ->set('prompts', ['Day one'])
        ->set('has_palette', false)
        ->set('coverFile', UploadedFile::fake()->image('cover.jpg', 800, 600))
        ->call('save');

    $challenge = Challenge::firstWhere('title', 'With Cover');

    expect($challenge->cover_path)->not->toBeNull()
        ->and($challenge->cover_path)->toStartWith("covers/{$user->id}/");
    Storage::disk('public')->assertExists($challenge->cover_path);
});

it('rejects a non-image cover', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Auth::login($user);

    Livewire::test('pages::challenges.create')
        ->set('coverFile', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['coverFile' => 'image']);
});

it('copies the source cover to an independent path when cloning', function () {
    Storage::fake('public');
    Storage::disk('public')->put('covers/original/cover.jpg', 'original-bytes');

    $owner = User::factory()->create();
    $source = Challenge::factory()->create([
        'creator_id' => $owner->id,
        'title' => 'Has Cover',
        'duration_days' => 1,
        'cover_path' => 'covers/original/cover.jpg',
    ]);
    ChallengeDay::factory()->create(['challenge_id' => $source->id, 'day_number' => 1, 'prompt_text' => 'One']);

    $cloner = User::factory()->create();
    Auth::login($cloner);

    Livewire::test('pages::challenges.create', ['challenge' => $source])
        ->set('title', 'Cloned Cover')
        ->call('save');

    $clone = Challenge::firstWhere('title', 'Cloned Cover');

    expect($clone->cover_path)->not->toBeNull()
        ->and($clone->cover_path)->not->toBe($source->cover_path)
        ->and($clone->cover_path)->toStartWith("covers/{$cloner->id}/");
    Storage::disk('public')->assertExists($clone->cover_path);
    // original file remains
    Storage::disk('public')->assertExists('covers/original/cover.jpg');
});
