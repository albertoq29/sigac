<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trimestre o semestre de una cátedra.
 */
class Lapso extends Model
{
    protected $table = 'lapsos';

    protected $fillable = ['catedra_id', 'numero', 'cerrado_at', 'cerrado_por'];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'cerrado_at' => 'datetime',
        ];
    }

    public function catedra(): BelongsTo
    {
        return $this->belongsTo(Catedra::class);
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class)->orderBy('orden')->orderBy('id');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    protected function nombre(): Attribute
    {
        return Attribute::get(fn () => $this->catedra->regimen->nombreLapso($this->numero));
    }

    public function estaCerrado(): bool
    {
        return $this->cerrado_at !== null;
    }

    public function pesoTotal(): float
    {
        return round((float) $this->evaluaciones->sum('peso'), 2);
    }
}
