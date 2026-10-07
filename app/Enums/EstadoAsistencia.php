<?php

namespace App\Enums;

enum EstadoAsistencia: string
{
    case Presente = 'P';
    case Ausente = 'A';
    case Retraso = 'R';
    case Justificada = 'J';

    public function label(): string
    {
        return match ($this) {
            self::Presente => 'Presente',
            self::Ausente => 'Ausente',
            self::Retraso => 'Retraso',
            self::Justificada => 'Justificada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Presente => 'emerald',
            self::Ausente => 'rose',
            self::Retraso => 'amber',
            self::Justificada => 'sky',
        };
    }

    /** Solo la ausencia sin justificar cuenta como inasistencia. */
    public function esInasistencia(): bool
    {
        return $this === self::Ausente;
    }

    /** Convierte los valores usados por la app anterior ("Presente", "P", ...). */
    public static function desdeTexto(?string $texto): ?self
    {
        $t = mb_strtoupper(trim((string) $texto));

        return match (true) {
            $t === '' => null,
            str_starts_with($t, 'P') => self::Presente,
            str_starts_with($t, 'A') => self::Ausente,
            str_starts_with($t, 'R') => self::Retraso,
            str_starts_with($t, 'J') => self::Justificada,
            default => null,
        };
    }
}
