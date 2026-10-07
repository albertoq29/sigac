<?php

namespace App\Models;

use App\Enums\EstadoInscripcion;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estudiante cursando una cátedra (dentro de su matrícula anual).
 */
class Inscripcion extends Model
{
    protected $table = 'inscripciones';

    protected $fillable = [
        'matricula_id',
        'estudiante_id',
        'catedra_id',
        'horario',
        'estado',
        'nota_final',
        'nota_definitiva',
        'fecha_retiro',
        'motivo_retiro',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoInscripcion::class,
            'nota_final' => 'decimal:2',
            'nota_definitiva' => 'decimal:2',
            'fecha_retiro' => 'date',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function catedra(): BelongsTo
    {
        return $this->belongsTo(Catedra::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /** Horario propio del estudiante o, si no tiene, el de la cátedra. */
    protected function horarioEfectivo(): Attribute
    {
        return Attribute::get(fn () => $this->horario ?: $this->catedra?->horario);
    }

    public function estaCursando(): bool
    {
        return $this->estado === EstadoInscripcion::Cursando;
    }

    public function estaRetirada(): bool
    {
        return $this->estado === EstadoInscripcion::Retirada;
    }
}
