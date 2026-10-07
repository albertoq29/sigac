<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGAC · Asistencia sin conexión · {{ $datos['usuario']['nombre'] }}</title>
    <style>{!! $css !!}</style>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .oculto { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<header class="sticky top-0 z-20 bg-slate-900 text-white shadow">
    <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-lg font-bold text-slate-900">♪</div>
        <div class="min-w-0 flex-1">
            <div class="text-sm font-bold">SIGAC · Asistencia sin conexión</div>
            <div class="truncate text-xs text-slate-300">{{ $datos['usuario']['nombre'] }} · Año {{ $datos['anio'] }}</div>
        </div>
        <span id="estado-red" class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold">Sin conexión</span>
    </div>
</header>

<main class="mx-auto max-w-3xl space-y-4 px-4 py-4">
    <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-900">
        Listas descargadas el <b>{{ $datos['generado'] }}</b>. Pase lista aunque no tenga internet: todo queda guardado en este dispositivo.
        Al final genere el <b>código .txt</b> y súbalo en SIGAC → “Asistencia sin conexión”. Si cambian los alumnos de sus cátedras, descargue de nuevo este archivo.
    </div>

    {{-- Pase de lista --}}
    <section class="card">
        <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-[1fr_11rem]">
            <div>
                <label class="label" for="catedra">Cátedra</label>
                <select id="catedra" class="input"></select>
            </div>
            <div>
                <label class="label" for="fecha">Fecha</label>
                <input id="fecha" type="date" class="input">
            </div>
        </div>

        <div class="space-y-3 border-b border-slate-100 p-4">
            <div class="grid grid-cols-4 gap-1.5 text-center text-xs font-semibold">
                <span class="badge badge-emerald justify-center">P <span data-cuenta="P">0</span></span>
                <span class="badge badge-rose justify-center">A <span data-cuenta="A">0</span></span>
                <span class="badge badge-amber justify-center">R <span data-cuenta="R">0</span></span>
                <span class="badge badge-sky justify-center">J <span data-cuenta="J">0</span></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="btn btn-sm btn-secondary" data-todos="P">Todos presentes</button>
                <button type="button" class="btn btn-sm btn-secondary" data-todos="A">Todos ausentes</button>
            </div>
            <div id="aviso-dia" class="oculto rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800"></div>
        </div>

        <ul id="alumnos" class="divide-y divide-slate-100"></ul>

        <div class="border-t border-slate-100 p-4">
            <input id="observacion" class="input" maxlength="255" placeholder="Observación de la jornada (opcional)">
        </div>

        <div class="sticky bottom-0 z-10 flex items-center gap-2 rounded-b-2xl border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
            <div class="flex-1 text-xs text-slate-600"><b id="total-presentes">0</b> presentes · <b id="total-ausentes" class="text-rose-600">0</b> ausentes</div>
            <button type="button" id="guardar" class="btn btn-success">✓ Guardar este día</button>
        </div>
    </section>

    {{-- Pendientes por subir --}}
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Asistencias guardadas en este dispositivo</h2>
            <span id="cantidad-pendientes" class="badge badge-slate">0</span>
        </div>
        <ul id="pendientes" class="divide-y divide-slate-100"></ul>
        <p id="sin-pendientes" class="px-5 py-6 text-center text-sm text-slate-500">Aún no ha guardado asistencias.</p>
        <div class="grid gap-2 border-t border-slate-100 p-4 sm:grid-cols-2">
            <button type="button" id="generar" class="btn btn-primary">⬇ Generar código y descargar .txt</button>
            <button type="button" id="vaciar" class="btn btn-secondary">Borrar las ya subidas</button>
        </div>
    </section>

    {{-- Código generado --}}
    <section id="seccion-codigo" class="card oculto">
        <div class="card-header"><h2 class="card-title">Código para subir a SIGAC</h2></div>
        <div class="space-y-3 p-4">
            <p class="text-sm text-slate-600">
                Se descargó el archivo <b id="nombre-txt"></b>. Cuando tenga internet, súbalo en SIGAC → <b>Asistencia sin conexión</b>
                (<a href="{{ $urlCarga }}" class="link break-all">{{ $urlCarga }}</a>), o copie este código y péguelo allí.
            </p>
            <textarea id="codigo" class="input font-mono text-xs" rows="5" readonly></textarea>
            <div class="grid gap-2 sm:grid-cols-2">
                <button type="button" id="copiar" class="btn btn-secondary">Copiar código</button>
                <button type="button" id="descargar-de-nuevo" class="btn btn-secondary">Descargar .txt otra vez</button>
            </div>
        </div>
    </section>

    <p class="pb-6 text-center text-[11px] text-slate-400">SIGAC · {{ config('sigac.ajustes.institucion_nombre') }}</p>
</main>

<script type="application/json" id="datos">@json($datos)</script>
<script>
(() => {
    const D = JSON.parse(document.getElementById('datos').textContent);
    const PREFIJO = @json($prefijo);
    const CLAVE = 'sigac-offline-' + D.usuario.id + '-' + D.anio;
    const ESTADOS = [['P', 'Presente'], ['A', 'Ausente'], ['R', 'Retraso'], ['J', 'Justif.']];
    const $ = (id) => document.getElementById(id);

    // ---------- Almacenamiento local ----------
    let memoria = [];
    let almacenamientoOk = true;
    function cargar() {
        try { return JSON.parse(localStorage.getItem(CLAVE) || '[]'); }
        catch (e) { almacenamientoOk = false; return memoria; }
    }
    function guardarTodo(lista) {
        memoria = lista;
        try { localStorage.setItem(CLAVE, JSON.stringify(lista)); }
        catch (e) { almacenamientoOk = false; }
    }
    let pendientes = cargar();

    // ---------- Utilidades ----------
    const hoy = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };
    const fechaBonita = (f) => f.split('-').reverse().join('/');
    const catedra = (id) => D.catedras.find((c) => c.id === Number(id));
    const escapar = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Suma de verificación FNV-1a 32 bits (igual que en el servidor).
    function verificacion(texto) {
        let h = 0x811c9dc5;
        for (const byte of new TextEncoder().encode(texto)) { h ^= byte; h = Math.imul(h, 0x01000193) >>> 0; }
        return h.toString(16).padStart(8, '0');
    }
    function base64url(texto) {
        const bytes = new TextEncoder().encode(texto);
        let binario = '';
        bytes.forEach((b) => (binario += String.fromCharCode(b)));
        return btoa(binario).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    // ---------- Pase de lista ----------
    let estados = {};

    D.catedras.forEach((c) => {
        const opcion = document.createElement('option');
        opcion.value = c.id;
        opcion.textContent = c.nombre + (c.horario ? ' · ' + c.horario : '') + ' (' + c.alumnos.length + ')';
        $('catedra').appendChild(opcion);
    });
    // Empezar por la primera cátedra que tenga alumnos.
    $('catedra').value = (D.catedras.find((c) => c.alumnos.length) ?? D.catedras[0]).id;
    $('fecha').value = hoy();
    $('fecha').max = hoy();
    if (D.fechaMinima) $('fecha').min = D.fechaMinima;

    function pintarLista() {
        const c = catedra($('catedra').value);
        const fecha = $('fecha').value;
        const previo = pendientes.find((p) => p.c === c.id && p.f === fecha);
        estados = {};
        c.alumnos.forEach((a) => (estados[a.id] = previo?.e?.[a.id] ?? 'P'));
        $('observacion').value = previo?.o ?? '';
        $('aviso-dia').classList.toggle('oculto', !previo);
        $('aviso-dia').textContent = previo ? 'Ya guardó la asistencia de este día; puede corregirla y guardar de nuevo.' : '';

        $('alumnos').innerHTML = c.alumnos.length ? c.alumnos.map((a, i) => `
            <li class="space-y-2.5 px-4 py-3">
                <div class="flex items-start gap-2">
                    <span class="w-6 shrink-0 pt-0.5 text-xs text-slate-400">${i + 1}</span>
                    <div class="min-w-0">
                        <div class="text-sm leading-snug font-semibold text-slate-900">${escapar(a.nombre)}</div>
                        <div class="text-xs text-slate-500">C.I. ${escapar(a.cedula)}</div>
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-1.5">
                    ${ESTADOS.map(([v, t]) => `<button type="button" class="marca-asistencia h-11 w-full flex-col gap-0 leading-none" data-alumno="${a.id}" data-estado="${v}"><span class="text-sm">${v}</span><span class="mt-0.5 text-[10px] font-medium">${t}</span></button>`).join('')}
                </div>
            </li>`).join('') : '<li class="px-4 py-6 text-center text-sm text-slate-500">Esta cátedra no tiene alumnos.</li>';
        refrescarMarcas();
    }

    function refrescarMarcas() {
        document.querySelectorAll('[data-alumno]').forEach((b) => {
            ['P', 'A', 'R', 'J'].forEach((v) => b.classList.remove('marca-' + v));
            if (estados[b.dataset.alumno] === b.dataset.estado) b.classList.add('marca-' + b.dataset.estado);
        });
        const cuenta = { P: 0, A: 0, R: 0, J: 0 };
        Object.values(estados).forEach((v) => cuenta[v]++);
        document.querySelectorAll('[data-cuenta]').forEach((s) => (s.textContent = cuenta[s.dataset.cuenta]));
        $('total-presentes').textContent = cuenta.P + cuenta.R;
        $('total-ausentes').textContent = cuenta.A;
    }

    $('alumnos').addEventListener('click', (e) => {
        const b = e.target.closest('[data-alumno]');
        if (!b) return;
        estados[b.dataset.alumno] = b.dataset.estado;
        refrescarMarcas();
    });
    document.querySelectorAll('[data-todos]').forEach((b) => b.addEventListener('click', () => {
        Object.keys(estados).forEach((k) => (estados[k] = b.dataset.todos));
        refrescarMarcas();
    }));
    $('catedra').addEventListener('change', pintarLista);
    $('fecha').addEventListener('change', pintarLista);

    $('guardar').addEventListener('click', () => {
        const c = catedra($('catedra').value);
        const f = $('fecha').value;
        if (!f) return alert('Seleccione la fecha.');
        if (f > hoy()) return alert('No puede registrar asistencia en una fecha futura.');
        if (!c.alumnos.length) return alert('La cátedra no tiene alumnos.');

        const registro = { c: c.id, f, o: $('observacion').value.trim(), e: { ...estados } };
        pendientes = pendientes.filter((p) => !(p.c === c.id && p.f === f)).concat(registro)
            .sort((a, b) => (a.f + a.c).localeCompare(b.f + b.c));
        guardarTodo(pendientes);
        pintarPendientes();
        pintarLista();
        alert('Asistencia del ' + fechaBonita(f) + ' guardada en este dispositivo.' + (almacenamientoOk ? '' : '\n\nAtención: este navegador no permite guardar datos; genere el código antes de cerrar la página.'));
    });

    // ---------- Pendientes y código ----------
    function pintarPendientes() {
        $('cantidad-pendientes').textContent = pendientes.length;
        $('sin-pendientes').classList.toggle('oculto', pendientes.length > 0);
        $('pendientes').innerHTML = pendientes.map((p, i) => {
            const c = catedra(p.c);
            const v = Object.values(p.e);
            const presentes = v.filter((x) => x === 'P' || x === 'R').length;
            return `<li class="flex items-center gap-3 px-4 py-2.5 text-sm">
                <button type="button" class="min-w-0 flex-1 text-left" data-abrir="${i}">
                    <div class="font-semibold text-slate-900">${fechaBonita(p.f)}</div>
                    <div class="truncate text-xs text-slate-500">${escapar(c ? c.nombre : 'Cátedra ' + p.c)}</div>
                </button>
                <span class="text-xs font-semibold text-slate-700">${presentes}/${v.length}</span>
                <button type="button" class="px-2 text-slate-300 hover:text-rose-600" data-quitar="${i}" title="Quitar">✕</button>
            </li>`;
        }).join('');
    }

    $('pendientes').addEventListener('click', (e) => {
        const quitar = e.target.closest('[data-quitar]');
        const abrir = e.target.closest('[data-abrir]');
        if (quitar) {
            const p = pendientes[quitar.dataset.quitar];
            if (!confirm('¿Quitar la asistencia del ' + fechaBonita(p.f) + '?')) return;
            pendientes.splice(Number(quitar.dataset.quitar), 1);
            guardarTodo(pendientes);
            pintarPendientes();
            pintarLista();
        } else if (abrir) {
            const p = pendientes[abrir.dataset.abrir];
            $('catedra').value = p.c;
            $('fecha').value = p.f;
            pintarLista();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    let ultimoTxt = null;

    function descargarTxt() {
        const enlace = document.createElement('a');
        enlace.href = URL.createObjectURL(new Blob([ultimoTxt.contenido], { type: 'text/plain;charset=utf-8' }));
        enlace.download = ultimoTxt.nombre;
        document.body.appendChild(enlace);
        enlace.click();
        setTimeout(() => { URL.revokeObjectURL(enlace.href); enlace.remove(); }, 1000);
    }

    $('generar').addEventListener('click', () => {
        if (!pendientes.length) return alert('No hay asistencias guardadas para subir.');

        const carga = { v: 1, u: D.usuario.id, n: D.usuario.nombre, g: new Date().toISOString(), j: pendientes };
        const datos = base64url(JSON.stringify(carga));
        const codigo = PREFIJO + '-' + datos + '-' + verificacion(datos);

        const resumen = pendientes.map((p) => {
            const v = Object.values(p.e);
            const n = (x) => v.filter((y) => y === x).length;
            return `- ${fechaBonita(p.f)} · ${catedra(p.c)?.nombre ?? 'Cátedra ' + p.c}: ${n('P')} presentes, ${n('A')} ausentes, ${n('R')} retrasos, ${n('J')} justificadas`;
        }).join('\r\n');

        const contenido = [
            'SIGAC - Asistencias registradas sin conexión',
            'Profesor: ' + D.usuario.nombre,
            'Año escolar: ' + D.anio,
            'Generado: ' + new Date().toLocaleString('es-VE'),
            '',
            resumen,
            '',
            'Para subirlas: entre a SIGAC > Asistencia sin conexión y suba este archivo, o copie el código completo:',
            '',
            codigo,
            '',
        ].join('\r\n');

        ultimoTxt = { contenido, nombre: 'asistencias-sigac-' + hoy() + '.txt' };
        $('codigo').value = codigo;
        $('nombre-txt').textContent = ultimoTxt.nombre;
        $('seccion-codigo').classList.remove('oculto');
        descargarTxt();
        $('seccion-codigo').scrollIntoView({ behavior: 'smooth' });
    });

    $('descargar-de-nuevo').addEventListener('click', () => ultimoTxt && descargarTxt());

    $('copiar').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText($('codigo').value); alert('Código copiado.'); }
        catch (e) { $('codigo').select(); document.execCommand('copy'); alert('Código copiado.'); }
    });

    $('vaciar').addEventListener('click', () => {
        if (!pendientes.length) return;
        if (!confirm('¿Ya subió el código a SIGAC? Se borrarán las asistencias guardadas en este dispositivo.')) return;
        pendientes = [];
        guardarTodo(pendientes);
        pintarPendientes();
        pintarLista();
        $('seccion-codigo').classList.add('oculto');
    });

    // ---------- Estado de la conexión ----------
    function red() {
        $('estado-red').textContent = navigator.onLine ? 'Con conexión' : 'Sin conexión';
        $('estado-red').className = 'rounded-full px-2.5 py-1 text-[11px] font-semibold ' + (navigator.onLine ? 'bg-emerald-500/20 text-emerald-200' : 'bg-white/10');
    }
    window.addEventListener('online', red);
    window.addEventListener('offline', red);
    red();

    pintarLista();
    pintarPendientes();
})();
</script>
</body>
</html>
