@props([
    'heading',
    'subheading' => null,
    'back' => null,
    'backLabel' => null,
])

@php
    $backUrl = $back ?? route('vault');
    $backText = $backLabel ?? __('Back to Vault');
@endphp

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
            @if ($subheading)
                <flux:subheading>{{ $subheading }}</flux:subheading>
            @endif
        </div>

        <div>
            <flux:button
                icon="arrow-left"
                :href="$backUrl"
                variant="subtle"
                wire:navigate.hover
            >
                {{ $backText }}
            </flux:button>
        </div>
    </div>

    <flux:separator variant="subtle" />

    {{ $slot }}
</div>

