<x-layouts.app title="Cambiar contraseña">
    <x-page-header title="Cambiar contraseña" subtitle="Use al menos 8 caracteres. No la comparta con nadie." />

    <form method="POST" action="{{ route('cuenta.password.update') }}" class="card max-w-xl space-y-5 p-6">
        @csrf
        @method('PUT')

        <x-field label="Contraseña actual" name="password_actual" required>
            <input id="password_actual" name="password_actual" type="password" class="input" required autocomplete="current-password">
        </x-field>

        <x-field label="Nueva contraseña" name="password" required>
            <input id="password" name="password" type="password" class="input" required autocomplete="new-password">
        </x-field>

        <x-field label="Repita la nueva contraseña" name="password_confirmation" required>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
        </x-field>

        <div class="flex justify-end">
            <button class="btn btn-primary"><x-icon name="check" class="size-4" /> Guardar contraseña</button>
        </div>
    </form>
</x-layouts.app>
