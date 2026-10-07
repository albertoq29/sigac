<?php

namespace App\Enums;

enum Rol: string
{
    case Control = 'control';
    case Profesor = 'profesor';

    public function label(): string
    {
        return match ($this) {
            self::Control => 'Control de Estudios',
            self::Profesor => 'Profesor',
        };
    }
}
