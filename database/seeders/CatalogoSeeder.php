<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use App\Models\Nivel;
use Illuminate\Database\Seeder;

/**
 * Asignaturas y niveles que usaba SIGAC v7.5.
 */
class CatalogoSeeder extends Seeder
{
    public const ASIGNATURAS = [
        'Iniciación Musical Infantil (IMI)' => [
            'IMI - Arte, Sonoridad y Movimiento',
            'IMI - Lenguaje Musical',
            'IMI - Práctica Coral',
        ],
        'Teóricas' => [
            'Lenguaje Musical',
            'Historia de la Música',
            'Armonía',
        ],
        'Estudios Complementarios' => [
            'Est. Comp - Piano',
            'Est. Comp - Cuatro',
            'Est. Comp - Guitarra',
            'Est. Comp - Canto',
        ],
        'Instrumentos / Práctica' => [
            'Piano', 'Cuatro', 'Violín', 'Guitarra', 'Canto', 'Práctica Coral', 'Bandola',
            'Trompeta', 'Saxofón', 'Contrabajo', 'Percusión', 'Mandolina', 'Viola',
            'Flauta Dulce', 'Flauta Transversa',
        ],
    ];

    /** nombre => [orden, siguiente al aprobar, tipo (anio = año de estudio, nivel = nivel independiente)] */
    public const NIVELES = [
        'Preparatorio' => [0, '1er Año', 'anio'],
        '1er Año' => [1, '2do Año', 'anio'],
        '2do Año' => [2, '3er Año', 'anio'],
        '3er Año' => [3, '4to Año', 'anio'],
        '4to Año' => [4, null, 'anio'],
        'Nivel I' => [5, 'Nivel II', 'nivel'],
        'Nivel II' => [6, 'Nivel III', 'nivel'],
        'Nivel III' => [7, null, 'nivel'],
    ];

    public function run(): void
    {
        foreach (self::ASIGNATURAS as $categoria => $nombres) {
            foreach ($nombres as $nombre) {
                Asignatura::query()->firstOrCreate(['nombre' => $nombre], ['categoria' => $categoria]);
            }
        }

        foreach (self::NIVELES as $nombre => [$orden, , $tipo]) {
            Nivel::query()->firstOrCreate(['nombre' => $nombre], ['orden' => $orden, 'tipo' => $tipo]);
        }

        foreach (self::NIVELES as $nombre => [, $siguiente]) {
            if ($siguiente === null) {
                continue;
            }

            $nivel = Nivel::query()->where('nombre', $nombre)->first();
            if ($nivel && $nivel->siguiente_nivel_id === null) {
                $nivel->update(['siguiente_nivel_id' => Nivel::query()->where('nombre', $siguiente)->value('id')]);
            }
        }
    }

    public static function categoriaPara(string $asignatura): string
    {
        foreach (self::ASIGNATURAS as $categoria => $nombres) {
            if (in_array($asignatura, $nombres, true)) {
                return $categoria;
            }
        }

        return match (true) {
            str_starts_with($asignatura, 'IMI') => 'Iniciación Musical Infantil (IMI)',
            str_starts_with($asignatura, 'Est. Comp') => 'Estudios Complementarios',
            default => 'Instrumentos / Práctica',
        };
    }
}
