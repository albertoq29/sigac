<?php

namespace App\Console\Commands;

use App\Models\Estudiante;
use App\Support\Legacy\ImportadorLegacy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportarLegacy extends Command
{
    protected $signature = 'sigac:importar-legacy
                            {ruta? : Carpeta de la app anterior (por defecto SIGAC_LEGACY_PATH)}
                            {--anio=2025-2026 : Año escolar al que pertenecen los datos}
                            {--fresh : Borra TODA la base de datos y la recrea antes de importar}';

    protected $description = 'Importa estudiantes, cátedras, docentes y asistencias de SIGAC v7.5 (archivos JSON) a MySQL';

    public function handle(): int
    {
        $ruta = $this->argument('ruta') ?: config('sigac.legacy_path');
        $ruta = $this->esAbsoluta($ruta) ? $ruta : base_path($ruta);
        $ruta = realpath($ruta) ?: $ruta;

        if (! is_dir($ruta)) {
            $this->error("No existe la carpeta {$ruta}");

            return self::FAILURE;
        }

        $this->info("Importando desde: {$ruta}");

        if ($this->option('fresh')) {
            if (! $this->confirm('Se borrarán TODOS los datos actuales de la base de datos. ¿Continuar?', ! $this->input->isInteractive())) {
                return self::FAILURE;
            }
            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        } elseif (Estudiante::query()->exists()) {
            $this->error('La base de datos ya tiene estudiantes. Use --fresh para reiniciarla e importar desde cero.');

            return self::FAILURE;
        }

        $resultado = (new ImportadorLegacy($ruta, $this->option('anio')))->ejecutar();

        $this->newLine();
        $this->table(['Concepto', 'Cantidad'], collect($resultado['estadisticas'])
            ->map(fn ($valor, $clave) => [str_replace('_', ' ', ucfirst($clave)), $valor])
            ->values()
            ->all());

        $csv = "nombre;usuario;contraseña_temporal\n".collect($resultado['credenciales'])
            ->map(fn ($c) => "{$c['nombre']};{$c['usuario']};{$c['password']}")
            ->implode("\n")."\n";
        Storage::disk('local')->put('credenciales_profesores.csv', "\xEF\xBB\xBF".$csv);

        $log = storage_path('logs/importacion-legacy.log');
        file_put_contents($log, implode(PHP_EOL, $resultado['avisos']).PHP_EOL);

        $this->newLine();
        $this->line(count($resultado['avisos']).' avisos de la importación en: '.$log);
        $this->line('Usuarios y contraseñas temporales de los profesores en: '.Storage::disk('local')->path('credenciales_profesores.csv'));
        $this->warn('Entregue a cada profesor su usuario y contraseña temporal y luego elimine ese archivo.');

        return self::SUCCESS;
    }

    private function esAbsoluta(string $ruta): bool
    {
        return str_starts_with($ruta, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta) === 1;
    }
}
