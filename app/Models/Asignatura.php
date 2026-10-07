<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asignatura extends Model
{
    protected $table = 'asignaturas';

    protected $fillable = ['nombre', 'categoria', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function catedras(): HasMany
    {
        return $this->hasMany(Catedra::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
