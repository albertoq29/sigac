@props(['title' => null])
@php
    $usuario = auth()->user();
    $esControl = $usuario?->esControl();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}SIGAC</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>♪</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased" x-data="{ menu: false }">

{{-- Barra lateral --}}
<div x-cloak x-show="menu" x-transition.opacity class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="menu = false"></div>

<aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-300 transition-transform lg:translate-x-0"
       :class="menu && 'translate-x-0'">
    <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-xl font-bold text-slate-900">♪</div>
        <div class="min-w-0">
            <div class="text-sm font-bold tracking-wide text-white">SIGAC</div>
            <div class="truncate text-[11px] leading-tight text-slate-400">Conservatorio "Carlos Afanador Real"</div>
        </div>
        <button class="ml-auto text-slate-400 hover:text-white lg:hidden" @click="menu = false"><x-icon name="x" /></button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
        <x-nav-link :href="route('inicio')" :active="request()->routeIs('inicio')" icon="home">Inicio</x-nav-link>

        @if ($esControl)
            <x-nav-link :href="route('estudiantes.index')" :active="request()->routeIs('estudiantes.*', 'matriculas.*', 'documentos.constancia', 'documentos.historial')" icon="users">Estudiantes</x-nav-link>
            <x-nav-link :href="route('catedras.index')" :active="request()->routeIs('catedras.*', 'asistencia.*', 'notas.*', 'documentos.acta')" icon="music">Cátedras</x-nav-link>

            <div class="px-3 pt-5 pb-1 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Año escolar</div>
            <x-nav-link :href="route('anios.index')" :active="request()->routeIs('anios.*')" icon="calendar">Años escolares</x-nav-link>
            @if ($reinscribir = $aniosEscolares->first(fn ($a) => ! $a->estaCerrado() && $aniosEscolares->contains(fn ($o) => $o->nombre < $a->nombre)))
                <x-nav-link :href="route('reinscripcion.index', $reinscribir)" :active="request()->routeIs('reinscripcion.*')" icon="switch">Reinscripción</x-nav-link>
            @endif
            @php $reaperturasPendientes = \App\Models\ReaperturaJornada::query()->sinRevisar()->whereHas('jornada.catedra', fn ($q) => $q->where('anio_escolar_id', $anioContexto?->id))->count(); @endphp
            <x-nav-link :href="route('reaperturas.index')" :active="request()->routeIs('reaperturas.*')" icon="unlock">
                Reaperturas de asistencia
                @if ($reaperturasPendientes)
                    <span class="ml-1 rounded-full bg-amber-400 px-1.5 text-[11px] font-bold text-slate-900">{{ $reaperturasPendientes }}</span>
                @endif
            </x-nav-link>
            <x-nav-link :href="route('offline.index')" :active="request()->routeIs('offline.*')" icon="sin-conexion">Asistencia sin conexión</x-nav-link>
            <x-nav-link :href="route('estadisticas')" :active="request()->routeIs('estadisticas')" icon="chart">Estadísticas</x-nav-link>

            <div class="px-3 pt-5 pb-1 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Administración</div>
            <x-nav-link :href="route('usuarios.index')" :active="request()->routeIs('usuarios.*')" icon="user">Usuarios y profesores</x-nav-link>
            <x-nav-link :href="route('asignaturas.index')" :active="request()->routeIs('asignaturas.*')" icon="book">Asignaturas</x-nav-link>
            <x-nav-link :href="route('niveles.index')" :active="request()->routeIs('niveles.*')" icon="layers">Niveles</x-nav-link>
            <x-nav-link :href="route('ajustes.edit')" :active="request()->routeIs('ajustes.*')" icon="cog">Ajustes</x-nav-link>
        @else
            <x-nav-link :href="route('catedras.index')" :active="request()->routeIs('catedras.*', 'asistencia.*', 'notas.*', 'documentos.acta')" icon="music">Mis cátedras</x-nav-link>
            <x-nav-link :href="route('offline.index')" :active="request()->routeIs('offline.*')" icon="sin-conexion">Asistencia sin conexión</x-nav-link>
        @endif
    </nav>

    <div class="border-t border-white/10 p-3">
        <div class="rounded-xl px-3 py-2">
            <div class="truncate text-sm font-semibold text-white">{{ $usuario?->name }}</div>
            <div class="text-xs text-slate-400">{{ $usuario?->rol->label() }} · {{ $usuario?->username }}</div>
        </div>
        <div class="mt-1 flex gap-1">
            <a href="{{ route('cuenta.password') }}" class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs text-slate-300 hover:bg-white/10 hover:text-white">
                <x-icon name="key" class="size-4" /> Contraseña
            </a>
            <form method="POST" action="{{ route('logout') }}" class="flex-1">
                @csrf
                <button class="flex w-full items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs text-slate-300 hover:bg-white/10 hover:text-white">
                    <x-icon name="logout" class="size-4" /> Salir
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="lg:pl-64">
    {{-- Barra superior --}}
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur no-print">
        <div class="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <button class="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100 lg:hidden" @click="menu = true" aria-label="Abrir menú">
                <x-icon name="menu" />
            </button>

            <form method="POST" action="{{ route('anio.seleccionar') }}" class="flex items-center gap-2">
                @csrf
                <label for="anio-trabajo" class="hidden text-xs font-semibold tracking-wide text-slate-500 uppercase sm:block">Año escolar</label>
                <select id="anio-trabajo" name="anio_escolar_id" onchange="this.form.submit()"
                        class="rounded-lg border border-slate-200 bg-white py-1.5 pr-8 pl-3 text-sm font-semibold text-slate-900 focus:border-slate-900 focus:ring-2 focus:ring-slate-900/15 focus:outline-none">
                    @forelse ($aniosEscolares as $opcion)
                        <option value="{{ $opcion->id }}" @selected($anioContexto?->id === $opcion->id)>{{ $opcion->nombre }}</option>
                    @empty
                        <option>Sin años escolares</option>
                    @endforelse
                </select>
                @if ($anioContexto)
                    <x-badge :color="$anioContexto->estado->color()">{{ $anioContexto->estado->label() }}</x-badge>
                @endif
            </form>

            <div class="ml-auto hidden text-right text-xs text-slate-500 sm:block">
                {{ ucfirst(now()->translatedFormat('l, d \d\e F \d\e Y')) }}
            </div>
        </div>
        @if ($anioContexto?->estaCerrado())
            <div class="border-t border-amber-200 bg-amber-50 px-4 py-1.5 text-center text-xs font-medium text-amber-800 sm:px-6 lg:px-8">
                Está consultando el año escolar {{ $anioContexto->nombre }}, que ya fue cerrado: sus notas y asistencias son de solo lectura.
            </div>
        @endif
    </header>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <x-flash />
        {{ $slot }}
    </main>
</div>

@stack('scripts')
</body>
</html>
