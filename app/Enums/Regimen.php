<?php

namespace App\Enums;

enum Regimen: string
{
    case Trimestral = 'trimestral';
    case Semestral = 'semestral';

    public function label(): string
    {
        return match ($this) {
            self::Trimestral => 'Trimestral',
            self::Semestral => 'Semestral',
        };
    }

    public function periodo(): string
    {
        return match ($this) {
            self::Trimestral => 'Trimestre',
            self::Semestral => 'Semestre',
        };
    }

    /** "1 trimestre", "3 semestres" */
    public function descripcion(int $cantidad): string
    {
        $periodo = mb_strtolower($this->periodo());

        return $cantidad.' '.($cantidad === 1 ? $periodo : $periodo.'s');
    }

    /** Nombre del lapso: "I Trimestre", "II Semestre", ... */
    public function nombreLapso(int $numero): string
    {
        $romanos = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

        return ($romanos[$numero] ?? $numero).' '.$this->periodo();
    }
}
