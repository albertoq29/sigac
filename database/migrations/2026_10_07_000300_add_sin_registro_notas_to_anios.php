<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Años sin registro de notas (p. ej. el importado de SIGAC v7.5): no cuentan
        // para la promoción y sus materias se muestran como "cursadas".
        Schema::table('anios_escolares', function (Blueprint $table) {
            $table->boolean('sin_registro_notas')->default(false)->after('lapsos_predeterminados');
        });

        DB::table('anios_escolares')
            ->where('observaciones', 'like', 'Año escolar importado desde SIGAC%')
            ->update(['sin_registro_notas' => true]);
    }

    public function down(): void
    {
        Schema::table('anios_escolares', fn (Blueprint $table) => $table->dropColumn('sin_registro_notas'));
    }
};
