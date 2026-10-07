@props(['title', 'subtitle' => null, 'back' => null])
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0 space-y-1">
        @if ($back)
            <a href="{{ $back }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-900 no-print">
                <x-icon name="arrow-left" class="size-3.5" /> Volver
            </a>
        @endif
        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 no-print">{{ $actions }}</div>
    @endisset
</div>
