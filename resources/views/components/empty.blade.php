@props(['icon' => 'info', 'title' => 'Sin registros'])
<div {{ $attributes->class('flex flex-col items-center justify-center gap-2 px-6 py-12 text-center') }}>
    <div class="flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <div class="text-sm font-semibold text-slate-700">{{ $title }}</div>
    @if (trim($slot))
        <div class="max-w-md text-sm text-slate-500">{{ $slot }}</div>
    @endif
</div>
