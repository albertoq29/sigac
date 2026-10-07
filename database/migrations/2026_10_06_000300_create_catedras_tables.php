<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catedras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anio_escolar_id')->constrained('anios_escolares')->cascadeOnDelete();
            $table->foreignId('asignatura_id')->constrained('asignaturas')->restrictOnDelete();
            $table->foreignId('nivel_id')->constrained('niveles')->restrictOnDelete();
            $table->string('seccion', 10)->default('U');
            $table->foreignId('profesor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('horario', 80)->nullable();
            // trimestral | semestral (lo designa Control de Estudios)
            $table->string('regimen', 20)->default('trimestral');
            $table->timestamp('notas_cerradas_at')->nullable();
            $table->foreignId('notas_cerradas_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['anio_escolar_id', 'asignatura_id', 'nivel_id', 'seccion']);
        });

        // Lapsos de evaluación (2 trimestres o 2 semestres por cátedra).
        Schema::create('lapsos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catedra_id')->constrained('catedras')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['catedra_id', 'numero']);
        });

        // Evaluaciones definidas por el profesor: cantidad y porcentaje libres (suman 100%).
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lapso_id')->constrained('lapsos')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->decimal('peso', 5, 2);
            $table->date('fecha')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
        Schema::dropIfExists('lapsos');
        Schema::dropIfExists('catedras');
    }
};
