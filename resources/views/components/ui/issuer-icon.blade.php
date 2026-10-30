@props([
    'issuer' => '',
    'initials' => null,
    'size' => 'md',
])

@php
    use App\Models\Authenticator;
    use App\Support\IssuerBrand;

    $brand = IssuerBrand::detect(issuer: (string) $issuer);
    $resolvedInitials = $initials ?? Authenticator::initialsFor(issuer: (string) $issuer);

    $dimensionClass = match ($size) {
        'sm' => 'size-8 text-xs',
        'lg' => 'size-12 text-base',
        default => 'size-10 text-sm',
    };

    $iconDimensionClass = match ($size) {
        'sm' => 'size-4',
        'lg' => 'size-6',
        default => 'size-5',
    };
@endphp

<div {{ $attributes->merge(['class' => "grid {$dimensionClass} shrink-0 place-items-center rounded-lg border border-zinc-200 bg-zinc-100 font-serif font-bold text-zinc-800 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200"]) }}>
    @if ($brand)
        <x-dynamic-component :component="'icons.brands.' . $brand" :class="$iconDimensionClass . ' fill-current text-zinc-800 dark:text-zinc-200'" />
    @else
        <span>{{ $resolvedInitials }}</span>
    @endif
</div>
