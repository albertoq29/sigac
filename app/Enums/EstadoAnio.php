<?php

namespace App\Enums;

enum EstadoAnio: string
{
    case Planificacion = 'planificacion';
    case EnCurso = 'en_curso';
    case Cerrado = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::Planificacion => 'En planificación',
            self::EnCurso => 'En curso',
            self::Cerrado => 'Cerrado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planificacion => 'sky',
            self::EnCurso => 'emerald',
            self::Cerrado => 'slate',
        };
    }
}
