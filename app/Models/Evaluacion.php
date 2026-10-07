<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = ['lapso_id', 'nombre', 'peso', 'fecha', 'orden'];

    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function lapso(): BelongsTo
    {
        return $this->belongsTo(Lapso::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }
}
