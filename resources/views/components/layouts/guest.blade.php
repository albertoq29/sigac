@props(['title' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ? $title.' · ' : '' }}SIGAC</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>♪</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4 font-sans text-slate-900 antialiased">
    <div class="w-full max-w-md space-y-6">
        <div class="space-y-2 text-center">
            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-900 text-3xl text-white shadow-lg">♪</div>
            <div class="text-xs font-semibold tracking-widest text-slate-500 uppercase">SIGAC</div>
            <h1 class="text-lg font-bold tracking-tight text-slate-900">Conservatorio de Música<br>"Carlos Afanador Real"</h1>
            <p class="text-sm text-slate-500">Sistema Integral de Gestión Académica y Control de Asistencia</p>
        </div>
        <div class="card p-6 sm:p-8">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
