<?php

namespace App\Services;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMatricula;
use App\Exceptions\ReglaNegocioException;
use App\Models\AnioEscolar;
use App\Models\Catedra;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Matricula;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inscripción de estudiantes en un año escolar y en sus cátedras, y retiros.
 */
class GestionInscripciones
{
    /**
     * Inscribe (o reincorpora) al estudiante en el año escolar y le asigna cátedras.
     *
     * @param  array<int>  $catedraIds
     * @param  array<int, string|null>  $horarios  horario propio por catedra_id (opcional)
     */
    public function inscribir(
        Estudiante $estudiante,
        AnioEscolar $anio,
        array $catedraIds = [],
        ?string $seccion = null,
        ?CarbonInterface $fecha = null,
        array $horarios = [],
    ): Matricula {
        if (! $anio->permiteInscripciones()) {
            throw new ReglaNegocioException("El año escolar {$anio->nombre} está cerrado; no admite inscripciones.");
        }

        return DB::transaction(function () use ($estudiante, $anio, $catedraIds, $seccion, $fecha, $horarios) {
            $matricula = Matricula::query()->firstOrNew([
                'estudiante_id' => $estudiante->id,
                'anio_escolar_id' => $anio->id,
            ]);

            $matricula->fill([
                'estado' => EstadoMatricula::Inscrito,
                'seccion' => $seccion !== null && $seccion !== '' ? $seccion : $matricula->seccion,
                'fecha_inscripcion' => $matricula->fecha_inscripcion ?? ($fecha ?? now())->toDateString(),
                'fecha_retiro' => null,
                'motivo_retiro' => null,
            ])->save();

            $this->asignarCatedras($matricula, $catedraIds, $horarios);

            $estudiante->sincronizarEstado();

            return $matricula;
        });
    }

    /**
     * @param  array<int>  $catedraIds
     * @param  array<int, string|null>  $horarios
     */
    public function asignarCatedras(Matricula $matricula, array $catedraIds, array $horarios = []): void
    {
        if (! $matricula->estaInscrito()) {
            throw new ReglaNegocioException('El estudiante no está inscrito en este año escolar.');
        }

        $catedras = Catedra::query()->with(['asignatura', 'nivel'])->whereIn('id', array_unique($catedraIds))->get();
        $aprobadas = self::materiasAprobadas($matricula->estudiante_id);

        foreach ($catedras as $catedra) {
            if ($catedra->anio_escolar_id !== $matricula->anio_escolar_id) {
                throw new ReglaNegocioException('Solo se pueden asignar cátedras del mismo año escolar de la inscripción.');
            }

            if ($catedra->notasCerradas()) {
                throw new ReglaNegocioException("La cátedra {$catedra->nombre} ya tiene las notas cerradas.");
            }

            // Una materia aprobada no se vuelve a cursar.
            if ($previa = $aprobadas->get(self::claveMateria($catedra))) {
                throw new ReglaNegocioException(sprintf(
                    'El estudiante ya aprobó %s (%s) en %s; no puede volver a cursarla.',
                    $catedra->asignatura->nombre,
                    $catedra->nivel->nombre,
                    $previa->catedra->anioEscolar->nombre,
                ));
            }

            $inscripcion = Inscripcion::query()->firstOrNew([
                'catedra_id' => $catedra->id,
                'estudiante_id' => $matricula->estudiante_id,
            ]);

            $horario = $horarios[$catedra->id] ?? null;

            $inscripcion->fill([
                'matricula_id' => $matricula->id,
                'estado' => EstadoInscripcion::Cursando,
                'fecha_retiro' => null,
                'motivo_retiro' => null,
                'horario' => $horario !== null && $horario !== '' && $horario !== $catedra->horario
                    ? $horario
                    : $inscripcion->horario,
            ])->save();
        }
    }

    /**
     * Materias (asignatura + nivel) que el estudiante ya aprobó, indexadas por claveMateria().
     *
     * @return Collection<string, Inscripcion>
     */
    public static function materiasAprobadas(int $estudianteId): Collection
    {
        return Inscripcion::query()
            ->where('estudiante_id', $estudianteId)
            ->where('estado', EstadoInscripcion::Aprobada->value)
            ->with('catedra.anioEscolar')
            ->get()
            ->keyBy(fn (Inscripcion $i) => self::claveMateria($i->catedra));
    }

    public static function claveMateria(Catedra $catedra): string
    {
        return $catedra->asignatura_id.'|'.$catedra->nivel_id;
    }

