<?php

namespace App\Enums;

enum EstadoInscripcion: string
{
    case Cursando = 'cursando';
    case Aprobada = 'aprobada';
    case Reprobada = 'reprobada';
    case Retirada = 'retirada';
    case SinCalificar = 'sin_calificar';

    public function label(): string
    {
        return match ($this) {
            self::Cursando => 'Cursando',
            self::Aprobada => 'Aprobada',
            self::Reprobada => 'Reprobada',
            self::Retirada => 'Retirada',
            self::SinCalificar => 'Sin calificar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cursando => 'sky',
            self::Aprobada => 'emerald',
            self::Reprobada => 'rose',
            self::Retirada => 'amber',
            self::SinCalificar => 'slate',
        };
    }
}
