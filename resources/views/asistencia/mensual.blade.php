<x-layouts.app :title="'Reporte mensual · '.$catedra->nombre">
    @push('head')
        <style>
            @media print {
                @page { size: landscape; margin: 10mm; }
                aside, header, nav { display: none !important; }
                .lg\:pl-64 { padding-left: 0 !important; }
                main { max-width: none !important; padding: 0 !important; }
                .card { box-shadow: none !important; border: none !important; }
            }
        </style>
    @endpush

    <x-catedra-encabezado :catedra="$catedra">
        <x-slot:acciones>
            <button type="button" onclick="window.print()" class="btn btn-warning"><x-icon name="printer" class="size-4" /> Imprimir</button>
        </x-slot:acciones>
    </x-catedra-encabezado>

    <form method="GET" class="card flex flex-wrap items-end gap-3 p-4 no-print">
        <x-field label="Mes">
            <input type="month" name="mes" class="input" value="{{ $mes }}" onchange="this.form.submit()">
        </x-field>
        @if ($meses->isNotEmpty())
            <div class="flex flex-wrap gap-1.5 pb-1">
                @foreach ($meses as $m)
                    <a href="{{ route('asistencia.mensual', [$catedra, 'mes' => $m]) }}"
                       @class(['btn btn-sm', 'btn-primary' => $m === $mes, 'btn-secondary' => $m !== $mes])>
                        {{ ucfirst(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $m.'-01')->translatedFormat('M Y')) }}
                    </a>
                @endforeach
            </div>
        @endif
    </form>

    @php $ajustes = \App\Models\Ajuste::todos(); @endphp
    <div class="hidden text-center print:block">
        <div class="text-sm font-bold uppercase">{{ $ajustes['institucion_nombre'] }}</div>
        <div class="text-xs font-semibold tracking-wide uppercase">Reporte mensual de asistencias</div>
        <div class="mt-1 text-xs">
            <b>DOCENTE:</b> {{ $catedra->profesor?->name ?? '—' }} &nbsp;
            <b>CÁTEDRA:</b> {{ mb_strtoupper($catedra->nombre) }} &nbsp;
            <b>MES:</b> {{ mb_strtoupper($inicioMes->translatedFormat('F Y')) }} &nbsp;
            <b>AÑO ESCOLAR:</b> {{ $catedra->anioEscolar->nombre }}
        </div>
    </div>

    <div class="card overflow-hidden">
        @if ($matriz['filas']->isEmpty())
            <x-empty icon="table" title="Sin datos para {{ $inicioMes->translatedFormat('F Y') }}" />
        @else
            @php $diasConClase = $matriz['jornadas']->map(fn ($j) => $j->fecha->day)->all(); @endphp
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-center text-xs">
                    <thead>
                        <tr class="bg-slate-900 text-white">
                            <th rowspan="2" class="sticky left-0 z-[1] border border-slate-700 bg-slate-900 px-3 py-2 text-left">Estudiante</th>
                            <th colspan="{{ $matriz['dias'] }}" class="border border-slate-700 px-2 py-1.5">{{ mb_strtoupper($inicioMes->translatedFormat('F Y')) }}</th>
                            <th rowspan="2" class="border border-slate-700 px-2 py-2">Inasist.</th>
                            <th rowspan="2" class="border border-slate-700 px-2 py-2">% Asist.</th>
                        </tr>
                        <tr class="bg-slate-800 text-white">
                            @for ($d = 1; $d <= $matriz['dias']; $d++)
                                <th @class(['min-w-6 border border-slate-700 px-0.5 py-1 font-medium', 'bg-slate-600' => in_array($d, $diasConClase)])>{{ $d }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matriz['filas'] as $fila)
                            <tr class="hover:bg-slate-50">
                                <td class="sticky left-0 z-[1] max-w-44 truncate border border-slate-200 bg-white px-3 py-1.5 text-left font-semibold text-slate-900 sm:max-w-none sm:whitespace-nowrap print:static">
                                    {{ $fila['inscripcion']->estudiante->apellidos_nombres }}
                                    @if ($fila['inscripcion']->estaRetirada()) <span class="font-normal text-amber-600">(ret.)</span> @endif
                                </td>
                                @for ($d = 1; $d <= $matriz['dias']; $d++)
                                    @php $estado = $fila['dias'][$d] ?? null; @endphp
                                    <td @class(['border border-slate-200 p-0.5 font-bold', $estado ? 'celda-'.$estado->value : (in_array($d, $diasConClase) ? 'bg-slate-50' : '')])>{{ $estado?->value }}</td>
                                @endfor
                                <td class="border border-slate-200 px-2 font-bold text-slate-900">{{ $fila['inasistencias'] }}</td>
                                <td class="border border-slate-200 px-2 font-bold text-slate-900">{{ $fila['porcentaje'] !== null ? $fila['porcentaje'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap gap-3 border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                <span><span class="celda-P rounded px-1.5 font-bold">P</span> Presente</span>
                <span><span class="celda-A rounded px-1.5 font-bold">A</span> Ausente</span>
                <span><span class="celda-R rounded px-1.5 font-bold">R</span> Retraso</span>
                <span><span class="celda-J rounded px-1.5 font-bold">J</span> Justificada</span>
                <span>· {{ count($diasConClase) }} jornada(s) en el mes</span>
            </div>
        @endif
    </div>

    <div class="mt-16 hidden grid-cols-3 gap-8 text-center text-xs print:grid">
        <div class="border-t border-slate-900 pt-2 font-bold">{{ $catedra->profesor?->name ?? '' }}<br><span class="font-normal">Docente</span></div>
        <div class="border-t border-slate-900 pt-2 font-bold">{{ $ajustes['control_estudios_nombre'] }}<br><span class="font-normal">Control de Estudios</span></div>
        <div class="border-t border-slate-900 pt-2 font-bold">{{ $ajustes['director_nombre'] }}<br><span class="font-normal">Director</span></div>
    </div>
</x-layouts.app>
