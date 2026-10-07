@props(['href', 'active' => false, 'icon' => null])
<a href="{{ $href }}"
   {{ $attributes->class([
       'flex items-center gap-3 rounded-xl px-3 py-2 font-medium transition',
       'bg-white/10 text-white' => $active,
       'text-slate-300 hover:bg-white/5 hover:text-white' => ! $active,
   ]) }}>
    @if ($icon)
        <x-icon :name="$icon" @class(['size-5 shrink-0', 'text-white' => $active, 'text-slate-400' => ! $active]) />
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
