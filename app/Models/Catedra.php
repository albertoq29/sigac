<?php

namespace App\Models;

use App\Enums\EstadoInscripcion;
use App\Enums\Regimen;
use App\Exceptions\ReglaNegocioException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catedra extends Model
{
    protected $table = 'catedras';

    protected $fillable = [
        'anio_escolar_id',
        'asignatura_id',
        'nivel_id',
        'seccion',
        'profesor_id',
        'horario',
        'regimen',
        'cantidad_lapsos',
        'notas_cerradas_at',
        'notas_cerradas_por',
    ];

    /** Control de Estudios puede designar 1, 2 o 3 lapsos por cátedra. */
    public const LAPSOS_PERMITIDOS = [1, 2, 3];

    protected $attributes = [
        'cantidad_lapsos' => 2,
    ];

    protected function casts(): array
    {
        return [
            'regimen' => Regimen::class,
            'cantidad_lapsos' => 'integer',
            'notas_cerradas_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Toda cátedra nace con sus lapsos (trimestres o semestres).
        static::created(fn (Catedra $catedra) => $catedra->sincronizarLapsos());
    }

    /**
     * Crea los lapsos que falten y elimina los que sobren según cantidad_lapsos.
     * No elimina lapsos que ya tengan evaluaciones o estén cerrados.
     */
    public function sincronizarLapsos(): void
    {
        for ($n = 1; $n <= $this->cantidad_lapsos; $n++) {
            $this->lapsos()->firstOrCreate(['numero' => $n]);
        }

        $sobrantes = $this->lapsos()
            ->where('numero', '>', $this->cantidad_lapsos)
            ->withCount('evaluaciones')
            ->get();

        foreach ($sobrantes as $lapso) {
            if ($lapso->estaCerrado() || $lapso->evaluaciones_count > 0) {
                throw new ReglaNegocioException(
                    "No se puede reducir a {$this->cantidad_lapsos} lapso(s): el {$this->regimen->nombreLapso($lapso->numero)} ya tiene evaluaciones o está cerrado."
                );
            }

            $lapso->delete();
        }

        $this->unsetRelation('lapsos');
    }

    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class);
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    public function notasCerradasPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notas_cerradas_por');
    }

    public function lapsos(): HasMany
    {
        return $this->hasMany(Lapso::class)->orderBy('numero');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /** Inscripciones que no han sido retiradas (lista de clase). */
    public function inscripcionesVigentes(): HasMany
    {
        return $this->hasMany(Inscripcion::class)->where('estado', '!=', EstadoInscripcion::Retirada->value);
    }

    public function jornadas(): HasMany
    {
        return $this->hasMany(Jornada::class);
    }

    /** "Lenguaje Musical · 1er Año · Sec. A" */
    protected function nombre(): Attribute
    {
        return Attribute::get(fn () => sprintf(
            '%s · %s · Sec. %s',
            $this->asignatura?->nombre,
            $this->nivel?->nombre,
            $this->seccion
        ));
    }

    public function notasCerradas(): bool
    {
        return $this->notas_cerradas_at !== null;
    }

    public function scopeDelAnio(Builder $query, AnioEscolar|int|null $anio): Builder
    {
        return $query->where('anio_escolar_id', $anio instanceof AnioEscolar ? $anio->id : $anio);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query
            ->join('asignaturas', 'asignaturas.id', '=', 'catedras.asignatura_id')
            ->join('niveles', 'niveles.id', '=', 'catedras.nivel_id')
            ->orderBy('asignaturas.nombre')
            ->orderBy('niveles.orden')
            ->orderBy('catedras.seccion')
            ->select('catedras.*');
    }
}
