@props([
    'user' => null,
    'size' => 'md',
    'unread' => false,
])

@php
    $sizes = [
        'sm' => 'size-8',
        'md' => 'size-10',
    ];
    $cls = $sizes[$size] ?? $sizes['md'];
    $ring = $unread ? 'ring-2 ring-lime-500' : 'ring-1 ring-zinc-600';
@endphp

<span class="{{ $cls }} {{ $ring }} block shrink-0 overflow-hidden rounded-full bg-zinc-700">
    @if ($user?->avatar_path)
        <img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover" />
    @elseif ($user)
        <span class="flex size-full items-center justify-center text-xs font-medium uppercase">{{ $user->initials() }}</span>
    @else
        <span class="flex size-full items-center justify-center opacity-50">
            <flux:icon.user variant="micro" />
        </span>
    @endif
</span>
