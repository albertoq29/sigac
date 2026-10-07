<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // anio  = año de estudio (Preparatorio, 1er Año...): se promueve aprobando todas sus materias.
        // nivel = nivel independiente (Nivel I, II...): se asigna manualmente.
        Schema::table('niveles', function (Blueprint $table) {
            $table->string('tipo', 10)->default('nivel')->after('orden');
        });

        DB::table('niveles')
            ->whereIn('nombre', ['Preparatorio', '1er Año', '2do Año', '3er Año', '4to Año'])
            ->update(['tipo' => 'anio']);
    }

    public function down(): void
    {
        Schema::table('niveles', fn (Blueprint $table) => $table->dropColumn('tipo'));
    }
};
