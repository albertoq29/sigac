<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes institucionales clave/valor (firmas, logos, escala de notas...).
 */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    private const CACHE_KEY = 'sigac.ajustes';

    /** @return array<string, string|null> */
    public static function todos(): array
    {
        $guardados = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('ajustes')) {
                return [];
            }

            return static::query()->pluck('valor', 'clave')->all();
        });

        return array_merge(config('sigac.ajustes', []), $guardados);
    }

    public static function valor(string $clave, mixed $defecto = null): mixed
    {
        return static::todos()[$clave] ?? $defecto;
    }

    /** @param array<string, string|null> $valores */
    public static function guardar(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            static::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** URL de un logo: acepta URLs completas o rutas dentro de /public. */
    public static function urlLogo(string $clave): ?string
    {
        $valor = trim((string) static::valor($clave));

        if ($valor === '') {
            return null;
        }

        return preg_match('#^(https?:)?//#i', $valor) ? $valor : asset(ltrim($valor, '/'));
    }

    public static function notaMaxima(): float
    {
        return (float) static::valor('nota_maxima', 20);
    }

    public static function notaMinimaAprobatoria(): float
    {
        return (float) static::valor('nota_minima_aprobatoria', 10);
    }

    public static function redondearDefinitiva(): bool
    {
        return (bool) (int) static::valor('redondear_definitiva', 1);
    }
}
