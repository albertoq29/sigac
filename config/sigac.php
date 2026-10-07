<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usuario inicial de Control de Estudios
    |--------------------------------------------------------------------------
    | Se crea al ejecutar "php artisan db:seed". La contraseña debe cambiarse
    | en el primer inicio de sesión.
    */
    'admin' => [
        'usuario' => env('SIGAC_ADMIN_USUARIO', 'control'),
        'password' => env('SIGAC_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Carpeta de la aplicación anterior (archivos JSON)
    |--------------------------------------------------------------------------
    | Usada por "php artisan sigac:importar-legacy". Puede ser relativa a la
    | raíz del proyecto Laravel.
    */
    'legacy_path' => env('SIGAC_LEGACY_PATH', '..'),

    /*
    |--------------------------------------------------------------------------
    | Listas de apoyo para formularios
    |--------------------------------------------------------------------------
    */
    'secciones' => ['A', 'B', 'C', 'D', 'E', 'U'],

    'horarios' => [
        '8:15 am a 9:45 am',
        '9:45 am a 11:15 am',
        '2:00 pm a 3:30 pm',
        '2:00 pm a 4:00 pm',
        '2:00 pm a 4:45 pm',
        '3:30 pm a 4:45 pm',
    ],

    'categorias_asignatura' => [
        'Iniciación Musical Infantil (IMI)',
        'Teóricas',
        'Estudios Complementarios',
        'Instrumentos / Práctica',
    ],

    /*
    |--------------------------------------------------------------------------
    | Valores por defecto de los ajustes institucionales
    |--------------------------------------------------------------------------
    | Se pueden modificar desde la pantalla "Ajustes" (Control de Estudios).
    */
    'ajustes' => [
        'institucion_nombre' => 'CONSERVATORIO DE MÚSICA "CARLOS AFANADOR REAL"',
        'institucion_nombre_texto' => 'Conservatorio de Música "Carlos Afanador Real"',
        'institucion_ubicacion' => 'CIUDAD BOLÍVAR - ESTADO BOLÍVAR',
        'institucion_ciudad' => 'Ciudad Bolívar',
        'institucion_direccion' => 'Parroquia Catedral. Casa Paschen. Calle Igualdad c/c Amor Patrio. Centro Histórico.',
        'institucion_telefono' => '0285-6323230',
        'institucion_correo' => 'cmusicaroficial@gmail.com',
        'director_nombre' => 'Lcdo. Orlando Flores S.',
        'director_resolucion' => 'Resolución Ejecutiva Nro. 010-24 Gaceta Oficial Nro. 1827',
        'control_estudios_nombre' => 'Prof. Pedro Moreno',
        'elaborado_por_nombre' => 'Blanco Migdalis',
        'logo_izquierda' => 'https://lh3.googleusercontent.com/d/1ckbOADwC5tgcE37UETsNCw0_3N_o3sBc',
        'logo_centro' => 'https://lh3.googleusercontent.com/d/1UCckCfC1gisuQdbaxQAdTuMnfeemZ-s_',
        'logo_derecha' => 'https://lh3.googleusercontent.com/d/1GFlAZBh5xJ0x8Aw5PC7ZzXRbw8iaM2lv',
        'nota_maxima' => '20',
        'nota_minima_aprobatoria' => '10',
        'redondear_definitiva' => '1',
    ],
];
