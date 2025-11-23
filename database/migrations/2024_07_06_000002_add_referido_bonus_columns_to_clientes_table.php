<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'puntos_por_referir')) {
                $table->integer('puntos_por_referir')->nullable();
            }
            if (!Schema::hasColumn('clientes', 'puntos_bienvenida')) {
                $table->integer('puntos_bienvenida')->nullable();
            }
        });

        $referrer   = config('loyalty.referrer_bonus_points', config('loyalty.referral_bonus_points', 0));
        $newCustomer = config('loyalty.new_customer_bonus_points', config('loyalty.referral_bonus_points', 0));

        DB::table('clientes')
            ->whereNull('puntos_por_referir')
            ->update(['puntos_por_referir' => $referrer]);

        DB::table('clientes')
            ->whereNull('puntos_bienvenida')
            ->update(['puntos_bienvenida' => $newCustomer]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'puntos_por_referir')) {
                $table->dropColumn('puntos_por_referir');
            }
            if (Schema::hasColumn('clientes', 'puntos_bienvenida')) {
                $table->dropColumn('puntos_bienvenida');
            }
        });
    }
};
