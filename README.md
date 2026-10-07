# SIGAC — Sistema Integral de Gestión Académica y Control de Asistencia

Conservatorio de Música **"Carlos Afanador Real"** · Ciudad Bolívar.

Aplicación web en **Laravel 12** (PHP 8.2+, MySQL) que reemplaza a SIGAC v7.5 (PHP + archivos JSON).

## Funciones

- **Años escolares** con ciclo *planificación → en curso → cerrado*. Al cerrar un año, sus notas y asistencias quedan como historial de solo lectura.
- **Estudiantes** con estado *activo* (inscrito en un año abierto) o *inactivo*; inscripción anual, retiro del estudiante o de una materia (queda registrada como *retirada*), traslado de cátedra y expediente con todo su historial.
- **Cátedras** por año: asignatura, nivel, sección, profesor, horario y régimen de **1, 2 o 3 lapsos** (trimestres o semestres), definido por Control de Estudios.
- **Notas**: el profesor define las evaluaciones y su porcentaje en cada lapso; nota del lapso ponderada, nota final (promedio de lapsos), definitiva, cierre de lapso y cierre de notas.
- **Progreso de aprobación** por materia en el expediente, con desglose de notas.
- **Promoción**: quien aprueba todas las materias de su año pasa al siguiente; no se puede volver a cursar una materia aprobada. Reinscripción con sugerencias.
- **Asistencia**: pase de lista por fecha (P/A/R/J), cierre de la asistencia del día y reapertura con justificación (Control de Estudios ve el motivo y los cambios), reporte mensual imprimible.
- **Asistencia sin conexión**: archivo HTML descargable para pasar lista sin internet; genera un `.txt` con un código verificado que se sube después.
- Constancia de estudio, historial académico y acta de notas imprimibles; estadísticas; usuarios con roles (*Control de Estudios* y *Profesor*).

## Instalación

Requisitos: PHP 8.2+ (extensiones `pdo_mysql`, `mbstring`, `intl`, `fileinfo`), Composer, Node.js 18+ y MySQL 8.

```bash
git clone https://github.com/albertoq29/sigac.git
cd sigac
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Edite `.env`:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: conexión a MySQL (cree antes la base de datos con `utf8mb4`).
- `SIGAC_ADMIN_USUARIO` y `SIGAC_ADMIN_PASSWORD`: usuario inicial de Control de Estudios.

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Entre con el usuario de Control de Estudios; el sistema pedirá cambiar la contraseña en el primer inicio de sesión.

## Migrar los datos de SIGAC v7.5

El comando lee la carpeta de la app anterior (`data/database.json`, `database.json`, `asistencias_reportadas.json`, `backups/` y `data/backups/`) y crea el año escolar, profesores, cátedras, estudiantes, inscripciones y asistencias:

```bash
php artisan sigac:importar-legacy "C:\ruta\a\la\app\anterior" --fresh
```

> `--fresh` **borra toda la base de datos** antes de importar.

Al terminar muestra un resumen. Los avisos quedan en `storage/logs/importacion-legacy.log` y los usuarios y contraseñas temporales de los profesores en `storage/app/private/credenciales_profesores.csv`. Entréguelos y luego borre ese archivo.

## Pruebas

```bash
php artisan test
```

## Estructura principal

| Carpeta | Contenido |
|---|---|
| `app/Services` | Reglas académicas: cálculo de notas, cierres, inscripciones, reinscripción, asistencia y asistencia sin conexión |
| `app/Support/Legacy` | Importador de los datos de SIGAC v7.5 |
| `app/Http/Controllers` | Pantallas de la aplicación |
| `resources/views` | Vistas Blade (Tailwind CSS 4 + Alpine.js + SweetAlert2) |
| `config/sigac.php` | Valores por defecto (datos institucionales, escala de notas, secciones, horarios) |
