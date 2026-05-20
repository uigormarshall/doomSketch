<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <title>{{ config('app.name') }} · {{ __('Daily art challenges') }}</title>
    </head>
    <body class="min-h-screen bg-gradient-to-b from-neutral-950 via-zinc-950 to-neutral-900 text-zinc-100 antialiased">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <x-app-logo-icon class="size-7 fill-current text-white" />
                <span class="text-lg">{{ config('app.name') }}</span>
            </a>
            <div class="flex items-center gap-2">
                <x-locale-switcher />
                <flux:button :href="route('login')" wire:navigate variant="ghost" size="sm">
                    {{ __('Log in') }}
                </flux:button>
                <flux:button :href="route('register')" wire:navigate variant="primary" size="sm">
                    {{ __('Sign up') }}
                </flux:button>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-20 px-6 pb-20">
            <section class="grid items-center gap-10 pt-10 md:grid-cols-2 md:pt-20">
                <div class="space-y-6">
                    <flux:badge color="lime" size="sm">{{ __('Indie · Dark · Cronometrado') }}</flux:badge>
                    <h1 class="text-balance text-4xl font-bold leading-tight tracking-tight md:text-6xl">
                        {{ __('Draw daily.') }}<br />
                        <span class="bg-gradient-to-r from-lime-400 to-emerald-300 bg-clip-text text-transparent">
                            {{ __('Without breaking your streak.') }}
                        </span>
                    </h1>
                    <p class="max-w-lg text-balance text-lg opacity-80">
                        {{ __('Doomsketch is a community of artists who run timed challenges — restricted palettes, daily prompts, and zero punishment for missing a day. You set your own pace.') }}
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <flux:button :href="route('register')" wire:navigate variant="primary" size="base" icon="plus">
                            {{ __('Create free account') }}
                        </flux:button>
                        <flux:button :href="route('login')" wire:navigate variant="ghost" size="base">
                            {{ __('I already have an account') }}
                        </flux:button>
                    </div>
                </div>

                <div class="relative">
                    @if ($arts->isNotEmpty())
                        <div class="grid grid-cols-3 gap-2 rounded-xl border border-zinc-800 bg-zinc-900/50 p-2 shadow-2xl">
                            @foreach ($arts->take(9) as $a)
                                <div class="aspect-square overflow-hidden rounded-md">
                                    <img
                                        src="/storage/{{ $a->image_path }}"
                                        alt=""
                                        class="size-full object-cover"
                                        loading="lazy"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="grid aspect-square place-items-center rounded-xl border border-dashed border-zinc-700">
                            <flux:text class="opacity-60">{{ __('Your art could be here.') }}</flux:text>
                        </div>
                    @endif
                </div>
            </section>

            <section class="space-y-6">
                <div class="space-y-1 text-center">
                    <h2 class="text-3xl font-bold tracking-tight">{{ __('How it works') }}</h2>
                    <p class="opacity-70">{{ __('Three steps to start drawing today.') }}</p>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ([
                        ['icon' => 'globe-alt', 'title' => __('Pick a challenge'), 'body' => __('Browse public challenges from the community or create your own with custom prompts.')],
                        ['icon' => 'paint-brush', 'title' => __('Draw daily'), 'body' => __('Each day has its own prompt. Restricted palettes are optional — and copyable in one click.')],
                        ['icon' => 'sparkles', 'title' => __('Share your progress'), 'body' => __('Submit your art when you want. Missed a day? Catch up retroactively. No streaks broken.')],
                    ] as $step)
                        <article class="space-y-2 rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                            <flux:icon :name="$step['icon']" class="size-7 text-lime-400" />
                            <h3 class="text-lg font-semibold">{{ $step['title'] }}</h3>
                            <flux:text class="opacity-80">{{ $step['body'] }}</flux:text>
                        </article>
                    @endforeach
                </div>
            </section>

            @if ($arts->count() > 9)
                <section class="space-y-4">
                    <div class="flex items-end justify-between">
                        <div>
                            <h2 class="text-2xl font-bold tracking-tight">{{ __('Recent art from the community') }}</h2>
                            <p class="opacity-70">{{ __('Real submissions from real artists.') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($arts as $a)
                            <figure class="group relative aspect-square overflow-hidden rounded-md">
                                <img
                                    src="/storage/{{ $a->image_path }}"
                                    alt=""
                                    class="size-full object-cover transition group-hover:scale-105"
                                    loading="lazy"
                                />
                                <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 to-transparent p-2 text-xs opacity-0 transition group-hover:opacity-100">
                                    <div class="truncate font-medium">{{ $a->userChallenge->user->name }}</div>
                                    <div class="truncate opacity-70">{{ $a->userChallenge->challenge->title }}</div>
                                    @if ($a->caption)
                                        <div class="line-clamp-2 italic opacity-60" title="{{ $a->caption }}">
                                            “{{ $a->caption }}”
                                        </div>
                                    @endif
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-2xl border border-zinc-800 bg-gradient-to-br from-lime-950/30 to-zinc-900 p-10 text-center">
                <h2 class="text-3xl font-bold tracking-tight">{{ __('Ready to start drawing?') }}</h2>
                <p class="mt-2 opacity-80">{{ __('Free forever. No credit card needed.') }}</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <flux:button :href="route('register')" wire:navigate variant="primary" size="base" icon="plus">
                        {{ __('Create free account') }}
                    </flux:button>
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-800">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6 text-xs opacity-60">
                <span>{{ config('app.name') }} · {{ now()->year }}</span>
                <span>{{ __('Made for artists, by artists.') }}</span>
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
