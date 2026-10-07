<x-layouts.app title="Editar estudiante">
    <x-page-header title="Editar datos del estudiante" :subtitle="$estudiante->apellidos_nombres" :back="route('estudiantes.show', $estudiante)" />

    <form method="POST" action="{{ route('estudiantes.update', $estudiante) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card">
            <div class="card-header"><h2 class="card-title">Datos personales</h2></div>
            <div class="card-body space-y-4">
                @include('estudiantes._datos')
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('estudiantes.show', $estudiante) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar cambios</button>
        </div>
    </form>
</x-layouts.app>
