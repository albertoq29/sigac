<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Control de Estudios designa 1, 2 o 3 lapsos (trimestres o semestres) por cátedra.
        Schema::table('catedras', function (Blueprint $table) {
            $table->unsignedTinyInteger('cantidad_lapsos')->default(2)->after('regimen');
        });

        Schema::table('anios_escolares', function (Blueprint $table) {
            $table->unsignedTinyInteger('lapsos_predeterminados')->default(2)->after('regimen_predeterminado');
        });
    }

    public function down(): void
    {
        Schema::table('catedras', fn (Blueprint $table) => $table->dropColumn('cantidad_lapsos'));
        Schema::table('anios_escolares', fn (Blueprint $table) => $table->dropColumn('lapsos_predeterminados'));
    }
};
