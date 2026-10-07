<?php

use App\Http\Controllers\AjusteController;
use App\Http\Controllers\AnioEscolarController;
use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AsistenciaOfflineController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatedraController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\EvaluacionController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\MatriculaController;
use App\Http\Controllers\NivelController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\ReaperturaController;
use App\Http\Controllers\ReinscripcionController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'habilitado'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/cuenta/contrasena', [CuentaController::class, 'edit'])->name('cuenta.password');
    Route::put('/cuenta/contrasena', [CuentaController::class, 'update'])->name('cuenta.password.update');

    Route::get('/', InicioController::class)->name('inicio');
    Route::post('/anio-de-trabajo', [AnioEscolarController::class, 'seleccionar'])->name('anio.seleccionar');

    // Cátedras: Control de Estudios ve todas; cada profesor, las suyas.
    Route::get('/catedras', [CatedraController::class, 'index'])->name('catedras.index');
    Route::get('/catedras/{catedra}', [CatedraController::class, 'show'])->name('catedras.show')->whereNumber('catedra');

    // Asistencia
    Route::get('/catedras/{catedra}/asistencia', [AsistenciaController::class, 'create'])->name('asistencia.create');
    Route::post('/catedras/{catedra}/asistencia', [AsistenciaController::class, 'store'])->name('asistencia.store');
    Route::get('/catedras/{catedra}/asistencia/mensual', [AsistenciaController::class, 'mensual'])->name('asistencia.mensual');
    Route::post('/jornadas/{jornada}/cerrar', [AsistenciaController::class, 'cerrar'])->name('jornadas.cerrar');

    // Asistencia sin conexión (archivo descargable + carga del código .txt)
    Route::get('/asistencia/sin-conexion', [AsistenciaOfflineController::class, 'index'])->name('offline.index');
    Route::get('/asistencia/sin-conexion/descargar', [AsistenciaOfflineController::class, 'descargar'])->name('offline.descargar');
    Route::post('/asistencia/sin-conexion/revisar', [AsistenciaOfflineController::class, 'revisar'])->name('offline.revisar');
    Route::post('/asistencia/sin-conexion/importar', [AsistenciaOfflineController::class, 'importar'])->name('offline.importar');
    Route::post('/jornadas/{jornada}/reabrir', [AsistenciaController::class, 'reabrir'])->name('jornadas.reabrir');

    // Notas
    Route::get('/catedras/{catedra}/notas', [NotaController::class, 'index'])->name('notas.index');
    Route::get('/catedras/{catedra}/notas/lapso/{numero}', [NotaController::class, 'lapso'])->name('notas.lapso')->whereNumber('numero');
    Route::put('/catedras/{catedra}/notas/lapso/{numero}', [NotaController::class, 'guardar'])->name('notas.guardar')->whereNumber('numero');
    Route::post('/catedras/{catedra}/notas/lapso/{numero}/cerrar', [NotaController::class, 'cerrarLapso'])->name('notas.cerrar-lapso')->whereNumber('numero');
    Route::post('/catedras/{catedra}/notas/cierre', [NotaController::class, 'cerrarCatedra'])->name('notas.cerrar');
    Route::get('/catedras/{catedra}/acta', [DocumentoController::class, 'acta'])->name('documentos.acta');

    Route::post('/catedras/{catedra}/notas/lapso/{numero}/evaluaciones', [EvaluacionController::class, 'store'])->name('evaluaciones.store')->whereNumber('numero');
    Route::put('/evaluaciones/{evaluacion}', [EvaluacionController::class, 'update'])->name('evaluaciones.update');
    Route::delete('/evaluaciones/{evaluacion}', [EvaluacionController::class, 'destroy'])->name('evaluaciones.destroy');

    // ---------------------------------------------------------------
    // Control de Estudios
    // ---------------------------------------------------------------
    Route::middleware('rol:control')->group(function () {
        // Estudiantes y su expediente
        Route::resource('estudiantes', EstudianteController::class);
        Route::get('/estudiantes/{estudiante}/constancia', [DocumentoController::class, 'constancia'])->name('documentos.constancia');
        Route::get('/estudiantes/{estudiante}/historial', [DocumentoController::class, 'historial'])->name('documentos.historial');

        // Inscripción anual, retiro y reincorporación
        Route::get('/estudiantes/{estudiante}/inscribir', [MatriculaController::class, 'create'])->name('matriculas.create');
        Route::post('/estudiantes/{estudiante}/inscribir', [MatriculaController::class, 'store'])->name('matriculas.store');
        Route::put('/matriculas/{matricula}', [MatriculaController::class, 'update'])->name('matriculas.update');
        Route::post('/matriculas/{matricula}/retirar', [MatriculaController::class, 'retirar'])->name('matriculas.retirar');
        Route::post('/matriculas/{matricula}/reincorporar', [MatriculaController::class, 'reincorporar'])->name('matriculas.reincorporar');
        Route::post('/matriculas/{matricula}/catedras', [InscripcionController::class, 'store'])->name('inscripciones.store');

        // Materias (inscripciones en cátedras)
        Route::put('/inscripciones/{inscripcion}', [InscripcionController::class, 'update'])->name('inscripciones.update');
        Route::post('/inscripciones/{inscripcion}/retirar', [InscripcionController::class, 'retirar'])->name('inscripciones.retirar');
        Route::post('/inscripciones/{inscripcion}/reincorporar', [InscripcionController::class, 'reincorporar'])->name('inscripciones.reincorporar');
        Route::post('/inscripciones/{inscripcion}/trasladar', [InscripcionController::class, 'trasladar'])->name('inscripciones.trasladar');
        Route::delete('/inscripciones/{inscripcion}', [InscripcionController::class, 'destroy'])->name('inscripciones.destroy');

        // Cátedras
        Route::get('/catedras/crear', [CatedraController::class, 'create'])->name('catedras.create');
        Route::post('/catedras', [CatedraController::class, 'store'])->name('catedras.store');
        Route::get('/catedras/{catedra}/editar', [CatedraController::class, 'edit'])->name('catedras.edit');
        Route::put('/catedras/{catedra}', [CatedraController::class, 'update'])->name('catedras.update');
        Route::delete('/catedras/{catedra}', [CatedraController::class, 'destroy'])->name('catedras.destroy');
        Route::get('/catedras/{catedra}/estudiantes', [CatedraController::class, 'estudiantes'])->name('catedras.estudiantes');
        Route::post('/catedras/{catedra}/estudiantes', [CatedraController::class, 'agregarEstudiantes'])->name('catedras.agregar-estudiantes');
        Route::post('/catedras/{catedra}/notas/lapso/{numero}/reabrir', [NotaController::class, 'reabrirLapso'])->name('notas.reabrir-lapso')->whereNumber('numero');
        Route::delete('/catedras/{catedra}/notas/cierre', [NotaController::class, 'reabrirCatedra'])->name('notas.reabrir');
        Route::delete('/jornadas/{jornada}', [AsistenciaController::class, 'destroy'])->name('jornadas.destroy');
        Route::get('/asistencia/reaperturas', [ReaperturaController::class, 'index'])->name('reaperturas.index');
        Route::post('/asistencia/reaperturas/{reapertura}/revisar', [ReaperturaController::class, 'revisar'])->name('reaperturas.revisar');

        // Años escolares: apertura, cierre y reinscripción
        Route::get('/anios-escolares', [AnioEscolarController::class, 'index'])->name('anios.index');
        Route::get('/anios-escolares/crear', [AnioEscolarController::class, 'create'])->name('anios.create');
        Route::post('/anios-escolares', [AnioEscolarController::class, 'store'])->name('anios.store');
        Route::get('/anios-escolares/{anio}', [AnioEscolarController::class, 'show'])->name('anios.show');
        Route::get('/anios-escolares/{anio}/editar', [AnioEscolarController::class, 'edit'])->name('anios.edit');
        Route::put('/anios-escolares/{anio}', [AnioEscolarController::class, 'update'])->name('anios.update');
        Route::post('/anios-escolares/{anio}/iniciar', [AnioEscolarController::class, 'iniciar'])->name('anios.iniciar');
        Route::post('/anios-escolares/{anio}/cerrar', [AnioEscolarController::class, 'cerrar'])->name('anios.cerrar');
        Route::post('/anios-escolares/{anio}/copiar-catedras', [AnioEscolarController::class, 'copiarCatedras'])->name('anios.copiar-catedras');
        Route::get('/anios-escolares/{anio}/reinscripcion', [ReinscripcionController::class, 'index'])->name('reinscripcion.index');
        Route::post('/anios-escolares/{anio}/reinscripcion', [ReinscripcionController::class, 'store'])->name('reinscripcion.store');

        // Usuarios (profesores y Control de Estudios) y catálogos
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'usuario']);
        Route::post('/usuarios/{usuario}/restablecer', [UsuarioController::class, 'restablecer'])->name('usuarios.restablecer');
        Route::resource('asignaturas', AsignaturaController::class)->except(['show', 'create', 'edit']);
        Route::resource('niveles', NivelController::class)->except(['show', 'create', 'edit'])
            ->parameters(['niveles' => 'nivel']);

        Route::get('/estadisticas', EstadisticaController::class)->name('estadisticas');
        Route::get('/ajustes', [AjusteController::class, 'edit'])->name('ajustes.edit');
        Route::put('/ajustes', [AjusteController::class, 'update'])->name('ajustes.update');
    });
});
