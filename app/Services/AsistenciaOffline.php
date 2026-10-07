<?php

namespace App\Services;

use App\Enums\EstadoAsistencia;
use App\Enums\EstadoInscripcion;
use App\Exceptions\ReglaNegocioException;
use App\Models\AnioEscolar;
use App\Models\Catedra;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Asistencia sin conexión:
 *  1. El profesor descarga un archivo HTML con sus cátedras y listas (datosParaArchivo).
 *  2. Sin internet pasa lista; el archivo genera un .txt con un código
 *     "SIGAC1-<datos en base64url>-<verificación>".
 *  3. Con internet sube el código: se analiza (vista previa) y se importa.
 */
class AsistenciaOffline
{
    public const PREFIJO = 'SIGAC1';

    public function __construct(private RegistroAsistencia $registro)
    {
    }

    /**
     * Cátedras y estudiantes (que cursan) del profesor en el año en curso.
     *
     * @return array<string, mixed>
     */
    public function datosParaArchivo(User $profesor, AnioEscolar $anio): array
    {
        $catedras = $profesor->catedras()
            ->delAnio($anio)
            ->ordenadas()
            ->with(['asignatura', 'nivel', 'inscripciones' => fn ($q) => $q
                ->where('estado', EstadoInscripcion::Cursando->value)
                ->with('estudiante')])
            ->get();

        return [
            'version' => 1,
            'usuario' => ['id' => $profesor->id, 'nombre' => $profesor->name],
            'anio' => $anio->nombre,
            'generado' => now()->format('d/m/Y h:i a'),
            'fechaMinima' => $anio->fecha_inicio?->toDateString(),
            'fechaMaxima' => $anio->fecha_fin?->toDateString(),
            'catedras' => $catedras->map(fn (Catedra $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'horario' => $c->horario,
                'alumnos' => $c->inscripciones
                    ->sortBy(fn ($i) => $i->estudiante->apellidos_nombres)
                    ->map(fn ($i) => ['id' => $i->id, 'nombre' => $i->estudiante->apellidos_nombres, 'cedula' => $i->estudiante->cedula])
                    ->values(),
            ])->values(),
        ];
    }

    /** CSS compilado de la app, para incrustarlo en el archivo sin conexión. */
    public function css(): string
    {
        $manifiesto = json_decode((string) @file_get_contents(public_path('build/manifest.json')), true);
        $archivo = public_path('build/'.($manifiesto['resources/css/app.css']['file'] ?? ''));

        // Sin las fuentes externas: el archivo sin conexión usa las del sistema.
        return is_file($archivo)
            ? preg_replace('/@font-face\s*{[^}]*}/', '', (string) file_get_contents($archivo))
            : '';
    }

    /**
     * Extrae y verifica el código de un texto (contenido del .txt o pegado).
     *
     * @return array{v: int, u: int, n?: string, g?: string, j: list<array{c: int, f: string, o?: string, e: array<string, string>}>}
     */
    public function decodificar(string $texto): array
    {
        if (! preg_match('/'.self::PREFIJO.'-([A-Za-z0-9_-]+)-([0-9a-f]{8})/', preg_replace('/\s+/', '', $texto), $m)) {
            throw new ReglaNegocioException('No se encontró un código de asistencia válido. Debe empezar por "'.self::PREFIJO.'-".');
        }

        [, $datos, $verificacion] = $m;

        if (self::verificacion($datos) !== $verificacion) {
            throw new ReglaNegocioException('El código está incompleto o fue modificado. Vuelva a copiarlo completo o suba el archivo .txt.');
        }

        $json = base64_decode(strtr($datos, '-_', '+/'), true);
        $carga = $json !== false ? json_decode($json, true) : null;

        if (! is_array($carga) || ($carga['v'] ?? null) !== 1 || ! is_array($carga['j'] ?? null)) {
            throw new ReglaNegocioException('El código no tiene el formato esperado.');
        }

        return $carga;
    }

