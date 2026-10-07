<?php

namespace App\Services;

use App\Enums\EstadoAsistencia;
use App\Enums\EstadoInscripcion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia;
use App\Models\Catedra;
use App\Models\Inscripcion;
use App\Models\Jornada;
use App\Models\ReaperturaJornada;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegistroAsistencia
{
    /**
     * Guarda (o corrige) el pase de lista de una cátedra en una fecha.
     *
     * @param  array<int, string>  $estados  [inscripcion_id => 'P'|'A'|'R'|'J']
     */
    public function guardar(Catedra $catedra, CarbonInterface $fecha, array $estados, User $usuario, ?string $observacion = null): Jornada
    {
        $anio = $catedra->anioEscolar;

        if (! $anio->permiteRegistros()) {
            throw new ReglaNegocioException("El año escolar {$anio->nombre} no está en curso; no se pueden registrar asistencias.");
        }

        if ($fecha->isAfter(today())) {
            throw new ReglaNegocioException('No se puede registrar asistencia en una fecha futura.');
        }

        if (($anio->fecha_inicio && $fecha->lt($anio->fecha_inicio)) || ($anio->fecha_fin && $fecha->gt($anio->fecha_fin))) {
            throw new ReglaNegocioException("La fecha está fuera del año escolar {$anio->nombre}.");
        }

        $existente = $catedra->jornadas()->whereDate('fecha', $fecha->toDateString())->first();
        if ($existente?->estaCerrada()) {
            throw new ReglaNegocioException('La asistencia de este día está cerrada. Para modificarla debe reabrirla indicando el motivo.');
        }

        $validas = $catedra->inscripciones()
            ->whereIn('id', array_keys($estados))
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($catedra, $fecha, $estados, $usuario, $observacion, $validas, $existente) {
            $jornada = $existente ?? new Jornada([
                'catedra_id' => $catedra->id,
                'fecha' => $fecha->toDateString(),
            ]);

            $jornada->fill([
                'registrado_por' => $usuario->id,
                'observacion' => $observacion,
            ])->save();

            foreach ($estados as $inscripcionId => $codigo) {
                $inscripcion = $validas->get($inscripcionId);
                $estado = EstadoAsistencia::tryFrom((string) $codigo);

                if (! $inscripcion || ! $estado) {
                    continue;
                }

                // Los retirados no reciben nuevas asistencias, pero se conservan las anteriores.
                $existente = $jornada->asistencias()->where('inscripcion_id', $inscripcion->id)->first();
                if ($inscripcion->estado === EstadoInscripcion::Retirada && ! $existente) {
                    continue;
                }

                Asistencia::query()->updateOrCreate(
                    ['jornada_id' => $jornada->id, 'inscripcion_id' => $inscripcion->id],
                    ['estado' => $estado]
                );
            }

            return $jornada;
        });
    }

    public function eliminarJornada(Jornada $jornada): void
    {
        if (! $jornada->catedra->anioEscolar->permiteRegistros()) {
            throw new ReglaNegocioException('El año escolar está cerrado; las asistencias son de solo lectura.');
        }

        $jornada->delete();
    }

    /**
     * Cierra la asistencia del día. Si venía de una reapertura, deja registrados
     * los cambios hechos mientras estuvo abierta.
     */
    public function cerrarJornada(Jornada $jornada, User $usuario): void
    {
        if (! $jornada->catedra->anioEscolar->permiteRegistros()) {
            throw new ReglaNegocioException('El año escolar no está en curso.');
        }

        if ($jornada->estaCerrada()) {
            throw new ReglaNegocioException('La asistencia de este día ya está cerrada.');
        }

        DB::transaction(function () use ($jornada, $usuario) {
            if ($reapertura = $jornada->reaperturaAbierta()) {
                $reapertura->update([
                    'cambios' => $this->cambios($reapertura->estados_antes, $jornada->estadosActuales()),
                    'recerrada_at' => now(),
                ]);
            }

            $jornada->update(['cerrada_at' => now(), 'cerrada_por' => $usuario->id]);
        });
    }

    /** Reabre la asistencia de un día cerrado; el motivo queda a la vista de Control de Estudios. */
    public function reabrirJornada(Jornada $jornada, User $usuario, string $motivo): ReaperturaJornada
    {
        if (! $jornada->catedra->anioEscolar->permiteRegistros()) {
            throw new ReglaNegocioException('El año escolar no está en curso; la asistencia no se puede reabrir.');
        }

        if (! $jornada->estaCerrada()) {
            throw new ReglaNegocioException('La asistencia de este día no está cerrada.');
        }

        return DB::transaction(function () use ($jornada, $usuario, $motivo) {
            $jornada->update(['cerrada_at' => null, 'cerrada_por' => null]);

            return $jornada->reaperturas()->create([
                'reabierta_por' => $usuario->id,
                'motivo' => trim($motivo),
                'estados_antes' => $jornada->estadosActuales(),
            ]);
        });
    }

    /**
     * Diferencias entre dos pases de lista.
     *
     * @param  array<int|string, string>  $antes
     * @param  array<int|string, string>  $despues
     * @return list<array{inscripcion_id: int, estudiante: string, antes: ?string, despues: ?string}>
     */
    public function cambios(array $antes, array $despues): array
    {
        $ids = collect(array_keys($antes))->merge(array_keys($despues))->map(fn ($id) => (int) $id)->unique();
        $nombres = Inscripcion::query()->with('estudiante')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (Inscripcion $i) => [$i->id => $i->estudiante->apellidos_nombres]);

        $cambios = [];
        foreach ($ids as $id) {
            $a = $antes[$id] ?? $antes[(string) $id] ?? null;
            $d = $despues[$id] ?? $despues[(string) $id] ?? null;

            if ($a !== $d) {
                $cambios[] = [
                    'inscripcion_id' => $id,
                    'estudiante' => $nombres[$id] ?? "Inscripción {$id}",
                    'antes' => $a,
                    'despues' => $d,
                ];
            }
        }

        return collect($cambios)->sortBy('estudiante')->values()->all();
    }

    /** Cambios de una reapertura: los registrados al cerrar o, si sigue abierta, los actuales. */
    public function cambiosDe(ReaperturaJornada $reapertura): array
    {
        return $reapertura->cambios
            ?? $this->cambios($reapertura->estados_antes ?? [], $reapertura->jornada->estadosActuales());
    }

    /**
     * Totales de asistencia por inscripción.
     *
     * @param  Collection<int, int>|array<int>  $inscripcionIds
     * @return array<int, array{P: int, A: int, R: int, J: int, total: int, porcentaje: float|null}>
     */
    public function resumen(Collection|array $inscripcionIds): array
    {
        $filas = Asistencia::query()
            ->whereIn('inscripcion_id', collect($inscripcionIds)->all())
            ->selectRaw('inscripcion_id, estado, COUNT(*) as total')
            ->groupBy('inscripcion_id', 'estado')
            ->get();

        $resumen = [];

        foreach ($filas as $fila) {
            $id = $fila->inscripcion_id;
            $resumen[$id] ??= ['P' => 0, 'A' => 0, 'R' => 0, 'J' => 0, 'total' => 0, 'porcentaje' => null];
            $codigo = $fila->estado instanceof EstadoAsistencia ? $fila->estado->value : $fila->estado;
            $resumen[$id][$codigo] += (int) $fila->total;
            $resumen[$id]['total'] += (int) $fila->total;
        }

        foreach ($resumen as $id => $datos) {
            $resumen[$id]['porcentaje'] = self::porcentaje($datos['total'], $datos['A']);
        }

        return $resumen;
    }

    /** % de asistencia = (registros − ausencias) / registros. Retrasos y justificadas no restan. */
    public static function porcentaje(int $total, int $ausencias): ?float
    {
        return $total > 0 ? round(($total - $ausencias) * 100 / $total, 1) : null;
    }

    /**
     * Matriz del reporte mensual: estudiantes × días del mes.
     *
     * @return array{dias: int, filas: Collection}
     */
    public function matrizMensual(Catedra $catedra, int $anio, int $mes): array
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        $jornadas = $catedra->jornadas()
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->with('asistencias')
            ->get();

        $inscripcionIds = $jornadas->flatMap->asistencias->pluck('inscripcion_id')->unique();

        $inscripciones = Inscripcion::query()
            ->where('catedra_id', $catedra->id)
            ->where(fn ($q) => $q->whereIn('id', $inscripcionIds)
                ->orWhere('estado', '!=', EstadoInscripcion::Retirada->value))
            ->with('estudiante')
            ->get()
            ->sortBy(fn (Inscripcion $i) => $i->estudiante->apellidos_nombres);

        $filas = $inscripciones->map(function (Inscripcion $inscripcion) use ($jornadas) {
            $dias = [];
            $ausencias = 0;
            $total = 0;

            foreach ($jornadas as $jornada) {
                $asistencia = $jornada->asistencias->firstWhere('inscripcion_id', $inscripcion->id);
                if (! $asistencia) {
                    continue;
                }
                $dias[$jornada->fecha->day] = $asistencia->estado;
                $total++;
                if ($asistencia->estado->esInasistencia()) {
                    $ausencias++;
                }
            }

            return [
                'inscripcion' => $inscripcion,
                'dias' => $dias,
                'inasistencias' => $ausencias,
                'total' => $total,
                'porcentaje' => self::porcentaje($total, $ausencias),
            ];
        })->values();

        return [
            'dias' => $fin->day,
            'filas' => $filas,
            'jornadas' => $jornadas,
        ];
    }
}
