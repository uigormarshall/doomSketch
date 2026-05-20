<?php

use Illuminate\Support\Facades\App;

it('stores the chosen locale in the session', function () {
    $response = $this->from('/')->post('/locale/en');

    $response->assertRedirect('/');
    expect(session('locale'))->toBe('en');
});

it('rejects unsupported locales', function () {
    $this->from('/')->post('/locale/fr')->assertStatus(422);

    expect(session('locale'))->toBeNull();
});

it('applies the session locale on subsequent requests', function () {
    $this->withSession(['locale' => 'en'])->get('/');

    expect(App::getLocale())->toBe('en');
});

it('honours the Accept-Language header when no session locale is set', function () {
    $this->get('/', ['Accept-Language' => 'pt-BR,pt;q=0.9']);

    expect(App::getLocale())->toBe('pt_BR');
});

it('falls back to the first supported locale when Accept-Language has no match', function () {
    $this->get('/', ['Accept-Language' => 'ja,ko;q=0.9']);

    expect(App::getLocale())->toBe('pt_BR');
});

it('prefers the session locale over the Accept-Language header', function () {
    $this->withSession(['locale' => 'en'])
        ->get('/', ['Accept-Language' => 'pt-BR']);

    expect(App::getLocale())->toBe('en');
});

it('translates dashboard label to portuguese for pt_BR locale', function () {
    expect(__('Dashboard', [], 'pt_BR'))->toBe('Painel');
});

it('keeps english strings for en locale', function () {
    expect(__('Dashboard', [], 'en'))->toBe('Dashboard');
});
