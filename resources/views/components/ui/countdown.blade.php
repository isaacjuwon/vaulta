@props([
    'remainingSeconds' => 30,
    'period' => 30,
])

<div {{ $attributes->class('space-y-1.5') }}>
    <div class="flex items-center justify-between text-[11px] opacity-75">
        <span class="inline-flex items-center gap-1" role="timer" aria-atomic="true">
            <flux:icon name="clock" class="size-3.5 shrink-0" />
            <span x-text="`${remaining}s`">{{ $remainingSeconds }}s</span>
        </span>
    </div>
    <div class="totp-bar" aria-hidden="true">
        <div
            class="totp-fill"
            :style="`width: ${Math.max(0, Math.min(100, (remaining / period) * 100))}%`"
            style="width: {{ round(($remainingSeconds / max(1, $period)) * 100, 2) }}%"
        ></div>
    </div>
</div>
