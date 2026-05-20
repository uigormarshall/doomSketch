@php
    $current = app()->getLocale();
    $locales = [
        'pt_BR' => ['label' => 'Português (Brasil)', 'short' => 'PT'],
        'en' => ['label' => 'English (US)', 'short' => 'EN'],
    ];
@endphp

<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" icon="language" size="sm" data-test="locale-switcher">
        {{ $locales[$current]['short'] ?? strtoupper($current) }}
    </flux:button>

    <flux:menu>
        @foreach ($locales as $code => $meta)
            <form method="POST" action="{{ route('locale.switch', $code) }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    :icon="$current === $code ? 'check' : null"
                    class="w-full cursor-pointer"
                    :data-test="'locale-option-' . $code"
                >
                    {{ $meta['label'] }}
                </flux:menu.item>
            </form>
        @endforeach
    </flux:menu>
</flux:dropdown>
