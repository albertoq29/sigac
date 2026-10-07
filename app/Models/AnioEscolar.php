<?php

namespace App\Models;

use App\Enums\EstadoAnio;
use App\Enums\Regimen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnioEscolar extends Model
{
    protected $table = 'anios_escolares';

    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'regimen_predeterminado',
        'lapsos_predeterminados',
        'sin_registro_notas',
        'observaciones',
        'iniciado_at',
        'cerrado_at',
        'cerrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => EstadoAnio::class,
            'regimen_predeterminado' => Regimen::class,
            'lapsos_predeterminados' => 'integer',
            'sin_registro_notas' => 'boolean',
            'iniciado_at' => 'datetime',
            'cerrado_at' => 'datetime',
        ];
    }

    public function catedras(): HasMany
    {
        return $this->hasMany(Catedra::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    /** Año escolar en curso (solo puede haber uno). */
    public static function actual(): ?self
    {
        return static::query()->where('estado', EstadoAnio::EnCurso->value)->first();
    }

    /**
     * Año que se muestra por defecto: el que está en curso, o el más reciente.
     */
    public static function predeterminado(): ?self
    {
        return static::actual() ?? static::query()->orderByDesc('nombre')->first();
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('estado', '!=', EstadoAnio::Cerrado->value);
    }

    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('nombre');
    }

    public function estaCerrado(): bool
    {
        return $this->estado === EstadoAnio::Cerrado;
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === EstadoAnio::EnCurso;
    }

    public function enPlanificacion(): bool
    {
        return $this->estado === EstadoAnio::Planificacion;
    }

    /** Años sin registro de notas: no cuentan para promoción y sus materias se muestran como "cursadas". */
    public function tieneRegistroNotas(): bool
    {
        return ! $this->sin_registro_notas;
    }

    /** Asistencias y notas solo se registran en el año en curso. */
    public function permiteRegistros(): bool
    {
        return $this->estaEnCurso();
    }

    /** Se pueden crear cátedras e inscribir estudiantes mientras no esté cerrado. */
    public function permiteInscripciones(): bool
    {
        return ! $this->estaCerrado();
    }
}
