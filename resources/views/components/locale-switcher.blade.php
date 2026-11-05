@props(['position' => 'bottom', 'align' => 'end', 'compact' => false])

@php
    $shortNames = ['pt_BR' => __('messages.pt'), 'en' => __('messages.en')];
    $names = ['pt_BR' => __('messages.portuguese_brazil'), 'en' => __('messages.english')];
@endphp

<flux:dropdown :position="$position" :align="$align" {{ $attributes }}>
    @if ($compact)
        <flux:button variant="ghost" size="sm" square icon="language" :aria-label="__('messages.language')" />
    @else
        <flux:button variant="ghost" size="sm" icon="language" icon:trailing="chevron-down">
            {{ $shortNames[app()->getLocale()] }}
        </flux:button>
    @endif

    <flux:menu>
        @foreach (config('app.available_locales') as $locale)
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="locale" value="{{ $locale }}" />

                <flux:menu.item as="button" type="submit" class="w-full" :icon="app()->isLocale($locale) ? 'check' : null">
                    {{ $names[$locale] }}
                </flux:menu.item>
            </form>
        @endforeach
    </flux:menu>
</flux:dropdown>
