<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pase de lista de una cátedra en una fecha.
 */
class Jornada extends Model
{
    protected $table = 'jornadas';

    protected $fillable = ['catedra_id', 'fecha', 'registrado_por', 'observacion', 'cerrada_at', 'cerrada_por'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cerrada_at' => 'datetime',
        ];
    }

    public function catedra(): BelongsTo
    {
        return $this->belongsTo(Catedra::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function reaperturas(): HasMany
    {
        return $this->hasMany(ReaperturaJornada::class)->latest();
    }

    public function estaCerrada(): bool
    {
        return $this->cerrada_at !== null;
    }

    /** Reapertura en curso (aún no se ha vuelto a cerrar). */
    public function reaperturaAbierta(): ?ReaperturaJornada
    {
        return $this->reaperturas()->whereNull('recerrada_at')->first();
    }

    /** @return array<int, string> estado actual por inscripcion_id */
    public function estadosActuales(): array
    {
        return $this->asistencias()->get()
            ->mapWithKeys(fn (Asistencia $a) => [$a->inscripcion_id => $a->estado->value])
            ->all();
    }
}
