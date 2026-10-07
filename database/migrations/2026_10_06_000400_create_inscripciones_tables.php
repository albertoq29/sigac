<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Estudiante cursando una cátedra dentro de su matrícula anual.
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matricula_id')->constrained('matriculas')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('catedra_id')->constrained('catedras')->cascadeOnDelete();
            $table->string('horario', 80)->nullable();
            // cursando | aprobada | reprobada | retirada | sin_calificar
            $table->string('estado', 20)->default('cursando')->index();
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->decimal('nota_definitiva', 5, 2)->nullable();
            $table->date('fecha_retiro')->nullable();
            $table->string('motivo_retiro', 255)->nullable();
            $table->timestamps();

            $table->unique(['catedra_id', 'estudiante_id']);
        });

        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('evaluaciones')->cascadeOnDelete();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            $table->decimal('valor', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['evaluacion_id', 'inscripcion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas');
        Schema::dropIfExists('inscripciones');
    }
};
