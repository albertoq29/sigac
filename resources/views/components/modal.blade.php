@props(['name', 'title', 'maxWidth' => 'max-w-lg'])
{{-- Se abre con: $dispatch('abrir-modal', 'nombre-del-modal') --}}
<div x-data="{ abierto: false }"
     x-on:abrir-modal.window="abierto = ($event.detail === @js($name))"
     x-on:keydown.escape.window="abierto = false"
     x-cloak x-show="abierto"
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto px-4 py-10 sm:py-20 no-print">
    <div class="fixed inset-0 bg-slate-900/50" x-show="abierto" x-transition.opacity @click="abierto = false"></div>
    <div class="relative w-full {{ $maxWidth }} rounded-2xl bg-white text-left shadow-xl ring-1 ring-slate-200" x-show="abierto" x-transition>
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
            <button type="button" class="text-slate-400 hover:text-slate-700" @click="abierto = false"><x-icon name="x" /></button>
        </div>
        <div class="p-5">
            {{ $slot }}
        </div>
    </div>
</div>
