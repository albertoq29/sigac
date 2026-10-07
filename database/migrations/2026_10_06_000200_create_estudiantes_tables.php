<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();
            $table->string('cedula', 20)->unique();
            $table->string('apellidos_nombres', 160)->index();
            $table->date('fecha_nacimiento')->nullable();
            $table->char('sexo', 1)->nullable();
            $table->string('telefono', 80)->nullable();
            $table->string('correo', 120)->nullable();
            // activo = inscrito en un año escolar no cerrado; inactivo = no inscrito.
            $table->string('estado', 20)->default('inactivo')->index();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // Registro anual del estudiante (una matrícula por año escolar).
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('anio_escolar_id')->constrained('anios_escolares')->cascadeOnDelete();
            $table->string('seccion', 40)->nullable();
            $table->string('estado', 20)->default('inscrito')->index();
            $table->date('fecha_inscripcion')->nullable();
            $table->date('fecha_retiro')->nullable();
            $table->text('motivo_retiro')->nullable();
            $table->timestamps();

            $table->unique(['estudiante_id', 'anio_escolar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
        Schema::dropIfExists('estudiantes');
    }
};
