<?php

namespace App\Services;

use App\Enums\EstadoInscripcion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Catedra;
use App\Models\Lapso;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cierre de lapsos (trimestres/semestres) y cierre de notas de la cátedra.
 */
class CierreNotas
{
    public function __construct(private CalculadoraNotas $calculadora)
    {
    }

    public function cerrarLapso(Lapso $lapso, User $usuario): void
    {
        $catedra = $lapso->catedra;
        $this->asegurarAnioEnCurso($catedra);

        if ($catedra->notasCerradas()) {
            throw new ReglaNegocioException('Las notas de la cátedra ya están cerradas.');
        }

        if ($lapso->estaCerrado()) {
            throw new ReglaNegocioException("El {$lapso->nombre} ya está cerrado.");
        }

        $lapso->load('evaluaciones');

        if ($lapso->evaluaciones->isEmpty()) {
            throw new ReglaNegocioException("El {$lapso->nombre} no tiene evaluaciones.");
        }

        if (abs($lapso->pesoTotal() - 100) >= 0.01) {
            throw new ReglaNegocioException(
                "Las evaluaciones del {$lapso->nombre} suman {$lapso->pesoTotal()}%. Deben sumar exactamente 100% para cerrarlo."
            );
        }

        $lapso->update(['cerrado_at' => now(), 'cerrado_por' => $usuario->id]);
    }

    public function reabrirLapso(Lapso $lapso): void
    {
        $catedra = $lapso->catedra;
        $this->asegurarAnioEnCurso($catedra);

        if ($catedra->notasCerradas()) {
            throw new ReglaNegocioException('Primero debe reabrir el cierre de notas de la cátedra.');
        }

        $lapso->update(['cerrado_at' => null, 'cerrado_por' => null]);
    }

    /**
     * Cierre de notas: calcula la nota final (promedio de los lapsos) y la
     * definitiva de cada estudiante, y marca la materia como aprobada o reprobada.
     *
     * Con $forzar (cierre del año escolar) no se exige que los lapsos estén
     * cerrados: se cierran los que se puedan y quien no tenga nota calculable
     * queda "sin calificar".
     */
    public function cerrarNotasCatedra(Catedra $catedra, User $usuario, bool $forzar = false): void
    {
        if (! $forzar) {
            $this->asegurarAnioEnCurso($catedra);
        }

        if ($catedra->notasCerradas()) {
            throw new ReglaNegocioException('Las notas de la cátedra ya están cerradas.');
        }

        $catedra->load('lapsos.evaluaciones.notas');

        if (! $forzar) {
            $abiertos = $catedra->lapsos->reject->estaCerrado();

            if ($abiertos->isNotEmpty()) {
                throw new ReglaNegocioException(
                    'Debe cerrar primero: '.$abiertos->map->nombre->implode(', ').'.'
                );
            }
        }

        DB::transaction(function () use ($catedra, $usuario, $forzar) {
            if ($forzar) {
                foreach ($catedra->lapsos as $lapso) {
                    if (! $lapso->estaCerrado()
                        && $lapso->evaluaciones->isNotEmpty()
                        && abs($lapso->pesoTotal() - 100) < 0.01) {
                        $lapso->update(['cerrado_at' => now(), 'cerrado_por' => $usuario->id]);
                    }
                }
            }

            $lapsosValidos = $catedra->lapsos->every(
                fn (Lapso $l) => $l->evaluaciones->isNotEmpty() && abs($l->pesoTotal() - 100) < 0.01
            );

            $inscripciones = $catedra->inscripciones()
                ->where('estado', EstadoInscripcion::Cursando->value)
                ->get();

            $resumen = $this->calculadora->resumenCatedra($catedra, $inscripciones);

            foreach ($inscripciones as $inscripcion) {
                $dato = $resumen[$inscripcion->id];

                if (! $lapsosValidos || $dato['definitiva'] === null) {
                    $inscripcion->update([
                        'estado' => EstadoInscripcion::SinCalificar,
                        'nota_final' => null,
                        'nota_definitiva' => null,
                    ]);

                    continue;
                }

                $inscripcion->update([
                    'estado' => $dato['aprobado'] ? EstadoInscripcion::Aprobada : EstadoInscripcion::Reprobada,
                    'nota_final' => $dato['final'],
                    'nota_definitiva' => $dato['definitiva'],
                ]);
            }

            $catedra->update(['notas_cerradas_at' => now(), 'notas_cerradas_por' => $usuario->id]);
        });
    }

    public function reabrirNotasCatedra(Catedra $catedra): void
    {
        $this->asegurarAnioEnCurso($catedra);

        if (! $catedra->notasCerradas()) {
            throw new ReglaNegocioException('Las notas de la cátedra no están cerradas.');
        }

        DB::transaction(function () use ($catedra) {
            $catedra->inscripciones()
                ->whereIn('estado', [
                    EstadoInscripcion::Aprobada->value,
                    EstadoInscripcion::Reprobada->value,
                    EstadoInscripcion::SinCalificar->value,
                ])
                ->update([
                    'estado' => EstadoInscripcion::Cursando->value,
                    'nota_final' => null,
                    'nota_definitiva' => null,
                ]);

            $catedra->update(['notas_cerradas_at' => null, 'notas_cerradas_por' => null]);
        });
    }

    private function asegurarAnioEnCurso(Catedra $catedra): void
    {
        if (! $catedra->anioEscolar->permiteRegistros()) {
            throw new ReglaNegocioException(
                "El año escolar {$catedra->anioEscolar->nombre} no está en curso; sus notas no se pueden modificar."
            );
        }
    }
}
