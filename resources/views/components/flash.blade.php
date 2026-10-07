{{-- Éxito, aviso y error se muestran con SweetAlert (resources/js/alertas.js) --}}
@if (session()->hasAny(['exito', 'aviso', 'error']))
    <script type="application/json" id="mensajes-flash">@json(['exito' => session('exito'), 'aviso' => session('aviso'), 'error' => session('error')])</script>
@endif
@if ($errors->any())
    <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 no-print">
        <x-icon name="exclamation" class="mt-0.5 size-5 shrink-0" />
        <div>
            <div class="font-semibold">Revise los datos del formulario:</div>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach (collect($errors->all())->unique()->take(6) as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
@if (session('credencial'))
    @php $credencial = session('credencial'); @endphp
    <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 no-print">
        <div class="flex items-center gap-2 font-semibold"><x-icon name="key" class="size-5" /> Credenciales temporales de {{ $credencial['nombre'] }}</div>
        <p class="mt-1">Entréguelas al usuario; se muestran <b>solo esta vez</b>. Deberá cambiar la contraseña al iniciar sesión.</p>
        <div class="mt-2 inline-flex flex-wrap gap-x-6 gap-y-1 rounded-lg bg-white px-3 py-2 font-mono text-sm ring-1 ring-sky-200">
            <span>Usuario: <b>{{ $credencial['usuario'] }}</b></span>
            <span>Contraseña: <b>{{ $credencial['password'] }}</b></span>
        </div>
    </div>
@endif
