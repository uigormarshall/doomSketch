@php $authUser = auth()->user(); @endphp

<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="$authUser->name"
        :initials="$authUser->initials()"
        :avatar="$authUser->avatarUrl()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <a href="{{ route('user.profile', $authUser->username) }}" wire:navigate class="block">
            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                @if ($authUser->avatar_path)
                    <flux:avatar :src="$authUser->avatarUrl()" />
                @else
                    <flux:avatar :name="$authUser->name" :initials="$authUser->initials()" />
                @endif
                <div class="grid flex-1 text-start text-sm leading-tight">
                    <flux:heading class="truncate">{{ $authUser->name }}</flux:heading>
                    <flux:text class="truncate opacity-70">@{{ $authUser->username }}</flux:text>
                </div>
            </div>
        </a>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('user.profile', $authUser->username)" icon="user" wire:navigate>
                {{ __('View my profile') }}
            </flux:menu.item>
            <flux:menu.item :href="route('notifications.index')" icon="bell" wire:navigate>
                {{ __('Notifications') }}
            </flux:menu.item>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
