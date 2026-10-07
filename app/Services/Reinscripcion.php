<?php

namespace App\Services;

use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMatricula;
use App\Models\AnioEscolar;
use App\Models\Catedra;
use App\Models\Inscripcion;
use App\Models\Matricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reasignación de materias para el siguiente año escolar.
 *
 * Solo se sugieren las materias de los AÑOS de estudio (Preparatorio, 1er Año...):
 *  - Si aprobó TODAS las materias de su año, pasa al año siguiente: se sugieren
 *    esas mismas asignaturas en el año siguiente (1er Año → 2do Año).
 *  - Si reprobó alguna, no es promovido: se sugiere repetir solo las que no aprobó.
 *  - Nunca se sugiere una materia que ya aprobó (tampoco se permite inscribirla).
 * Las cátedras por nivel (instrumentos, Nivel I...) se asignan manualmente.
 * Si el año anterior no está cerrado, las materias "cursando" se cuentan como aprobadas.
 * Los años sin registro de notas no se toman en cuenta: no hay sugerencias y se asigna manualmente.
 */
class Reinscripcion
{
    /**
     * Matrículas del año de origen cuyos estudiantes aún no tienen matrícula en el destino.
     */
    public function pendientes(AnioEscolar $origen, AnioEscolar $destino): Builder
    {
        // Columnas calificadas: la consulta puede unirse con "estudiantes", que también tiene "estado".
        return Matricula::query()
            ->where('matriculas.anio_escolar_id', $origen->id)
            ->whereIn('matriculas.estado', [EstadoMatricula::Finalizado->value, EstadoMatricula::Inscrito->value])
            ->whereDoesntHave('estudiante.matriculas', fn ($q) => $q->where('matriculas.anio_escolar_id', $destino->id));
    }

    /**
     * @param  Collection<int, Catedra>|null  $catedrasDestino  cátedras del año destino (para no repetir consultas)
     * @return Collection<int, Catedra>
     */
    public function sugerencias(Matricula $anterior, AnioEscolar $destino, ?Collection $catedrasDestino = null): Collection
    {
        $anterior->loadMissing(['anioEscolar', 'inscripciones.catedra.nivel']);

        if (! $anterior->anioEscolar->tieneRegistroNotas()) {
            return collect();
        }

        $catedrasDestino ??= $destino->catedras()->with(['asignatura', 'nivel', 'profesor'])->get();

        $aprobadas = GestionInscripciones::materiasAprobadas($anterior->estudiante_id);
        $sugeridas = collect();

        $porAnio = $anterior->inscripciones
            ->filter(fn (Inscripcion $i) => $i->catedra->nivel->esAnio())
            ->groupBy(fn (Inscripcion $i) => $i->catedra->nivel_id);

        foreach ($porAnio as $inscripciones) {
            $nivel = $inscripciones->first()->catedra->nivel;
            $promovido = $inscripciones->every(fn (Inscripcion $i) => $this->cuentaComoAprobada($i));

            foreach ($inscripciones as $inscripcion) {
                if ($promovido) {
                    $nivelDestinoId = $nivel->siguiente_nivel_id; // null: último año aprobado
                } elseif (! $this->cuentaComoAprobada($inscripcion)) {
                    $nivelDestinoId = $nivel->id; // repite solo la que no aprobó
                } else {
                    continue;
                }

                $catedra = $nivelDestinoId
                    ? $this->buscar($catedrasDestino, $inscripcion->catedra, $nivelDestinoId)
                    : null;

                if ($catedra
                    && ! $aprobadas->has(GestionInscripciones::claveMateria($catedra))
                    && ! $sugeridas->contains('id', $catedra->id)) {
                    $sugeridas->push($catedra);
                }
            }
        }

        return $sugeridas;
    }

    /** ¿El estudiante aprobó (o aprueba todas las de) su año anterior? */
    public function promovido(Matricula $anterior): ?bool
    {
        $anterior->loadMissing(['anioEscolar', 'inscripciones.catedra.nivel']);

        if (! $anterior->anioEscolar->tieneRegistroNotas()) {
            return null;
        }

        $anios = $anterior->inscripciones->filter(fn (Inscripcion $i) => $i->catedra->nivel->esAnio());

        return $anios->isEmpty() ? null : $anios->every(fn (Inscripcion $i) => $this->cuentaComoAprobada($i));
    }

    private function cuentaComoAprobada(Inscripcion $inscripcion): bool
    {
        return in_array($inscripcion->estado, [EstadoInscripcion::Aprobada, EstadoInscripcion::Cursando], true);
    }

    /** Misma asignatura en el nivel destino; se prefiere la misma sección. */
    private function buscar(Collection $catedrasDestino, Catedra $origen, int $nivelId): ?Catedra
    {
        $candidatas = $catedrasDestino
            ->where('asignatura_id', $origen->asignatura_id)
            ->where('nivel_id', $nivelId);

        return $candidatas->firstWhere('seccion', $origen->seccion)
            ?? $candidatas->sortBy('seccion')->first();
    }
}
