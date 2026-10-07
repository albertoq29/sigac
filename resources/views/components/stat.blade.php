@props(['label', 'value', 'hint' => null, 'color' => 'slate', 'icon' => null])
@php
    $acentos = [
        'slate' => 'bg-slate-900 text-white',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'rose' => 'bg-rose-50 text-rose-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'sky' => 'bg-sky-50 text-sky-700',
        'violet' => 'bg-violet-50 text-violet-700',
    ];
@endphp
<div {{ $attributes->class('card p-5') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $label }}</div>
        @if ($icon)
            <div class="flex size-9 items-center justify-center rounded-xl {{ $acentos[$color] ?? $acentos['slate'] }}">
                <x-icon :name="$icon" class="size-5" />
            </div>
        @endif
    </div>
    <div class="mt-2 text-3xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
    @endif
</div>
