<?php

namespace App\Support\Legacy;

use App\Enums\EstadoAnio;
use App\Enums\EstadoAsistencia;
use App\Enums\EstadoEstudiante;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoMatricula;
use App\Enums\Regimen;
use App\Enums\Rol;
use App\Models\AnioEscolar;
use App\Models\Asignatura;
use App\Models\Asistencia;
use App\Models\Catedra;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Jornada;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importa los datos de SIGAC v7.5 (archivos JSON) a la base de datos MySQL.
 *
 * Fuentes (carpeta de la app anterior):
 *  - data/database.json ............ estudiantes vigentes y sus cátedras
 *  - database.json (raíz) .......... copia antigua: estudiantes que luego se eliminaron
 *  - asistencias_reportadas.json, backups/*.json, data/backups/*.json,
 *    data/asistencias_reportadas.json ... asistencias (las más recientes prevalecen)
 *
 * Reparaciones aplicadas:
 *  - La materia "IMI - Arte, Sonoridad y Movimiento" contiene una coma, que la app
 *    anterior usaba como separador: se reconstruye y se descartan los fragmentos rotos.
 *  - Cátedras sin sección (formato antiguo) quedan en la sección "U" (única).
 *  - Nombres de docentes escritos de distinta forma ("Everlyn Guzmán" / "EVERLYS GUZMAN")
 *    se unifican.
 *  - Cédulas corregidas en la app anterior se enlazan con sus asistencias viejas.
 */
class ImportadorLegacy
{
    public const ARTE = 'IMI - Arte, Sonoridad y Movimiento';

    private const MOTIVO_ELIMINADO = 'Registro eliminado en el sistema anterior (recuperado en la migración).';

    private const MOTIVO_CAMBIO = 'Cambio de cátedra en el sistema anterior (reconstruido a partir de las asistencias).';

    private AnioEscolar $anio;

    /** @var array<string, User> clave normalizada => profesor */
    private array $profesores = [];

    /** @var array<string, string> clave normalizada (alias) => clave canónica */
    private array $aliasProfesor = [];

    /** @var array<string, Catedra> "materia|nivel|sección|docente" => cátedra */
    private array $catedras = [];

    /** @var array<int, Catedra> */
    private array $catedrasPorId = [];

    /** @var array<string, Asignatura> */
    private array $asignaturas = [];

    /** @var array<string, Nivel> */
    private array $niveles = [];

    /** @var array<string, Estudiante> cédula => estudiante */
    private array $estudiantes = [];

    /** @var array<int, Matricula> estudiante_id => matrícula */
    private array $matriculas = [];

    /** @var array<string, Inscripcion> "catedra_id|estudiante_id" => inscripción */
    private array $inscripciones = [];

    /** @var array<string, Jornada> "catedra_id|fecha" => jornada */
    private array $jornadas = [];

    /** @var array<string, string> cédula antigua => cédula vigente */
    private array $aliasCedula = [];

    /** @var array<int, true> inscripciones reconstruidas desde asistencias */
    private array $inscripcionesReconstruidas = [];

    /** @var array<string, int> */
    private array $estadisticas = [];

    /** @var list<string> */
    private array $avisos = [];

    /** @var array<int, array{nombre: string, usuario: string, password: string}> */
    private array $credenciales = [];

    public function __construct(
        private string $ruta,
        private string $nombreAnio = '2025-2026',
    ) {
        $this->ruta = rtrim($ruta, '/\\');
    }

    /**
     * @return array{estadisticas: array<string, int>, avisos: list<string>, credenciales: array<int, array{nombre: string, usuario: string, password: string}>}
     */
    public function ejecutar(): array
    {
        $vigentes = $this->leerJson('data/database.json') ?? $this->leerJson('database.json');

        if ($vigentes === null) {
            throw new RuntimeException("No se encontró data/database.json ni database.json en {$this->ruta}");
        }

        $antiguos = file_exists($this->ruta.'/data/database.json') ? ($this->leerJson('database.json') ?? []) : [];

        DB::transaction(function () use ($vigentes, $antiguos) {
            (new CatalogoSeeder)->run();
            $this->cargarCatalogos();
            $this->prepararAnio();
            $this->detectarCedulasCorregidas($vigentes, $antiguos);
            $eliminados = $this->eliminados($vigentes, $antiguos);
            $this->prepararProfesores($vigentes, $eliminados);
            $this->importarEstudiantesVigentes($vigentes);
            $this->importarEstudiantesEliminados($eliminados);
            $this->importarAsistencias();
            $this->completarFechasDeRetiro();
        });

        return [
            'estadisticas' => $this->estadisticas,
            'avisos' => array_values(array_unique($this->avisos)),
            'credenciales' => $this->credenciales,
        ];
    }

