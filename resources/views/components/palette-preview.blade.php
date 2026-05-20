@props([
    'colors' => [],
    'size' => 'md',
    'clickable' => false,
])

@php
    $sizes = [
        'sm' => 'w-4 h-4',
        'md' => 'w-6 h-6',
        'lg' => 'w-10 h-10',
    ];
    $cls = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }}>
    @foreach ($colors as $hex)
        @if ($clickable)
            <button
                type="button"
                x-data
                @click="navigator.clipboard.writeText('{{ $hex }}'); $flux.toast({ text: '{{ __('Copied :hex', ['hex' => $hex]) }}', variant: 'success' })"
                class="{{ $cls }} rounded-full ring-1 ring-zinc-700 transition hover:scale-110 hover:ring-2 hover:ring-zinc-400 cursor-pointer"
                style="background-color: {{ $hex }}"
                :title="'{{ $hex }}'"
                aria-label="{{ $hex }}"
            ></button>
        @else
            <span
                class="{{ $cls }} rounded-full ring-1 ring-zinc-700 inline-block"
                style="background-color: {{ $hex }}"
                title="{{ $hex }}"
                aria-label="{{ $hex }}"
            ></span>
        @endif
    @endforeach
</div>
