@props(['color' => 'slate'])
@php
    $clases = [
        'slate' => 'badge-slate',
        'emerald' => 'badge-emerald',
        'rose' => 'badge-rose',
        'amber' => 'badge-amber',
        'sky' => 'badge-sky',
        'violet' => 'badge-violet',
    ];
@endphp
<span {{ $attributes->class(['badge', $clases[$color] ?? 'badge-slate']) }}>{{ $slot }}</span>
