<?php

namespace App\Models;

use App\Enums\EstadoMatricula;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro anual del estudiante dentro de un año escolar.
 */
class Matricula extends Model
{
    protected $table = 'matriculas';

    protected $fillable = [
        'estudiante_id',
        'anio_escolar_id',
        'seccion',
        'estado',
        'fecha_inscripcion',
        'fecha_retiro',
        'motivo_retiro',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoMatricula::class,
            'fecha_inscripcion' => 'date',
            'fecha_retiro' => 'date',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    public function estaInscrito(): bool
    {
        return $this->estado === EstadoMatricula::Inscrito;
    }
}
