<?php

namespace Tests\Feature;

use App\Enums\EstadoAnio;
use App\Enums\Rol;
use App\Models\AnioEscolar;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Nota;
use App\Models\User;
use App\Services\CalculadoraNotas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgresoAprobacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_el_progreso_y_lo_muestra_en_el_expediente(): void
    {
        $anio = AnioEscolar::query()->create(['nombre' => '2026-2027', 'estado' => EstadoAnio::EnCurso, 'regimen_predeterminado' => 'trimestral']);
        $catedra = $anio->catedras()->create([
            'asignatura_id' => Asignatura::query()->create(['nombre' => 'Armonía'])->id,
            'nivel_id' => Nivel::query()->create(['nombre' => 'Nivel I', 'orden' => 5])->id,
            'seccion' => 'U', 'regimen' => 'trimestral', 'cantidad_lapsos' => 2,
        ]);
        $estudiante = Estudiante::query()->create(['cedula' => '3000001', 'apellidos_nombres' => 'ROJAS MARIA', 'estado' => 'activo']);
        $matricula = Matricula::query()->create(['estudiante_id' => $estudiante->id, 'anio_escolar_id' => $anio->id, 'estado' => 'inscrito']);
        $inscripcion = Inscripcion::query()->create(['matricula_id' => $matricula->id, 'estudiante_id' => $estudiante->id, 'catedra_id' => $catedra->id, 'estado' => 'cursando']);

        // I Trimestre: 60% con 15 y 40% sin nota aún.
        $lapso = $catedra->lapsos()->where('numero', 1)->first();
        $examen = $lapso->evaluaciones()->create(['nombre' => 'Examen', 'peso' => 60]);
        $lapso->evaluaciones()->create(['nombre' => 'Proyecto', 'peso' => 40]);
        Nota::query()->create(['evaluacion_id' => $examen->id, 'inscripcion_id' => $inscripcion->id, 'valor' => 15]);

        $p = app(CalculadoraNotas::class)->progreso($inscripcion->fresh('catedra.lapsos.evaluaciones.notas'));

        $this->assertSame(4.5, $p['acumulado']);           // 15 × 60% = 9 en el lapso → 9 / 2 lapsos
        $this->assertSame(0.3, $p['evaluado']);            // 60% de 1 de 2 lapsos
        $this->assertSame(18.5, $p['maximoPosible']);      // 4,5 + 20 × 0,7
        $this->assertSame(5.5, $p['faltan']);
        $this->assertSame('en_curso', $p['situacion']);
        $this->assertSame(9.0, $p['lapsos'][0]['nota']);
        $this->assertSame(9.0, $p['lapsos'][0]['evaluaciones'][0]['aporte']);

        $this->actingAs(User::factory()->create(['rol' => Rol::Control]))
            ->get(route('estudiantes.show', $estudiante))
            ->assertOk()
            ->assertSee('Progreso y notas por año escolar')
            ->assertSee('faltan 5,5 para aprobar')
            ->assertSee('30% evaluado');
    }
}
