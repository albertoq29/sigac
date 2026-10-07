<?php

namespace Tests\Feature;

use App\Enums\EstadoAnio;
use App\Enums\Rol;
use App\Models\AnioEscolar;
use App\Models\Asignatura;
use App\Models\Catedra;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Jornada;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\ReaperturaJornada;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CierreAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;

    private User $control;

    private Catedra $catedra;

    /** @var list<Inscripcion> */
    private array $inscripciones = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->profesor = User::factory()->create(['rol' => Rol::Profesor]);
        $this->control = User::factory()->create(['rol' => Rol::Control]);

        $anio = AnioEscolar::query()->create(['nombre' => '2026-2027', 'estado' => EstadoAnio::EnCurso, 'regimen_predeterminado' => 'trimestral']);
        $this->catedra = $anio->catedras()->create([
            'asignatura_id' => Asignatura::query()->create(['nombre' => 'Lenguaje Musical'])->id,
            'nivel_id' => Nivel::query()->create(['nombre' => '1er Año', 'orden' => 1])->id,
            'seccion' => 'A',
            'profesor_id' => $this->profesor->id,
            'regimen' => 'trimestral',
        ]);

        foreach (['PEREZ ANA', 'GOMEZ LUIS'] as $n => $nombre) {
            $estudiante = Estudiante::query()->create(['cedula' => '1000000'.$n, 'apellidos_nombres' => $nombre, 'estado' => 'activo']);
            $matricula = Matricula::query()->create(['estudiante_id' => $estudiante->id, 'anio_escolar_id' => $anio->id, 'estado' => 'inscrito']);
            $this->inscripciones[] = Inscripcion::query()->create([
                'matricula_id' => $matricula->id, 'estudiante_id' => $estudiante->id, 'catedra_id' => $this->catedra->id, 'estado' => 'cursando',
            ]);
        }
    }

    private function estados(string $primero, string $segundo): array
    {
        return [$this->inscripciones[0]->id => $primero, $this->inscripciones[1]->id => $segundo];
    }

    public function test_cerrar_reabrir_con_motivo_y_registrar_cambios(): void
    {
        $fecha = today()->toDateString();
        $ruta = route('asistencia.store', $this->catedra);

        // 1. Guardar y cerrar.
        $this->actingAs($this->profesor)
            ->post($ruta, ['fecha' => $fecha, 'estados' => $this->estados('P', 'A'), 'cerrar' => 1])
            ->assertSessionHasNoErrors();

        $jornada = Jornada::query()->firstOrFail();
        $this->assertTrue($jornada->estaCerrada());

        // 2. Cerrada no se puede modificar.
        $this->post($ruta, ['fecha' => $fecha, 'estados' => $this->estados('P', 'P')])
            ->assertSessionHas('error');
        $this->assertSame('A', $jornada->fresh()->estadosActuales()[$this->inscripciones[1]->id]);

        // 3. Reabrir exige motivo.
        $this->post(route('jornadas.reabrir', $jornada), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post(route('jornadas.reabrir', $jornada), ['motivo' => 'corto'])->assertSessionHasErrors('motivo');
        $this->assertTrue($jornada->fresh()->estaCerrada());

        $this->post(route('jornadas.reabrir', $jornada), ['motivo' => 'Gomez llegó tarde, lo marqué ausente por error.'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($jornada->fresh()->estaCerrada());

        // 4. Corregir y volver a cerrar: queda registrado el cambio.
        $this->post($ruta, ['fecha' => $fecha, 'estados' => $this->estados('P', 'R'), 'cerrar' => 1])
            ->assertSessionHasNoErrors();

        $reapertura = ReaperturaJornada::query()->firstOrFail();
        $this->assertNotNull($reapertura->recerrada_at);
        $this->assertSame('Gomez llegó tarde, lo marqué ausente por error.', $reapertura->motivo);
        $this->assertCount(1, $reapertura->cambios);
        $this->assertSame(['estudiante' => 'GOMEZ LUIS', 'antes' => 'A', 'despues' => 'R'], array_intersect_key($reapertura->cambios[0], array_flip(['estudiante', 'antes', 'despues'])));
        $this->assertTrue($jornada->fresh()->estaCerrada());

        // 5. El profesor ve el historial; Control de Estudios lo revisa.
        $this->get(route('asistencia.create', [$this->catedra, 'fecha' => $fecha]))
            ->assertOk()->assertSee('Historial de reaperturas')->assertSee('Gomez llegó tarde');

        $this->actingAs($this->control)
            ->get(route('reaperturas.index'))
            ->assertOk()->assertSee('Gomez llegó tarde')->assertSee('GOMEZ LUIS')->assertSee('Marcar como revisada');

        $this->post(route('reaperturas.revisar', $reapertura))->assertSessionHas('exito');
        $this->assertNotNull($reapertura->fresh()->revisada_at);
    }

    public function test_otro_profesor_no_puede_cerrar_ni_reabrir(): void
    {
        $jornada = $this->catedra->jornadas()->create(['fecha' => today()->toDateString(), 'cerrada_at' => now()]);
        $otro = User::factory()->create(['rol' => Rol::Profesor]);

        $this->actingAs($otro)
            ->post(route('jornadas.reabrir', $jornada), ['motivo' => 'Quiero cambiar la asistencia de otro'])
            ->assertForbidden();
    }
}
