@props([
    'user',
    'withAvatar' => false,
    'avatarSize' => 'xs',
    'class' => '',
])

<a
    href="{{ route('user.profile', $user->username) }}"
    wire:navigate
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 hover:underline ' . $class]) }}
>
    @if ($withAvatar)
        <span class="inline-block size-5 overflow-hidden rounded-full bg-zinc-700 align-middle">
            @if ($user->avatar_path)
                <img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover" />
            @else
                <span class="flex size-full items-center justify-center text-[10px] uppercase">{{ $user->initials() }}</span>
            @endif
        </span>
    @endif
    <span>{{ $user->name }}</span>
</a>
