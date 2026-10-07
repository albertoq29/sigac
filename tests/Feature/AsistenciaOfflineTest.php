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
use App\Models\User;
use App\Services\AsistenciaOffline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AsistenciaOfflineTest extends TestCase
{
    use RefreshDatabase;

    private User $profesor;

    private Catedra $catedra;

    private Catedra $ajena;

    /** @var list<Inscripcion> */
    private array $inscripciones = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->profesor = User::factory()->create(['rol' => Rol::Profesor, 'name' => 'EVERLYS GUZMAN']);
        $anio = AnioEscolar::query()->create(['nombre' => '2026-2027', 'estado' => EstadoAnio::EnCurso, 'regimen_predeterminado' => 'trimestral']);
        $asignatura = Asignatura::query()->create(['nombre' => 'Lenguaje Musical']);
        $nivel = Nivel::query()->create(['nombre' => '1er Año', 'orden' => 1]);

        $this->catedra = $anio->catedras()->create(['asignatura_id' => $asignatura->id, 'nivel_id' => $nivel->id, 'seccion' => 'A', 'profesor_id' => $this->profesor->id, 'regimen' => 'trimestral']);
        $this->ajena = $anio->catedras()->create(['asignatura_id' => $asignatura->id, 'nivel_id' => $nivel->id, 'seccion' => 'B', 'profesor_id' => User::factory()->create()->id, 'regimen' => 'trimestral']);

        foreach (['PEREZ ANA', 'GOMEZ LUIS'] as $n => $nombre) {
            $estudiante = Estudiante::query()->create(['cedula' => '2000000'.$n, 'apellidos_nombres' => $nombre, 'estado' => 'activo']);
            $matricula = Matricula::query()->create(['estudiante_id' => $estudiante->id, 'anio_escolar_id' => $anio->id, 'estado' => 'inscrito']);
            $this->inscripciones[] = Inscripcion::query()->create(['matricula_id' => $matricula->id, 'estudiante_id' => $estudiante->id, 'catedra_id' => $this->catedra->id, 'estado' => 'cursando']);
        }
    }

    /** Genera el código igual que el archivo sin conexión. */
    private function codigo(array $jornadas): string
    {
        $datos = rtrim(strtr(base64_encode(json_encode(['v' => 1, 'u' => $this->profesor->id, 'n' => 'EVERLYS GUZMAN', 'j' => $jornadas])), '+/', '-_'), '=');

        return AsistenciaOffline::PREFIJO.'-'.$datos.'-'.AsistenciaOffline::verificacion($datos);
    }

    public function test_descarga_el_archivo_sin_conexion_con_sus_alumnos(): void
    {
        $respuesta = $this->actingAs($this->profesor)->get(route('offline.descargar'))->assertOk();

        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $respuesta->assertSee('Asistencia sin conexión')->assertSee('PEREZ ANA')->assertDontSee('Sec. B');
    }

    public function test_sube_el_txt_revisa_e_importa_cerrando(): void
    {
        $ayer = today()->subDay()->toDateString();
        $hoy = today()->toDateString();
        [$a, $b] = $this->inscripciones;

        $codigo = $this->codigo([
            ['c' => $this->catedra->id, 'f' => $ayer, 'o' => 'Clase en el patio', 'e' => [$a->id => 'P', $b->id => 'A']],
            ['c' => $this->catedra->id, 'f' => $hoy, 'o' => '', 'e' => [$a->id => 'R', $b->id => 'P', 999999 => 'P']],
            ['c' => $this->ajena->id, 'f' => $hoy, 'o' => '', 'e' => [$a->id => 'P']],
        ]);

        $txt = UploadedFile::fake()->createWithContent('asistencias-sigac.txt', "SIGAC - Asistencias\r\nResumen...\r\n\r\n{$codigo}\r\n");

        $this->actingAs($this->profesor)
            ->post(route('offline.revisar'), ['archivo' => $txt])
            ->assertOk()
            ->assertSee('Nueva')
            ->assertSee('No tiene permiso')
            ->assertSee('ya no están en la cátedra');

        $this->post(route('offline.importar'), ['codigo' => $codigo, 'cerrar' => 1])
            ->assertRedirect(route('offline.index'))
            ->assertSessionHas('exito');

        $this->assertSame(2, Jornada::query()->count());
        $jornadaAyer = $this->catedra->jornadas()->whereDate('fecha', $ayer)->first();
        $this->assertTrue($jornadaAyer->estaCerrada());
        $this->assertSame('Clase en el patio', $jornadaAyer->observacion);
        $this->assertSame([$a->id => 'P', $b->id => 'A'], $jornadaAyer->estadosActuales());

        // Volver a subir el mismo código: las jornadas ya están cerradas y se omiten.
        $this->post(route('offline.revisar'), ['codigo' => $codigo])->assertOk()->assertSee('Cerrada: se omite');
    }

    public function test_rechaza_un_codigo_alterado(): void
    {
        $codigo = $this->codigo([['c' => $this->catedra->id, 'f' => today()->toDateString(), 'e' => [$this->inscripciones[0]->id => 'P']]]);
        $alterado = substr($codigo, 0, 20).'X'.substr($codigo, 21);

        $this->actingAs($this->profesor)
            ->from(route('offline.index'))
            ->post(route('offline.revisar'), ['codigo' => $alterado])
            ->assertRedirect(route('offline.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Jornada::query()->count());
    }
}