    /**
     * Retiro del estudiante: la matrícula queda "retirado", todas las materias
     * que cursa quedan registradas como "retirada" y el estudiante pasa a inactivo.
     */
    public function retirarEstudiante(Matricula $matricula, CarbonInterface $fecha, ?string $motivo = null): void
    {
        if ($matricula->anioEscolar->estaCerrado()) {
            throw new ReglaNegocioException('El año escolar está cerrado.');
        }

        if (! $matricula->estaInscrito()) {
            throw new ReglaNegocioException('El estudiante no está inscrito en este año escolar.');
        }

        DB::transaction(function () use ($matricula, $fecha, $motivo) {
            $matricula->update([
                'estado' => EstadoMatricula::Retirado,
                'fecha_retiro' => $fecha->toDateString(),
                'motivo_retiro' => $motivo,
            ]);

            $matricula->inscripciones()
                ->where('estado', EstadoInscripcion::Cursando->value)
                ->update([
                    'estado' => EstadoInscripcion::Retirada->value,
                    'fecha_retiro' => $fecha->toDateString(),
                    'motivo_retiro' => $motivo ?: 'Retiro del estudiante',
                ]);

            $matricula->estudiante->sincronizarEstado();
        });
    }

    /** Deshace un retiro del estudiante dentro del mismo año escolar. */
    public function reincorporarEstudiante(Matricula $matricula): void
    {
        if ($matricula->anioEscolar->estaCerrado()) {
            throw new ReglaNegocioException('El año escolar está cerrado.');
        }

        if ($matricula->estado !== EstadoMatricula::Retirado) {
            throw new ReglaNegocioException('El estudiante no está retirado.');
        }

        DB::transaction(function () use ($matricula) {
            $fechaRetiro = $matricula->fecha_retiro;

            $matricula->update([
                'estado' => EstadoMatricula::Inscrito,
                'fecha_retiro' => null,
                'motivo_retiro' => null,
            ]);

            // Se reactivan las materias retiradas junto con el estudiante (misma fecha)
            // cuyas cátedras no tengan aún las notas cerradas.
            $matricula->inscripciones()
                ->where('estado', EstadoInscripcion::Retirada->value)
                ->when($fechaRetiro, fn ($q) => $q->whereDate('fecha_retiro', $fechaRetiro))
                ->whereHas('catedra', fn ($q) => $q->whereNull('notas_cerradas_at'))
                ->update([
                    'estado' => EstadoInscripcion::Cursando->value,
                    'fecha_retiro' => null,
                    'motivo_retiro' => null,
                ]);

            $matricula->estudiante->sincronizarEstado();
        });
    }

    /** Retiro de una sola materia (cátedra). */
    public function retirarInscripcion(Inscripcion $inscripcion, CarbonInterface $fecha, ?string $motivo = null): void
    {
        $this->asegurarEditable($inscripcion);

        if (! $inscripcion->estaCursando()) {
            throw new ReglaNegocioException('Solo se pueden retirar materias que se estén cursando.');
        }

        $inscripcion->update([
            'estado' => EstadoInscripcion::Retirada,
            'fecha_retiro' => $fecha->toDateString(),
            'motivo_retiro' => $motivo ?: 'Retiro de la materia',
        ]);
    }

    public function reincorporarInscripcion(Inscripcion $inscripcion): void
    {
        $this->asegurarEditable($inscripcion);

        if (! $inscripcion->estaRetirada()) {
            throw new ReglaNegocioException('La materia no está retirada.');
        }

        if (! $inscripcion->matricula->estaInscrito()) {
            throw new ReglaNegocioException('Primero debe reincorporar al estudiante en el año escolar.');
        }

        $inscripcion->update([
            'estado' => EstadoInscripcion::Cursando,
            'fecha_retiro' => null,
            'motivo_retiro' => null,
        ]);
    }

    /** Elimina una asignación hecha por error (sin asistencias ni notas). */
    public function eliminarInscripcion(Inscripcion $inscripcion): void
    {
        $this->asegurarEditable($inscripcion);

        if ($inscripcion->asistencias()->exists() || $inscripcion->notas()->whereNotNull('valor')->exists()) {
            throw new ReglaNegocioException('La materia ya tiene asistencias o notas registradas; use "Retirar" en su lugar.');
        }

        $inscripcion->delete();
    }

    private function asegurarEditable(Inscripcion $inscripcion): void
    {
        $catedra = $inscripcion->catedra;

        if ($catedra->anioEscolar->estaCerrado()) {
            throw new ReglaNegocioException('El año escolar está cerrado.');
        }

        if ($catedra->notasCerradas()) {
            throw new ReglaNegocioException('La cátedra ya tiene las notas cerradas.');
        }
    }
}
