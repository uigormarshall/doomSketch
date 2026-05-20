<?php

use App\Models\Challenge;
use App\Models\User;
use Livewire\Livewire;

it('renders the cover, title, palette and both CTAs on explore', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    $challenge = Challenge::factory()->withPalette()->withCover()->create([
        'title' => 'Cover Challenge',
        'is_private' => false,
    ]);

    Livewire::test('pages::challenges.explore')
        ->assertSee('Cover Challenge')
        ->assertSee('/storage/'.$challenge->cover_path, false)
        ->assertSee($challenge->palette_colors[0])
        ->assertSee(route('challenges.template', $challenge), false)
        ->assertSee(route('challenges.clone', $challenge), false);
});

it('falls back to palette stripes when there is no cover', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    $challenge = Challenge::factory()->withPalette()->create([
        'title' => 'No Cover',
        'is_private' => false,
        'cover_path' => null,
    ]);

    Livewire::test('pages::challenges.explore')
        ->assertSee('No Cover')
        ->assertDontSee('/storage/covers', false)
        ->assertSee('background-color: '.$challenge->palette_colors[0], false);
});

it('hides private and own challenges from explore', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Challenge::factory()->create(['title' => 'My Own', 'creator_id' => $viewer->id, 'is_private' => false]);
    Challenge::factory()->private()->create(['title' => 'Secret']);

    Livewire::test('pages::challenges.explore')
        ->assertDontSee('My Own')
        ->assertDontSee('Secret');
});