    // ------------------------------------------------------------------
    // Preparación
    // ------------------------------------------------------------------

    private function cargarCatalogos(): void
    {
        foreach (Asignatura::all() as $asignatura) {
            $this->asignaturas[$asignatura->nombre] = $asignatura;
        }

        foreach (Nivel::all() as $nivel) {
            $this->niveles[$nivel->nombre] = $nivel;
        }
    }

    private function prepararAnio(): void
    {
        $enCurso = AnioEscolar::actual();

        $this->anio = AnioEscolar::query()->firstOrCreate(
            ['nombre' => $this->nombreAnio],
            [
                'estado' => $enCurso ? EstadoAnio::Planificacion : EstadoAnio::EnCurso,
                'regimen_predeterminado' => Regimen::Trimestral,
                'iniciado_at' => $enCurso ? null : now(),
                'observaciones' => 'Año escolar importado desde SIGAC v7.5.',
                'sin_registro_notas' => true,
            ]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $vigentes
     * @param  list<array<string, mixed>>  $eliminados
     */
    private function prepararProfesores(array $vigentes, array $eliminados): void
    {
        // Frecuencia de cada forma escrita del nombre, priorizando la base vigente.
        $formas = [];

        foreach ([[$vigentes, 3], [$eliminados, 1]] as [$lista, $peso]) {
            foreach ($lista as $registro) {
                $cedula = $this->normalizarCedula((string) ($registro['cedula'] ?? ''));
                foreach ($this->parsearCatedras((string) ($registro['catedras'] ?? ''), $cedula) as $entrada) {
                    $clave = $this->claveNombre($entrada['docente']);
                    if ($clave === '') {
                        continue;
                    }
                    $formas[$clave][$entrada['docente']] = ($formas[$clave][$entrada['docente']] ?? 0) + $peso;
                }
            }
        }

        // Nombres que solo aparecen en las asistencias.
        foreach ($this->fuentesAsistencia() as $archivo) {
            foreach ($this->leerJson($archivo) ?? [] as $registro) {
                $nombre = trim((string) ($registro['docente'] ?? ''));
                $clave = $this->claveNombre($nombre);
                if ($clave !== '') {
                    $formas[$clave][$nombre] = ($formas[$clave][$nombre] ?? 0);
                }
            }
        }

        // Ordenar por frecuencia: las formas más usadas se vuelven canónicas.
        uasort($formas, fn ($a, $b) => array_sum($b) <=> array_sum($a));

        foreach ($formas as $clave => $variantes) {
            $canonica = $this->buscarProfesorSimilar($clave);

            if ($canonica !== null) {
                $this->aliasProfesor[$clave] = $canonica;

                continue;
            }

            arsort($variantes);
            $nombre = (string) array_key_first($variantes);
            $this->profesores[$clave] = $this->crearProfesor($nombre);
            $this->aliasProfesor[$clave] = $clave;
        }

        $this->estadisticas['profesores'] = count($this->profesores);
    }

    private function buscarProfesorSimilar(string $clave): ?string
    {
        foreach (array_keys($this->profesores) as $existente) {
            if (strlen($clave) > 6 && levenshtein($clave, $existente) <= 2) {
                $this->avisos[] = "Docente \"{$clave}\" unificado con \"{$existente}\".";

                return $existente;
            }
        }

        return null;
    }

    private function crearProfesor(string $nombre): User
    {
        $nombre = $this->limpiarTexto($nombre);
        $base = Str::slug(Str::ascii($nombre), '.') ?: 'profesor';
        $usuario = $base;
        $n = 2;

        while (User::query()->where('username', $usuario)->exists()) {
            $usuario = $base.$n++;
        }

        $password = Str::password(10, symbols: false);

        $profesor = User::query()->create([
            'name' => $nombre,
            'username' => $usuario,
            'rol' => Rol::Profesor,
            'password' => $password,
            'debe_cambiar_password' => true,
        ]);

        $this->credenciales[] = ['nombre' => $nombre, 'usuario' => $usuario, 'password' => $password];

        return $profesor;
    }

    /**
     * @param  list<array<string, mixed>>  $vigentes
     * @param  list<array<string, mixed>>  $antiguos
     */
    private function detectarCedulasCorregidas(array $vigentes, array $antiguos): void
    {
        $porNombre = [];
        $cedulasVigentes = [];

        foreach ($vigentes as $registro) {
            $cedula = $this->normalizarCedula((string) $registro['cedula']);
            $cedulasVigentes[$cedula] = true;
            $porNombre[$this->claveNombre((string) $registro['apellidos_nombres'])] = $cedula;
        }

        foreach ($antiguos as $registro) {
            $cedula = $this->normalizarCedula((string) $registro['cedula']);
            $nombre = $this->claveNombre((string) $registro['apellidos_nombres']);

            if (! isset($cedulasVigentes[$cedula]) && isset($porNombre[$nombre])) {
                $this->aliasCedula[$cedula] = $porNombre[$nombre];
                $this->avisos[] = "Cédula {$cedula} corregida a {$porNombre[$nombre]} ({$registro['apellidos_nombres']}).";
            }
        }
    }

    // ------------------------------------------------------------------
    // Estudiantes
    // ------------------------------------------------------------------

    /** @param  list<array<string, mixed>>  $vigentes */
    private function importarEstudiantesVigentes(array $vigentes): void
    {
        foreach ($vigentes as $registro) {
            $estudiante = $this->crearEstudiante($registro, EstadoEstudiante::Activo);

            $matricula = $this->crearMatricula($estudiante, [
                'seccion' => $this->normalizarSeccionGeneral($registro['seccion'] ?? null),
                'estado' => EstadoMatricula::Inscrito,
            ]);

            foreach ($this->parsearCatedras((string) ($registro['catedras'] ?? ''), (string) $estudiante->cedula) as $entrada) {
                $catedra = $this->catedraPara($entrada, $matricula->seccion);
                $this->crearInscripcion($matricula, $catedra, $entrada['horario'], EstadoInscripcion::Cursando);
            }
        }

        $this->estadisticas['estudiantes_activos'] = count($vigentes);
    }

    /**
     * Registros de la copia antigua que ya no existen en la base vigente
     * (sin contar los que solo cambiaron de cédula).
     *
     * @param  list<array<string, mixed>>  $vigentes
     * @param  list<array<string, mixed>>  $antiguos
     * @return list<array<string, mixed>>
     */
    private function eliminados(array $vigentes, array $antiguos): array
    {
        $vigentesCedulas = array_flip(array_map(fn ($r) => $this->normalizarCedula((string) $r['cedula']), $vigentes));
        $vistos = [];

        return array_values(array_filter($antiguos, function ($registro) use ($vigentesCedulas, &$vistos) {
            $cedula = $this->normalizarCedula((string) $registro['cedula']);

            if (isset($vigentesCedulas[$cedula]) || isset($this->aliasCedula[$cedula]) || isset($vistos[$cedula])) {
                return false;
            }

            return $vistos[$cedula] = true;
        }));
    }

    /**
     * Estudiantes que estaban en la copia antigua y fueron eliminados de la base
     * vigente: se importan como retirados para conservar su historial.
     *
     * @param  list<array<string, mixed>>  $eliminados
     */
    private function importarEstudiantesEliminados(array $eliminados): void
    {
        foreach ($eliminados as $registro) {
            $cedula = $this->normalizarCedula((string) $registro['cedula']);
            $estudiante = $this->crearEstudiante($registro, EstadoEstudiante::Inactivo);
            $matricula = $this->crearMatricula($estudiante, [
                'seccion' => $this->normalizarSeccionGeneral($registro['seccion'] ?? null),
                'estado' => EstadoMatricula::Retirado,
                'motivo_retiro' => self::MOTIVO_ELIMINADO,
            ]);

            foreach ($this->parsearCatedras((string) ($registro['catedras'] ?? ''), $cedula) as $entrada) {
                $catedra = $this->catedraPara($entrada, $matricula->seccion);
                $inscripcion = $this->crearInscripcion($matricula, $catedra, $entrada['horario'], EstadoInscripcion::Retirada);
                $inscripcion->update(['motivo_retiro' => self::MOTIVO_ELIMINADO]);
            }
        }

        $this->estadisticas['estudiantes_retirados'] = count($eliminados);
    }

    /** @param  array<string, mixed>  $registro */
    private function crearEstudiante(array $registro, EstadoEstudiante $estado): Estudiante
    {
        $cedula = $this->normalizarCedula((string) $registro['cedula']);
        $fecha = (string) ($registro['fecha_nacimiento'] ?? '');
        $correo = Str::lower(trim((string) ($registro['correo'] ?? '')));
        $sexo = Str::upper(trim((string) ($registro['sexo'] ?? '')));

        $estudiante = Estudiante::query()->create([
            'cedula' => $cedula,
            'apellidos_nombres' => $this->limpiarTexto((string) $registro['apellidos_nombres']),
            'fecha_nacimiento' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null,
            'sexo' => in_array($sexo, ['F', 'M'], true) ? $sexo : null,
            'telefono' => $this->normalizarTelefono((string) ($registro['telefono'] ?? '')),
            'correo' => filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : null,
            'estado' => $estado,
        ]);

        return $this->estudiantes[$cedula] = $estudiante;
    }

    /** @param  array<string, mixed>  $datos */
    private function crearMatricula(Estudiante $estudiante, array $datos): Matricula
    {
        return $this->matriculas[$estudiante->id] = Matricula::query()->create([
            'estudiante_id' => $estudiante->id,
            'anio_escolar_id' => $this->anio->id,
            ...$datos,
        ]);
    }

    private function crearInscripcion(Matricula $matricula, Catedra $catedra, ?string $horario, EstadoInscripcion $estado): Inscripcion
    {
        $clave = $catedra->id.'|'.$matricula->estudiante_id;

        if (isset($this->inscripciones[$clave])) {
            return $this->inscripciones[$clave];
        }

        return $this->inscripciones[$clave] = Inscripcion::query()->create([
            'matricula_id' => $matricula->id,
            'estudiante_id' => $matricula->estudiante_id,
            'catedra_id' => $catedra->id,
            'horario' => $horario !== null && $horario !== $catedra->horario ? $horario : null,
            'estado' => $estado,
        ]);
    }

    // ------------------------------------------------------------------
    // Cátedras
    // ------------------------------------------------------------------

    /**
     * Cátedra de un año = asignatura + nivel + sección + docente.
     *
     * @param  array{materia: string, nivel: string, seccion: ?string, docente: string, horario: ?string}  $entrada
     */
    private function catedraPara(array $entrada, ?string $seccionGeneral = null): Catedra
    {
        $profesorClave = $this->claveProfesor($entrada['docente']);
        $seccion = $entrada['seccion'];

        if ($seccion === null) {
            // Formato antiguo sin sección: reutilizar una cátedra existente del mismo docente.
            $candidatas = $this->buscarCatedras($entrada['materia'], $entrada['nivel'], $profesorClave);

            if ($candidatas->isNotEmpty()) {
                return $candidatas->firstWhere('seccion', $seccionGeneral) ?? $candidatas->first();
            }

            $seccion = 'U';
        }

        $clave = implode('|', [$entrada['materia'], $entrada['nivel'], $seccion, $profesorClave]);

        if (! isset($this->catedras[$clave])) {
            $this->catedras[$clave] = Catedra::query()->create([
                'anio_escolar_id' => $this->anio->id,
                'asignatura_id' => $this->asignatura($entrada['materia'])->id,
                'nivel_id' => $this->nivel($entrada['nivel'])->id,
                'seccion' => $seccion,
                'profesor_id' => ($this->profesores[$profesorClave ?? ''] ?? null)?->id,
                'horario' => $entrada['horario'],
                'regimen' => $this->anio->regimen_predeterminado,
            ]);
            $this->catedras[$clave]->load(['asignatura', 'nivel']);
            $this->catedrasPorId[$this->catedras[$clave]->id] = $this->catedras[$clave];
            $this->estadisticas['catedras'] = ($this->estadisticas['catedras'] ?? 0) + 1;
        }

        return $this->catedras[$clave];
    }

    /** @return Collection<int, Catedra> */
    private function buscarCatedras(string $materia, string $nivel, ?string $profesorClave, ?string $seccion = null): Collection
    {
        return collect($this->catedras)
            ->filter(function (Catedra $c, string $clave) use ($materia, $nivel, $profesorClave, $seccion) {
                [$m, $n, $s, $p] = explode('|', $clave);

                return $m === $materia && $n === $nivel
                    && ($profesorClave === null || $p === $profesorClave)
                    && ($seccion === null || $s === $seccion);
            })
            ->values();
    }

    private function asignatura(string $nombre): Asignatura
    {
        return $this->asignaturas[$nombre] ??= Asignatura::query()->create([
            'nombre' => $nombre,
            'categoria' => CatalogoSeeder::categoriaPara($nombre),
        ]);
    }

    private function nivel(string $nombre): Nivel
    {
        return $this->niveles[$nombre] ??= Nivel::query()->create(['nombre' => $nombre, 'orden' => 99]);
    }

    /**
     * Separa "Materia|Nivel|Sección|Docente|Horario,Materia|..." reparando la coma
     * de "IMI - Arte, Sonoridad y Movimiento".
     *
     * @return list<array{materia: string, nivel: string, seccion: ?string, docente: string, horario: ?string}>
     */
    public function parsearCatedras(string $texto, ?string $cedula = null): array
    {
        $marcador = '@@ARTE@@';
        $texto = str_replace(self::ARTE, $marcador, $texto);
        $texto = preg_replace('/,\s*Sonoridad y Movimiento\|/u', ','.$marcador.'|', $texto);

        $entradas = [];
        $vistas = [];

        foreach (explode(',', $texto) as $fragmento) {
            $fragmento = trim(str_replace($marcador, self::ARTE, $fragmento));
            if ($fragmento === '') {
                continue;
            }

            $p = array_map(fn ($x) => $this->limpiarTexto($x), explode('|', $fragmento));

            if (count($p) >= 5) {
                [$materia, $nivel, $seccion, $docente, $horario] = $p;
            } elseif (count($p) === 4) {
                [$materia, $nivel, $docente, $horario] = $p;
                $seccion = null;
            } elseif (count($p) === 3) {
                [$materia, $nivel, $docente] = $p;
                $seccion = $horario = null;
            } else {
                $this->avisos[] = "Fragmento de cátedra descartado".($cedula ? " (cédula {$cedula})" : '').": \"{$fragmento}\".";

                continue;
            }

            $materia = $this->normalizarMateria($materia);
            $seccion = $this->normalizarSeccion($seccion);
            $clave = $materia.'|'.$nivel.'|'.$seccion.'|'.$this->claveNombre($docente);

            if (isset($vistas[$clave]) || $materia === '' || $nivel === '') {
                continue;
            }
            $vistas[$clave] = true;

            $entradas[] = [
                'materia' => $materia,
                'nivel' => $nivel,
                'seccion' => $seccion,
                'docente' => $docente,
                'horario' => $horario !== null && $horario !== '' ? $horario : null,
            ];
        }

        return $entradas;
    }

    // ------------------------------------------------------------------
    // Asistencias
    // ------------------------------------------------------------------

    /** Archivos de asistencia, de más antiguo a más reciente (los últimos prevalecen). */
    private function fuentesAsistencia(): array
    {
        $archivos = [];

        foreach (['asistencias_reportadas.json', 'backups/*.json', 'data/backups/*.json', 'data/asistencias_reportadas.json'] as $patron) {
            $encontrados = glob($this->ruta.'/'.$patron) ?: [];
            sort($encontrados);

            foreach ($encontrados as $archivo) {
                $relativo = ltrim(str_replace('\\', '/', substr($archivo, strlen($this->ruta))), '/');
                if (! in_array($relativo, $archivos, true)) {
                    $archivos[] = $relativo;
                }
            }
        }

        return $archivos;
    }

    private function importarAsistencias(): void
    {
        $registros = [];
        $orden = 0;

        foreach ($this->fuentesAsistencia() as $archivo) {
            $datos = $this->leerJson($archivo) ?? [];
            $this->estadisticas['asistencias_leidas'] = ($this->estadisticas['asistencias_leidas'] ?? 0) + count($datos);

            foreach ($datos as $r) {
                $registro = $this->interpretarAsistencia($r, $archivo);
                if ($registro !== null) {
                    $registro['orden'] = $orden++;
                    $registros[] = $registro;
                }
            }
        }

        // 1ª pasada: coinciden con una materia que el estudiante cursa.
        $pendientes = [];
        foreach ($registros as $registro) {
            $inscripcion = $this->inscripcionPropia($registro);
            if ($inscripcion) {
                $this->registrarAsistencia($inscripcion, $registro);
            } else {
                $pendientes[] = $registro;
            }
        }

        // 2ª pasada: cambios de sección / nivel / docente durante el año.
        foreach ($pendientes as $registro) {
            $this->registrarAsistencia($this->inscripcionReconstruida($registro), $registro);
        }

        $this->estadisticas['jornadas'] = count($this->jornadas);
        $this->estadisticas['asistencias'] = Asistencia::query()->count();
        $this->estadisticas['inscripciones_reconstruidas'] = count($this->inscripcionesReconstruidas);
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{estudiante: Estudiante, materia: string, nivel: string, seccion: ?string, docente: string, fecha: string, estado: EstadoAsistencia}|null
     */
    private function interpretarAsistencia(array $r, string $archivo): ?array
    {
        $cedula = $this->normalizarCedula((string) ($r['cedula'] ?? ''));
        $cedula = $this->aliasCedula[$cedula] ?? $cedula;
        $estudiante = $this->estudiantes[$cedula] ?? null;
        $fecha = (string) ($r['fecha'] ?? '');
        $estado = EstadoAsistencia::desdeTexto($r['status'] ?? $r['estado'] ?? null);
        $materiaTexto = trim((string) ($r['materia'] ?? ''));

        if (! $estudiante) {
            $this->descartar("Asistencia sin estudiante registrado (cédula {$cedula}, {$archivo}).");

            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || ! $estado) {
            $this->descartar("Asistencia con fecha o estado inválido ({$archivo}).");

            return null;
        }

        if (preg_match('/^(.*?)\s*\(([^()]*)\)\s*(?:\[Sec:\s*([^\]]*)\])?\s*$/u', $materiaTexto, $m)) {
            [$materia, $nivel, $seccion] = [$m[1], $m[2], $m[3] ?? null];
        } elseif (str_contains($materiaTexto, '|')) {
            [$materia, $nivel] = array_map('trim', explode('|', $materiaTexto));
            $seccion = null;
        } else {
            $this->descartar("Asistencia con materia ilegible \"{$materiaTexto}\" ({$archivo}).");

            return null;
        }

        return [
            'estudiante' => $estudiante,
            'materia' => $this->normalizarMateria($materia),
            'nivel' => trim($nivel),
            'seccion' => $this->normalizarSeccion($seccion),
            'docente' => trim((string) ($r['docente'] ?? '')),
            'fecha' => $fecha,
            'estado' => $estado,
        ];
    }

    /** @param  array<string, mixed>  $registro */
    private function inscripcionPropia(array $registro): ?Inscripcion
    {
        $candidatas = $this->inscripcionesDelEstudiante($registro['estudiante'], $registro['materia'], $registro['nivel']);
        $profesor = $this->claveProfesor($registro['docente']);

        $mismoDocente = $candidatas->filter(fn (Inscripcion $i) => $this->claveDeProfesor($i->catedra) === $profesor);

        return $mismoDocente->first(fn (Inscripcion $i) => $registro['seccion'] === null || $i->catedra->seccion === $registro['seccion'])
            ?? $mismoDocente->first();
    }

    /** @param  array<string, mixed>  $registro */
    private function inscripcionReconstruida(array $registro): Inscripcion
    {
        $estudiante = $registro['estudiante'];
        $profesor = $this->claveProfesor($registro['docente']);

        // Misma materia y mismo docente con otro nivel: etiqueta mal elegida o cambio
        // de nivel con el mismo profesor; se conserva en la materia que cursa.
        $mismoDocente = $this->inscripcionesDelEstudiante($estudiante, $registro['materia'])
            ->filter(fn (Inscripcion $i) => $this->claveDeProfesor($i->catedra) === $profesor);
        if ($mismoDocente->count() === 1) {
            return $mismoDocente->first();
        }

        $materia = $this->materiaExistente($registro['materia'], $registro['nivel'], $profesor);

        $candidatas = $this->buscarCatedras($materia, $registro['nivel'], $profesor, $registro['seccion']);
        if ($candidatas->isEmpty() && $registro['seccion'] !== null) {
            $candidatas = $this->buscarCatedras($materia, $registro['nivel'], $profesor);
        }

        $catedra = null;

        if ($candidatas->count() > 1) {
            $matricula = $this->matriculas[$estudiante->id];
            $catedra = $candidatas->first(fn (Catedra $c) => isset($this->jornadas[$c->id.'|'.$registro['fecha']]))
                ?? $candidatas->firstWhere('seccion', $matricula->seccion)
                ?? $candidatas->sortByDesc(fn (Catedra $c) => $c->inscripciones()->count())->first();
        } elseif ($candidatas->count() === 1) {
            $catedra = $candidatas->first();
        }

        if (! $catedra) {
            // Docente distinto (suplencia o nombre mal escrito): se usa la materia que el estudiante cursa.
            $propia = $this->inscripcionesDelEstudiante($estudiante, $registro['materia'], $registro['nivel'])->first();
            if ($propia) {
                return $propia;
            }

            $catedra = $this->catedraPara([
                'materia' => $materia,
                'nivel' => $registro['nivel'],
                'seccion' => $registro['seccion'] ?? 'U',
                'docente' => $registro['docente'],
                'horario' => null,
            ]);
        }

        $clave = $catedra->id.'|'.$estudiante->id;

        if (! isset($this->inscripciones[$clave])) {
            $inscripcion = $this->crearInscripcion($this->matriculas[$estudiante->id], $catedra, null, EstadoInscripcion::Retirada);
            $inscripcion->update(['motivo_retiro' => self::MOTIVO_CAMBIO]);
            $this->inscripcionesReconstruidas[$inscripcion->id] = true;
        }

        return $this->inscripciones[$clave];
    }

    /** Nombre antiguo sin prefijo ("Lenguaje Musical (Nivel III)") → "IMI - Lenguaje Musical". */
    private function materiaExistente(string $materia, string $nivel, ?string $profesor): string
    {
        if ($this->buscarCatedras($materia, $nivel, $profesor)->isEmpty()
            && $this->buscarCatedras('IMI - '.$materia, $nivel, $profesor)->isNotEmpty()) {
            return 'IMI - '.$materia;
        }

        return $materia;
    }

    /** @return Collection<int, Inscripcion> */
    private function inscripcionesDelEstudiante(Estudiante $estudiante, string $materia, ?string $nivel = null): Collection
    {
        return collect($this->inscripciones)
            ->filter(fn (Inscripcion $i, string $clave) => str_ends_with($clave, '|'.$estudiante->id))
            ->filter(function (Inscripcion $i) use ($materia, $nivel) {
                $catedra = $this->catedraPorId($i->catedra_id);
                $nombre = $catedra->asignatura->nombre;

                return ($nombre === $materia || $nombre === 'IMI - '.$materia)
                    && ($nivel === null || $catedra->nivel->nombre === $nivel);
            })
            ->each(fn (Inscripcion $i) => $i->setRelation('catedra', $this->catedraPorId($i->catedra_id)))
            ->values();
    }

    private function catedraPorId(int $id): Catedra
    {
        return $this->catedrasPorId[$id] ??= Catedra::query()->with(['asignatura', 'nivel'])->findOrFail($id);
    }

    private function claveDeProfesor(Catedra $catedra): ?string
    {
        foreach ($this->profesores as $clave => $profesor) {
            if ($profesor->id === $catedra->profesor_id) {
                return $clave;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $registro */
    private function registrarAsistencia(Inscripcion $inscripcion, array $registro): void
    {
        $clave = $inscripcion->catedra_id.'|'.$registro['fecha'];

        if (! isset($this->jornadas[$clave])) {
            $momento = Carbon::parse($registro['fecha'].' 12:00:00');
            $profesor = $this->profesores[$this->claveProfesor($registro['docente']) ?? ''] ?? null;

            $jornada = new Jornada([
                'catedra_id' => $inscripcion->catedra_id,
                'fecha' => $registro['fecha'],
                'registrado_por' => $profesor?->id,
            ]);
            $jornada->created_at = $momento;
            $jornada->updated_at = $momento;
            $jornada->save();

            $this->jornadas[$clave] = $jornada;
        }

        $jornada = $this->jornadas[$clave];
        $asistencia = Asistencia::query()->firstOrNew([
            'jornada_id' => $jornada->id,
            'inscripcion_id' => $inscripcion->id,
        ]);

        if ($asistencia->exists) {
            $this->estadisticas['asistencias_duplicadas'] = ($this->estadisticas['asistencias_duplicadas'] ?? 0) + 1;
        }

        $asistencia->estado = $registro['estado'];
        $asistencia->created_at ??= $jornada->created_at;
        $asistencia->updated_at = $jornada->created_at;
        $asistencia->timestamps = false;
        $asistencia->save();
    }

    /** Fecha de retiro aproximada = última asistencia registrada. */
    private function completarFechasDeRetiro(): void
    {
        $ultimaAsistencia = fn (array $inscripcionIds) => DB::table('asistencias')
            ->join('jornadas', 'jornadas.id', '=', 'asistencias.jornada_id')
            ->whereIn('asistencias.inscripcion_id', $inscripcionIds)
            ->max('jornadas.fecha');

        foreach (Inscripcion::query()->where('estado', EstadoInscripcion::Retirada->value)->whereNull('fecha_retiro')->get() as $inscripcion) {
            if ($fecha = $ultimaAsistencia([$inscripcion->id])) {
                $inscripcion->update(['fecha_retiro' => $fecha]);
            }
        }

        foreach (Matricula::query()->where('estado', EstadoMatricula::Retirado->value)->whereNull('fecha_retiro')->get() as $matricula) {
            if ($fecha = $ultimaAsistencia($matricula->inscripciones()->pluck('id')->all())) {
                $matricula->update(['fecha_retiro' => $fecha]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>>|null */
    private function leerJson(string $relativo): ?array
    {
        $archivo = $this->ruta.'/'.$relativo;

        if (! is_file($archivo)) {
            return null;
        }

        $datos = json_decode((string) file_get_contents($archivo), true);

        if (! is_array($datos)) {
            $this->avisos[] = "No se pudo leer {$relativo} (JSON inválido).";

            return null;
        }

        // La app anterior guardaba los textos escapados con htmlspecialchars.
        array_walk_recursive($datos, function (&$valor) {
            if (is_string($valor)) {
                $valor = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        });

        return array_values($datos);
    }

    private function descartar(string $motivo): void
    {
        $this->estadisticas['asistencias_descartadas'] = ($this->estadisticas['asistencias_descartadas'] ?? 0) + 1;
        $this->avisos[] = $motivo;
    }

    private function claveProfesor(string $nombre): ?string
    {
        $clave = $this->claveNombre($nombre);

        return $this->aliasProfesor[$clave] ?? ($clave !== '' ? $clave : null);
    }

    private function claveNombre(string $nombre): string
    {
        return Str::upper(preg_replace('/\s+/', ' ', trim(Str::ascii($nombre))));
    }

    private function limpiarTexto(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', $texto));
    }

    private function normalizarCedula(string $cedula): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', preg_replace('/^[VEve]-?/', '', trim($cedula)));
    }

    private function normalizarMateria(string $materia): string
    {
        $materia = $this->limpiarTexto($materia);

        return in_array($materia, ['Sonoridad y Movimiento', 'IMI - Arte'], true) ? self::ARTE : $materia;
    }

    private function normalizarSeccion(?string $seccion): ?string
    {
        $seccion = Str::upper(trim((string) $seccion));

        return in_array($seccion, ['', 'S/S', 'SS'], true) ? null : $seccion;
    }

    private function normalizarSeccionGeneral(mixed $seccion): ?string
    {
        $seccion = Str::upper($this->limpiarTexto((string) $seccion));

        return in_array($seccion, ['', 'NAN', 'NULL', 'S/S'], true) ? null : $seccion;
    }

    private function normalizarTelefono(string $telefono): ?string
    {
        $partes = preg_split('/[\s.,;\/]+/', trim($telefono), -1, PREG_SPLIT_NO_EMPTY);

        return $partes ? implode(' / ', $partes) : null;
    }
}
