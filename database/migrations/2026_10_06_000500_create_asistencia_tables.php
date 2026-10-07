<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jornada = un pase de lista de una cátedra en una fecha.
        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catedra_id')->constrained('catedras')->cascadeOnDelete();
            $table->date('fecha');
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->unique(['catedra_id', 'fecha']);
            $table->index('fecha');
        });

        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_id')->constrained('jornadas')->cascadeOnDelete();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->cascadeOnDelete();
            // P = presente, A = ausente, R = retraso, J = inasistencia justificada
            $table->char('estado', 1);
            $table->timestamps();

            $table->unique(['jornada_id', 'inscripcion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('jornadas');
    }
};
