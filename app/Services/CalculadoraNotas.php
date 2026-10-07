<?php

namespace App\Services;

use App\Enums\EstadoInscripcion;
use App\Models\Ajuste;
use App\Models\Catedra;
use App\Models\Inscripcion;
use App\Models\Lapso;
use Illuminate\Support\Collection;

/**
 * Cálculo de notas:
 *  - Nota del lapso = Σ (nota de la evaluación × porcentaje / 100). Las notas
 *    no cargadas cuentan como 0.
 *  - Nota final = promedio de las notas de los lapsos de la cátedra.
 *  - Definitiva = nota final redondeada (si así se configura) y comparada con
 *    la nota mínima aprobatoria.
 */
class CalculadoraNotas
{
    /**
     * @param  Collection<int, Inscripcion>  $inscripciones
     * @return array<int, array{nota: float|null, completa: bool}>  por inscripcion_id
     */
    public function notasLapso(Lapso $lapso, Collection $inscripciones): array
    {
        $lapso->loadMissing('evaluaciones.notas');
        $evaluaciones = $lapso->evaluaciones;

        $resultado = [];

        foreach ($inscripciones as $inscripcion) {
            if ($evaluaciones->isEmpty()) {
                $resultado[$inscripcion->id] = ['nota' => null, 'completa' => false];

                continue;
            }

            $suma = 0.0;
            $completa = true;

            foreach ($evaluaciones as $evaluacion) {
                $valor = $evaluacion->notas->firstWhere('inscripcion_id', $inscripcion->id)?->valor;

                if ($valor === null) {
                    $completa = false;

                    continue;
                }

                $suma += (float) $valor * (float) $evaluacion->peso / 100;
            }

            $resultado[$inscripcion->id] = [
                'nota' => round($suma, 2),
                'completa' => $completa && abs($lapso->pesoTotal() - 100) < 0.01,
            ];
        }

        return $resultado;
    }

    /**
     * Resumen de la cátedra por inscripción.
     *
     * @return array<int, array{lapsos: array<int, float|null>, completa: bool, final: float|null, definitiva: float|null, aprobado: bool|null}>
     */
    public function resumenCatedra(Catedra $catedra, ?Collection $inscripciones = null): array
    {
        $catedra->loadMissing('lapsos.evaluaciones.notas');
        $inscripciones ??= $catedra->inscripciones()->get();

        $porLapso = [];
        foreach ($catedra->lapsos as $lapso) {
            $porLapso[$lapso->numero] = $this->notasLapso($lapso, $inscripciones);
        }

        $resumen = [];

        foreach ($inscripciones as $inscripcion) {
            $notas = [];
            $completa = $catedra->lapsos->isNotEmpty();

            foreach ($catedra->lapsos as $lapso) {
                $dato = $porLapso[$lapso->numero][$inscripcion->id] ?? ['nota' => null, 'completa' => false];
                $notas[$lapso->numero] = $dato['nota'];
                $completa = $completa && $dato['completa'];
            }

            $calculables = array_filter($notas, fn ($n) => $n !== null);
            $final = count($calculables) === count($notas) && count($notas) > 0
                ? round(array_sum($notas) / count($notas), 2)
                : null;

            $definitiva = $final !== null ? $this->definitiva($final) : null;

            $resumen[$inscripcion->id] = [
                'lapsos' => $notas,
                'completa' => $completa,
                'final' => $final,
                'definitiva' => $definitiva,
                'aprobado' => $definitiva !== null ? $this->aprueba($definitiva) : null,
            ];
        }

        return $resumen;
    }

    public function definitiva(float $final): float
    {
        return Ajuste::redondearDefinitiva()
            ? (float) round($final, 0, PHP_ROUND_HALF_UP)
            : round($final, 2);
    }

    public function aprueba(float $definitiva): bool
    {
        return $definitiva >= Ajuste::notaMinimaAprobatoria();
    }

    /**
     * Progreso hacia la aprobación de una materia y desglose de sus notas.
     * Requiere cargado: catedra.lapsos.evaluaciones.notas (al menos las de esta inscripción).
     *
     *  - acumulado: puntos ya ganados sobre la nota final (lo no evaluado cuenta 0).
     *  - evaluado: fracción (0..1) del total de la materia que ya tiene nota.
     *  - maximoPosible: acumulado + lo que aún se puede ganar.
     *  - situacion: aprobada | reprobada | asegurada | imposible | en_curso | sin_evaluaciones
     *
     * @return array<string, mixed>
     */
    public function progreso(Inscripcion $inscripcion): array
    {
        $catedra = $inscripcion->catedra;
        $maximo = Ajuste::notaMaxima();
        $minimo = Ajuste::notaMinimaAprobatoria();
        $cantidad = max(1, $catedra->lapsos->count());

        $lapsos = [];
        $acumulado = 0.0;
        $evaluado = 0.0;

        foreach ($catedra->lapsos as $lapso) {
            $evaluaciones = [];
            $notaLapso = 0.0;
            $pesoEvaluado = 0.0;

            foreach ($lapso->evaluaciones as $evaluacion) {
                $valor = $evaluacion->notas->firstWhere('inscripcion_id', $inscripcion->id)?->valor;
                $aporte = $valor !== null ? round((float) $valor * (float) $evaluacion->peso / 100, 2) : null;

                if ($valor !== null) {
                    $notaLapso += $aporte;
                    $pesoEvaluado += (float) $evaluacion->peso;
                }

                $evaluaciones[] = [
                    'nombre' => $evaluacion->nombre,
                    'peso' => (float) $evaluacion->peso,
                    'fecha' => $evaluacion->fecha,
                    'nota' => $valor !== null ? (float) $valor : null,
                    'aporte' => $aporte,
                ];
            }

            $lapsos[] = [
                'nombre' => $catedra->regimen->nombreLapso($lapso->numero),
                'cerrado' => $lapso->estaCerrado(),
                'nota' => $lapso->evaluaciones->isNotEmpty() ? round($notaLapso, 2) : null,
                'peso_total' => $lapso->pesoTotal(),
                'peso_evaluado' => round($pesoEvaluado, 2),
                'evaluaciones' => $evaluaciones,
            ];

            $acumulado += $notaLapso / $cantidad;
            $evaluado += min(100, $pesoEvaluado) / 100 / $cantidad;
        }

        $acumulado = round($acumulado, 2);
        $maximoPosible = round($acumulado + $maximo * (1 - $evaluado), 2);

        $situacion = match (true) {
            $inscripcion->estado === EstadoInscripcion::Aprobada => 'aprobada',
            $inscripcion->estado === EstadoInscripcion::Reprobada => 'reprobada',
            $evaluado <= 0 => 'sin_evaluaciones',
            $acumulado >= $minimo => 'asegurada',
            $maximoPosible < $minimo => 'imposible',
            default => 'en_curso',
        };

        // Materias ya cerradas: la barra muestra la definitiva.
        $puntos = $inscripcion->nota_definitiva !== null ? (float) $inscripcion->nota_definitiva : $acumulado;

        return [
            'lapsos' => $lapsos,
            'acumulado' => $acumulado,
            'puntos' => $puntos,
            'evaluado' => round($evaluado, 4),
            'maximo' => $maximo,
            'minimo' => $minimo,
            'maximoPosible' => min($maximo, $maximoPosible),
            'faltan' => max(0, round($minimo - $acumulado, 2)),
            'situacion' => $situacion,
        ];
    }
}
