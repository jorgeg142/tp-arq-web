<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('descripcion')->nullable();
            $table->unsignedInteger('min_puntos');
            $table->unsignedInteger('max_puntos')->nullable();
            $table->text('beneficios')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('clientes') && !Schema::hasColumn('clientes', 'nivel_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->foreignId('nivel_id')->nullable()->constrained('niveles')->nullOnDelete();
            });
        }

        DB::table('niveles')->insert([
            [
                'nombre'       => 'Bronce',
                'slug'         => 'bronce',
                'descripcion'  => 'Entrada al programa',
                'min_puntos'   => 0,
                'max_puntos'   => 999,
                'beneficios'   => "Acumulación estándar de puntos\nAcceso a campañas generales",
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'nombre'       => 'Plata',
                'slug'         => 'plata',
                'descripcion'  => 'Clientes frecuentes',
                'min_puntos'   => 1000,
                'max_puntos'   => 4999,
                'beneficios'   => "Prioridad en atención y envíos\nOfertas exclusivas mensuales",
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'nombre'       => 'Oro',
                'slug'         => 'oro',
                'descripcion'  => 'Clientes premium',
                'min_puntos'   => 5000,
                'max_puntos'   => 9999,
                'beneficios'   => "Bonificación de puntos por compra recurrente\nAcceso anticipado a lanzamientos",
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'nombre'       => 'Platino',
                'slug'         => 'platino',
                'descripcion'  => 'Clientes VIP',
                'min_puntos'   => 10000,
                'max_puntos'   => null,
                'beneficios'   => "Mayor multiplicador de puntos\nBeneficios y experiencias exclusivas",
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'nivel_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('nivel_id');
            });
        }

        Schema::dropIfExists('niveles');
    }
};
