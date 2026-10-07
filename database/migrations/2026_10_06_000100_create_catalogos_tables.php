<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 80)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        Schema::create('asignaturas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('categoria', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('niveles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->foreignId('siguiente_nivel_id')->nullable()->constrained('niveles')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('anios_escolares', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 20)->unique();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('planificacion')->index();
            $table->string('regimen_predeterminado', 20)->default('trimestral');
            $table->text('observaciones')->nullable();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anios_escolares');
        Schema::dropIfExists('niveles');
        Schema::dropIfExists('asignaturas');
        Schema::dropIfExists('ajustes');
    }
};
