<?php

namespace App\Support;

use App\Models\AnioEscolar;

/**
 * Año escolar con el que se está trabajando (seleccionable en la barra superior).
 */
class ContextoAnio
{
    private const CLAVE = 'anio_escolar_id';

    public static function anio(): ?AnioEscolar
    {
        $id = session(self::CLAVE);
        $anio = $id ? AnioEscolar::query()->find($id) : null;

        return $anio ?? AnioEscolar::predeterminado();
    }

    public static function seleccionar(AnioEscolar $anio): void
    {
        session([self::CLAVE => $anio->id]);
    }
}
