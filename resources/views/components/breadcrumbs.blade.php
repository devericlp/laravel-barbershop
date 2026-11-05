<flux:breadcrumbs>
    <flux:breadcrumbs.item :href="$homeUrl()" icon="home" :aria-label="__('messages.home')" wire:navigate />
    <flux:breadcrumbs.item class="whitespace-nowrap max-sm:hidden">{{ $context() }}</flux:breadcrumbs.item>
    @if ($title)
        <flux:breadcrumbs.item aria-current="page">{{ $title }}</flux:breadcrumbs.item>
    @endif
</flux:breadcrumbs>
