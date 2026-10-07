<?php

namespace App\Models;

use App\Enums\EstadoAnio;
use App\Enums\EstadoEstudiante;
use App\Enums\EstadoMatricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    protected $table = 'estudiantes';

    protected $fillable = [
        'cedula',
        'apellidos_nombres',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'correo',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'estado' => EstadoEstudiante::class,
        ];
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    protected function edad(): Attribute
    {
        return Attribute::get(fn () => $this->fecha_nacimiento?->age);
    }

    protected function sexoTexto(): Attribute
    {
        return Attribute::get(fn () => match ($this->sexo) {
            'F' => 'Femenino',
            'M' => 'Masculino',
            default => '—',
        });
    }

    public function matriculaEn(AnioEscolar|int $anio): ?Matricula
    {
        $anioId = $anio instanceof AnioEscolar ? $anio->id : $anio;

        return $this->matriculas->firstWhere('anio_escolar_id', $anioId)
            ?? $this->matriculas()->where('anio_escolar_id', $anioId)->first();
    }

    /** Matrícula vigente: inscrito en un año escolar que no está cerrado. */
    public function matriculaVigente(): ?Matricula
    {
        return $this->matriculas()
            ->where('estado', EstadoMatricula::Inscrito->value)
            ->whereHas('anioEscolar', fn ($q) => $q->where('estado', '!=', EstadoAnio::Cerrado->value))
            ->with('anioEscolar')
            ->get()
            ->sortByDesc(fn (Matricula $m) => $m->anioEscolar->nombre)
            ->first();
    }

    /**
     * Activo = inscrito en un año escolar no cerrado. Se recalcula tras cada
     * inscripción, retiro o cierre de año.
     */
    public function sincronizarEstado(): void
    {
        $inscrito = $this->matriculas()
            ->where('estado', EstadoMatricula::Inscrito->value)
            ->whereHas('anioEscolar', fn ($q) => $q->where('estado', '!=', EstadoAnio::Cerrado->value))
            ->exists();

        $estado = $inscrito ? EstadoEstudiante::Activo : EstadoEstudiante::Inactivo;

        if ($this->estado !== $estado) {
            $this->forceFill(['estado' => $estado])->save();
        }
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoEstudiante::Activo->value);
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('cedula', 'like', "%{$termino}%")
                ->orWhere('apellidos_nombres', 'like', '%'.str_replace(' ', '%', $termino).'%');
        });
    }
}
