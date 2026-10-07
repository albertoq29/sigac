@props(['title', 'horizontal' => false])
@php $ajustes = \App\Models\Ajuste::todos(); @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $horizontal ? 'letter landscape' : 'letter' }}; margin: 14mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Times New Roman', Times, serif; color: #000; margin: 0; background: #e2e8f0; line-height: 1.35; }
        .hoja { background: #fff; max-width: {{ $horizontal ? '1050px' : '816px' }}; margin: 24px auto; padding: 40px 48px; box-shadow: 0 10px 30px rgba(15, 23, 42, .15); }
        .barra { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; font-family: system-ui, sans-serif; }
        .barra button { border: 0; border-radius: 10px; padding: 8px 16px; font-weight: 600; cursor: pointer; }
        .barra .imprimir { background: #fff; color: #0f172a; }
        .barra .cerrar { background: transparent; color: #cbd5e1; }
        .banner { display: flex; justify-content: space-between; align-items: center; min-height: 70px; }
        .banner img { height: 70px; width: auto; max-width: 30%; object-fit: contain; }
        .institucion { text-align: center; font-size: 11px; font-weight: bold; margin: 6px 0 18px; text-transform: uppercase; }
        .titulo { text-align: center; font-weight: bold; font-size: 19px; margin: 18px 0; text-decoration: underline; text-transform: uppercase; }
        .texto { font-size: 14.5px; text-align: justify; margin-bottom: 16px; }
        table.datos { width: 100%; border-collapse: collapse; margin: 14px 0; }
        table.datos th, table.datos td { border: 1px solid #000; padding: 5px 6px; font-size: 12.5px; text-align: center; }
        table.datos th { background: #f1f5f9; }
        table.datos td.izq { text-align: left; }
        .firmas { margin-top: 56px; display: flex; gap: 32px; align-items: flex-end; font-size: 12px; }
        .firma { flex: 1; text-align: center; }
        .firma .linea { border-top: 1px solid #000; margin: 0 auto 4px; width: 85%; }
        .gaceta { font-size: 10px; font-style: italic; color: #333; }
        .pie { font-size: 10px; text-align: center; margin-top: 40px; border-top: 1px solid #000; padding-top: 8px; }
        .seccion-titulo { font-size: 13px; font-weight: bold; margin: 18px 0 4px; text-transform: uppercase; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .hoja { box-shadow: none; margin: 0; padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <button class="imprimir" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
        <button class="cerrar" onclick="window.close()">Cerrar</button>
    </div>
    <div class="hoja">
        <div class="banner">
            @foreach (['logo_izquierda', 'logo_centro', 'logo_derecha'] as $logo)
                @if ($url = \App\Models\Ajuste::urlLogo($logo))
                    <img src="{{ $url }}" alt="" onerror="this.style.visibility='hidden'">
                @else
                    <span></span>
                @endif
            @endforeach
        </div>
        <div class="institucion">
            {{ $ajustes['institucion_nombre'] }}<br>
            {{ $ajustes['institucion_ubicacion'] }}
        </div>

        {{ $slot }}

        <div class="pie">
            {{ $ajustes['institucion_direccion'] }}<br>
            @if ($ajustes['institucion_telefono']) Telf. {{ $ajustes['institucion_telefono'] }} @endif
            @if ($ajustes['institucion_correo']) | {{ $ajustes['institucion_correo'] }} @endif
        </div>
    </div>
</body>
</html>
