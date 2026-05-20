<?php

use App\Http\Middleware\SetLocale;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    $arts = Submission::query()
        ->whereHas('userChallenge.challenge', fn ($q) => $q->where('is_private', false))
        ->with(['userChallenge.user:id,name,username', 'userChallenge.challenge:id,title'])
        ->latest()
        ->limit(60)
        ->get()
        ->shuffle()
        ->take(24);

    return view('welcome', ['arts' => $arts]);
})->name('home');

Route::post('locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 422);

    $request->session()->put('locale', $locale);

    return back();
})->name('locale.switch');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::feed')->name('dashboard');

    Route::livewire('u/{user:username}', 'pages::user-profile')->name('user.profile');
    Route::livewire('notifications', 'pages::notifications')->name('notifications.index');
    Route::livewire('art/{submission}', 'pages::art.show')->name('art.show');
    Route::livewire('challenges/create', 'pages::challenges.create')->name('challenges.create');
    Route::livewire('challenges/clone/{challenge}', 'pages::challenges.create')->name('challenges.clone');
    Route::livewire('explore', 'pages::challenges.explore')->name('challenges.explore');
    Route::livewire('my-challenges', 'pages::challenges.my-challenges')->name('my-challenges');
    Route::livewire('templates/{challenge}', 'pages::challenges.template')->name('challenges.template');
    Route::livewire('challenges/{userChallenge}', 'pages::challenges.show')->name('challenge.show');
});

require __DIR__.'/settings.php';
