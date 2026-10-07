<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reapertura justificada de la asistencia de un día.
 */
class ReaperturaJornada extends Model
{
    protected $table = 'jornada_reaperturas';

    protected $fillable = [
        'jornada_id',
        'reabierta_por',
        'motivo',
        'estados_antes',
        'cambios',
        'recerrada_at',
        'revisada_at',
        'revisada_por',
    ];

    protected function casts(): array
    {
        return [
            'estados_antes' => 'array',
            'cambios' => 'array',
            'recerrada_at' => 'datetime',
            'revisada_at' => 'datetime',
        ];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    public function reabiertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reabierta_por');
    }

    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    /** Sigue abierta: aún no se ha vuelto a cerrar la asistencia. */
    public function estaAbierta(): bool
    {
        return $this->recerrada_at === null;
    }

    public function scopeSinRevisar(Builder $query): Builder
    {
        return $query->whereNull('revisada_at');
    }
}
