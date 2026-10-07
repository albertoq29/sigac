<?php

namespace App\Services;

use App\Enums\EstadoAnio;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMatricula;
use App\Exceptions\ReglaNegocioException;
use App\Models\AnioEscolar;
use App\Models\Catedra;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida del año escolar: planificación → en curso → cerrado.
 */
class GestionAnioEscolar
{
    public function __construct(private CierreNotas $cierreNotas)
    {
    }

    public function iniciar(AnioEscolar $anio): void
    {
        if (! $anio->enPlanificacion()) {
            throw new ReglaNegocioException('Solo se puede iniciar un año escolar que esté en planificación.');
        }

        $enCurso = AnioEscolar::actual();

        if ($enCurso) {
            throw new ReglaNegocioException(
                "El año escolar {$enCurso->nombre} sigue en curso. Debe cerrarlo antes de iniciar {$anio->nombre}."
            );
        }

        $anio->update(['estado' => EstadoAnio::EnCurso, 'iniciado_at' => now()]);
    }

    /**
     * Resumen de lo que queda pendiente antes de cerrar el año.
     *
     * @return array<string, int>
     */
    public function pendientes(AnioEscolar $anio): array
    {
        $catedras = $anio->catedras();

        return [
            'catedras' => (clone $catedras)->count(),
            'catedras_sin_cierre' => (clone $catedras)->whereNull('notas_cerradas_at')->count(),
            'catedras_sin_profesor' => (clone $catedras)->whereNull('profesor_id')->count(),
            'lapsos_abiertos' => DB::table('lapsos')
                ->join('catedras', 'catedras.id', '=', 'lapsos.catedra_id')
                ->where('catedras.anio_escolar_id', $anio->id)
                ->whereNull('lapsos.cerrado_at')
                ->count(),
            'estudiantes_inscritos' => $anio->matriculas()->where('estado', EstadoMatricula::Inscrito->value)->count(),
            'materias_cursando' => DB::table('inscripciones')
                ->join('catedras', 'catedras.id', '=', 'inscripciones.catedra_id')
                ->where('catedras.anio_escolar_id', $anio->id)
                ->where('inscripciones.estado', EstadoInscripcion::Cursando->value)
                ->count(),
        ];
    }

    /**
     * Cierre del año escolar (Control de Estudios):
     *  1. Cierra las notas de todas las cátedras pendientes (las materias sin nota
     *     calculable quedan "sin calificar").
     *  2. Las matrículas "inscrito" pasan a "año finalizado".
     *  3. El año queda cerrado: asistencias y notas pasan a ser históricas y de solo lectura.
     *  4. Los estudiantes pasan a inactivo hasta que se reinscriban en otro año.
     */
    public function cerrar(AnioEscolar $anio, User $usuario): void
    {
        if (! $anio->estaEnCurso()) {
            throw new ReglaNegocioException('Solo se puede cerrar el año escolar que está en curso.');
        }

        DB::transaction(function () use ($anio, $usuario) {
            $anio->catedras()
                ->whereNull('notas_cerradas_at')
                ->get()
                ->each(fn (Catedra $catedra) => $this->cierreNotas->cerrarNotasCatedra($catedra, $usuario, forzar: true));

            $estudianteIds = $anio->matriculas()->pluck('estudiante_id');

            $anio->matriculas()
                ->where('estado', EstadoMatricula::Inscrito->value)
                ->update(['estado' => EstadoMatricula::Finalizado->value]);

            $anio->update([
                'estado' => EstadoAnio::Cerrado,
                'cerrado_at' => now(),
                'cerrado_por' => $usuario->id,
            ]);

            Estudiante::query()
                ->whereIn('id', $estudianteIds)
                ->get()
                ->each(fn (Estudiante $e) => $e->sincronizarEstado());
        });
    }

    /**
     * Copia la estructura de cátedras (asignatura, nivel, sección, profesor y
     * horario) de un año a otro. El régimen y la cantidad de lapsos son los
     * predeterminados del año de destino. No copia estudiantes ni notas.
     */
    public function copiarCatedras(AnioEscolar $origen, AnioEscolar $destino): int
    {
        if ($destino->estaCerrado()) {
            throw new ReglaNegocioException('El año de destino está cerrado.');
        }

        $creadas = 0;

        DB::transaction(function () use ($origen, $destino, &$creadas) {
            foreach ($origen->catedras()->get() as $catedra) {
                $existe = $destino->catedras()
                    ->where('asignatura_id', $catedra->asignatura_id)
                    ->where('nivel_id', $catedra->nivel_id)
                    ->where('seccion', $catedra->seccion)
                    ->where(fn ($q) => $catedra->profesor_id
                        ? $q->where('profesor_id', $catedra->profesor_id)
                        : $q->whereNull('profesor_id'))
                    ->exists();

                if ($existe) {
                    continue;
                }

                $destino->catedras()->create([
                    'asignatura_id' => $catedra->asignatura_id,
                    'nivel_id' => $catedra->nivel_id,
                    'seccion' => $catedra->seccion,
                    'profesor_id' => $catedra->profesor?->activo ? $catedra->profesor_id : null,
                    'horario' => $catedra->horario,
                    'regimen' => $destino->regimen_predeterminado,
                    'cantidad_lapsos' => $destino->lapsos_predeterminados,
                ]);

                $creadas++;
            }
        });

        return $creadas;
    }

    /**
     * Aplica el régimen y la cantidad de lapsos predeterminados del año a sus
     * cátedras ya creadas. Se omiten las que tienen el cierre de notas hecho o
     * a las que habría que quitarles un lapso con evaluaciones.
     *
     * @return array{actualizadas: int, omitidas: list<string>}
     */
    public function aplicarLapsosPredeterminados(AnioEscolar $anio): array
    {
        if ($anio->estaCerrado()) {
            throw new ReglaNegocioException('El año escolar está cerrado.');
        }

        $actualizadas = 0;
        $omitidas = [];

        foreach ($anio->catedras()->with(['asignatura', 'nivel'])->get() as $catedra) {
            if ($catedra->cantidad_lapsos === $anio->lapsos_predeterminados
                && $catedra->regimen === $anio->regimen_predeterminado) {
                continue;
            }

            if ($catedra->notasCerradas()) {
                $omitidas[] = $catedra->nombre.' (notas cerradas)';

                continue;
            }

            try {
                DB::transaction(function () use ($catedra, $anio) {
                    $catedra->update([
                        'cantidad_lapsos' => $anio->lapsos_predeterminados,
                        'regimen' => $anio->regimen_predeterminado,
                    ]);
                    $catedra->sincronizarLapsos();
                });
                $actualizadas++;
            } catch (ReglaNegocioException) {
                $catedra->refresh();
                $omitidas[] = $catedra->nombre.' (lapsos con evaluaciones)';
            }
        }

        return ['actualizadas' => $actualizadas, 'omitidas' => $omitidas];
    }
}
