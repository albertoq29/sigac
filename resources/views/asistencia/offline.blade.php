<x-layouts.app title="Asistencia sin conexión">
    <x-page-header title="Asistencia sin conexión"
                   subtitle="Pase lista sin internet y suba las asistencias después con un código." />

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- 1. Descargar --}}
        <section class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2"><span class="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">1</span> Descargar el archivo</h2>
            </div>
            <div class="card-body space-y-4 text-sm text-slate-600">
                <p>Descárguelo <b>mientras tiene internet</b>. Es una página que funciona sin conexión en el teléfono o la computadora, con sus cátedras y listas de alumnos de {{ $anio?->nombre ?? 'este año' }}.</p>
                <ol class="list-decimal space-y-1 pl-5">
                    <li>Ábralo con el navegador (Chrome, Edge…), aunque no tenga internet.</li>
                    <li>Elija cátedra y fecha, marque la asistencia y toque <b>Guardar este día</b>. Puede guardar varios días.</li>
                    <li>Toque <b>Generar código y descargar .txt</b>.</li>
                </ol>

                @if (! $anio)
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">No hay un año escolar en curso.</p>
                @elseif (auth()->user()->esControl())
                    <form method="GET" action="{{ route('offline.descargar') }}" class="space-y-3">
                        <x-field label="Profesor" name="profesor">
                            <select name="profesor" class="input" required>
                                @foreach ($profesores as $profesor)
                                    <option value="{{ $profesor->id }}" @disabled(! $profesor->catedras_count)>{{ $profesor->name }} ({{ $profesor->catedras_count }} cátedras)</option>
                                @endforeach
                            </select>
                        </x-field>
                        <button class="btn btn-primary w-full"><x-icon name="descargar" class="size-4" /> Descargar archivo del profesor</button>
                    </form>
                @else
                    <a href="{{ route('offline.descargar') }}" class="btn btn-primary w-full"><x-icon name="descargar" class="size-4" /> Descargar mi archivo sin conexión</a>
                    <p class="text-xs text-slate-500">Vuelva a descargarlo si cambian los alumnos de sus cátedras.</p>
                @endif
            </div>
        </section>

        {{-- 2. Subir --}}
        <section class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2"><span class="flex size-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">2</span> Subir las asistencias</h2>
            </div>
            <form method="POST" action="{{ route('offline.revisar') }}" enctype="multipart/form-data" class="card-body space-y-4"
                  x-data="{ nombre: '' }">
                @csrf
                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center hover:border-slate-500">
                    <x-icon name="subir" class="size-8 text-slate-400" />
                    <span class="text-sm font-semibold text-slate-700" x-text="nombre || 'Seleccione el archivo .txt'"></span>
                    <span class="text-xs text-slate-500">asistencias-sigac-AAAA-MM-DD.txt</span>
                    <input type="file" name="archivo" accept=".txt,text/plain" class="sr-only" @change="nombre = $event.target.files[0]?.name ?? ''">
                </label>
                @error('archivo') <p class="error-text">{{ $message }}</p> @enderror

                <div class="flex items-center gap-3 text-xs font-semibold text-slate-400 uppercase"><span class="h-px flex-1 bg-slate-200"></span>o pegue el código<span class="h-px flex-1 bg-slate-200"></span></div>

                <x-field name="codigo">
                    <textarea name="codigo" rows="4" class="input font-mono text-xs" placeholder="SIGAC1-…">{{ old('codigo') }}</textarea>
                </x-field>

                <button class="btn btn-success w-full"><x-icon name="eye" class="size-4" /> Revisar antes de registrar</button>
            </form>
        </section>
    </div>
</x-layouts.app>
