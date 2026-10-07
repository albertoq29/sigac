<?php

if (! function_exists('formato_nota')) {
    /** 15.00 → "15"; 14.50 → "14,5"; null → "—" */
    function formato_nota(mixed $valor, string $vacio = '—'): string
    {
        if ($valor === null || $valor === '') {
            return $vacio;
        }

        $texto = number_format((float) $valor, 2, ',', '');

        return rtrim(rtrim($texto, '0'), ',');
    }
}
