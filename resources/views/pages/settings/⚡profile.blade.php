<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $bio = '';

    public $avatar;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->username = $user->username ?? '';
        $this->email = $user->email;
        $this->bio = $user->bio ?? '';
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id) + [
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($this->avatar) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $validated['avatar_path'] = $this->avatar->store("avatars/{$user->id}", 'public');
        }

        unset($validated['avatar']);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->avatar = null;

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        $this->avatar = null;

        Flux::toast(variant: 'success', text: __('Avatar removed.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your public profile.')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div class="flex items-center gap-4">
                @php $currentAvatar = $avatar?->temporaryUrl() ?? Auth::user()->avatarUrl(); @endphp
                <div class="size-20 overflow-hidden rounded-full ring-2 ring-zinc-700 bg-zinc-800 flex items-center justify-center">
                    @if ($currentAvatar)
                        <img src="{{ $currentAvatar }}" alt="" class="size-full object-cover" />
                    @else
                        <flux:text size="xl" class="opacity-60">{{ Auth::user()->initials() }}</flux:text>
                    @endif
                </div>
                <div class="flex-1 space-y-2">
                    <flux:input wire:model="avatar" type="file" accept="image/*" :label="__('Avatar (max 2MB)')" />
                    @if (Auth::user()->avatar_path)
                        <flux:button type="button" variant="ghost" size="sm" icon="trash" wire:click="removeAvatar">
                            {{ __('Remove avatar') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />

            <flux:input
                wire:model="username"
                :label="__('Username')"
                :description="__('Lowercase letters, numbers and underscores. 3-30 characters.')"
                type="text"
                required
            />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <flux:textarea wire:model="bio" :label="__('Bio')" rows="3" maxlength="500" />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
