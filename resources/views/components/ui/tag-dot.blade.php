@php
    use App\Enums\TagColor;
@endphp
@props([
    'color' => TagColor::Zinc,
    'showLabel' => false,
])
@php
    $resolved = $color instanceof TagColor ? $color : TagColor::tryFrom((string) $color) ?? TagColor::Zinc;
@endphp
<span
    {{ $attributes->class(['inline-flex items-center gap-1.5 text-xs font-medium', $resolved->ringClass()]) }}
    title="{{ __('Tag: :label', ['label' => $resolved->label()]) }}"
>
    <span class="size-2 shrink-0 rounded-full {{ $resolved->dotClass() }}" aria-hidden="true"></span>
    <span class="sr-only">{{ $resolved->label() }}</span>
    @if ($showLabel && $resolved !== TagColor::Zinc)
        <span aria-hidden="true" class="text-[11px] leading-none">{{ $resolved->shapeCue() }} {{ $resolved->label() }}</span>
    @elseif ($resolved !== TagColor::Zinc)
        <span aria-hidden="true" class="text-[10px] leading-none opacity-80">{{ $resolved->shapeCue() }}</span>
    @endif
</span>
