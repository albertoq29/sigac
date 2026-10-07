<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cierre de la asistencia del día.
        Schema::table('jornadas', function (Blueprint $table) {
            $table->timestamp('cerrada_at')->nullable()->after('observacion');
            $table->foreignId('cerrada_por')->nullable()->after('cerrada_at')->constrained('users')->nullOnDelete();
        });

        // Cada reapertura exige un motivo y deja constancia de los cambios realizados.
        Schema::create('jornada_reaperturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_id')->constrained('jornadas')->cascadeOnDelete();
            $table->foreignId('reabierta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo');
            $table->json('estados_antes');          // {inscripcion_id: "P"|"A"|...} al reabrir
            $table->json('cambios')->nullable();    // diferencias registradas al volver a cerrar
            $table->timestamp('recerrada_at')->nullable();
            $table->timestamp('revisada_at')->nullable();
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jornada_reaperturas');
        Schema::table('jornadas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cerrada_por');
            $table->dropColumn('cerrada_at');
        });
    }
};