    /** Suma de verificación FNV-1a de 32 bits (la misma que calcula el archivo sin conexión). */
    public static function verificacion(string $texto): string
    {
        $hash = 0x811C9DC5;

        foreach (unpack('C*', $texto) as $byte) {
            $hash ^= $byte;
            $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
        }

        return str_pad(dechex($hash), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Qué pasará con cada asistencia del código (vista previa).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function analizar(array $carga, User $usuario): Collection
    {
        return collect($carga['j'])->map(function ($j) use ($usuario) {
            $item = [
                'catedra' => null,
                'fecha' => null,
                'estados' => [],
                'observacion' => trim((string) ($j['o'] ?? '')),
                'resultado' => 'error',
                'mensaje' => null,
                'conteo' => ['P' => 0, 'A' => 0, 'R' => 0, 'J' => 0],
                'omitidos' => 0,
                'diferencias' => null,
            ];

            $catedra = Catedra::query()->with(['anioEscolar', 'asignatura', 'nivel'])->find((int) ($j['c'] ?? 0));
            $item['catedra'] = $catedra;

            try {
                $fecha = Carbon::createFromFormat('!Y-m-d', (string) ($j['f'] ?? ''));
            } catch (\Throwable) {
                $fecha = null;
            }
            $item['fecha'] = $fecha;

            if (! $catedra) {
                return ['mensaje' => 'La cátedra ya no existe.'] + $item;
            }
            if (! $usuario->can('registrar', $catedra)) {
                return ['mensaje' => 'No tiene permiso para registrar asistencia en esta cátedra.'] + $item;
            }
            if (! $catedra->anioEscolar->permiteRegistros()) {
                return ['mensaje' => "El año escolar {$catedra->anioEscolar->nombre} no está en curso."] + $item;
            }
            if (! $fecha) {
                return ['mensaje' => 'Fecha inválida.'] + $item;
            }
            if ($fecha->isAfter(today())) {
                return ['mensaje' => 'La fecha es futura.'] + $item;
            }
            $anio = $catedra->anioEscolar;
            if (($anio->fecha_inicio && $fecha->lt($anio->fecha_inicio)) || ($anio->fecha_fin && $fecha->gt($anio->fecha_fin))) {
                return ['mensaje' => "La fecha está fuera del año escolar {$anio->nombre}."] + $item;
            }

            // Solo estudiantes que siguen en la cátedra.
            $validas = $catedra->inscripciones()->pluck('id')->flip();
            foreach ((array) ($j['e'] ?? []) as $inscripcionId => $codigo) {
                $estado = EstadoAsistencia::tryFrom((string) $codigo);
                if ($estado && $validas->has((int) $inscripcionId)) {
                    $item['estados'][(int) $inscripcionId] = $estado->value;
                    $item['conteo'][$estado->value]++;
                } else {
                    $item['omitidos']++;
                }
            }

            if ($item['estados'] === []) {
                return ['mensaje' => 'Ningún estudiante del código pertenece a la cátedra.'] + $item;
            }

            $existente = $catedra->jornadas()->whereDate('fecha', $fecha->toDateString())->first();

            if ($existente?->estaCerrada()) {
                return [
                    'resultado' => 'cerrada',
                    'mensaje' => 'La asistencia de ese día ya está cerrada en el sistema; se omitirá. Reábrala con su justificación si necesita cambiarla.',
                ] + $item;
            }

            if ($existente) {
                $item['diferencias'] = count($this->registro->cambios(
                    array_intersect_key($existente->estadosActuales(), $item['estados']),
                    $item['estados'],
                ));

                return ['resultado' => 'actualiza', 'mensaje' => 'Ya hay asistencia registrada ese día; se actualizará.'] + $item;
            }

            return ['resultado' => 'nueva'] + $item;
        });
    }

    /**
     * Registra las asistencias válidas del código.
     *
     * @return array{importadas: int, omitidas: int}
     */
    public function importar(array $carga, User $usuario, bool $cerrar): array
    {
        $importadas = 0;
        $omitidas = 0;

        foreach ($this->analizar($carga, $usuario) as $item) {
            if (! in_array($item['resultado'], ['nueva', 'actualiza'], true)) {
                $omitidas++;

                continue;
            }

            try {
                $jornada = $this->registro->guardar(
                    $item['catedra'],
                    $item['fecha'],
                    $item['estados'],
                    $usuario,
                    $item['observacion'] !== '' ? $item['observacion'] : $item['catedra']->jornadas()->whereDate('fecha', $item['fecha'])->value('observacion'),
                );

                if ($cerrar) {
                    $this->registro->cerrarJornada($jornada, $usuario);
                }

                $importadas++;
            } catch (ReglaNegocioException) {
                $omitidas++;
            }
        }

        return ['importadas' => $importadas, 'omitidas' => $omitidas];
    }
}
