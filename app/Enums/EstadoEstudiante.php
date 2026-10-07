<?php

namespace App\Enums;

enum EstadoEstudiante: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Activo => 'emerald',
            self::Inactivo => 'slate',
        };
    }
}
