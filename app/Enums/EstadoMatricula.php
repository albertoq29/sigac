<?php

namespace App\Enums;

enum EstadoMatricula: string
{
    case Inscrito = 'inscrito';
    case Retirado = 'retirado';
    case Finalizado = 'finalizado';

    public function label(): string
    {
        return match ($this) {
            self::Inscrito => 'Inscrito',
            self::Retirado => 'Retirado',
            self::Finalizado => 'Año finalizado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Inscrito => 'emerald',
            self::Retirado => 'rose',
            self::Finalizado => 'slate',
        };
    }
}
