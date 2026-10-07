<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nivel extends Model
{
    /** Año de estudio: se promueve al siguiente aprobando todas sus materias. */
    public const TIPO_ANIO = 'anio';

    /** Nivel independiente (instrumento, Nivel I...): se asigna manualmente. */
    public const TIPO_NIVEL = 'nivel';

    protected $table = 'niveles';

    protected $fillable = ['nombre', 'orden', 'tipo', 'siguiente_nivel_id'];

    protected $attributes = [
        'tipo' => self::TIPO_NIVEL,
    ];

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    /** Año al que se promueve al aprobar todas sus materias. */
    public function siguiente(): BelongsTo
    {
        return $this->belongsTo(Nivel::class, 'siguiente_nivel_id');
    }

    public function catedras(): HasMany
    {
        return $this->hasMany(Catedra::class);
    }

    public function esAnio(): bool
    {
        return $this->tipo === self::TIPO_ANIO;
    }

    /** Grupos para mostrar las cátedras separadas, en este orden. */
    public const GRUPOS = ['Preparatorio', 'Años', 'Niveles'];

    public function grupo(): string
    {
        return match (true) {
            str_contains(mb_strtolower($this->nombre), 'preparatorio') => 'Preparatorio',
            $this->esAnio() => 'Años',
            default => 'Niveles',
        };
    }

    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }
}
