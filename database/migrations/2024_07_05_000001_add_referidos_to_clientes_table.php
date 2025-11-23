<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'codigo_referido')) {
                $table->string('codigo_referido', 16)->nullable()->unique();
            }
            if (!Schema::hasColumn('clientes', 'referido_por_id')) {
                $table->foreignId('referido_por_id')->nullable()->constrained('clientes')->nullOnDelete();
            }
        });

        // Generar códigos únicos para clientes existentes
        $this->generarCodigosParaExistentes();
    }

    public function down(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'referido_por_id')) {
                $table->dropConstrainedForeignId('referido_por_id');
            }
            if (Schema::hasColumn('clientes', 'codigo_referido')) {
                $table->dropColumn('codigo_referido');
            }
        });
    }

    private function generarCodigosParaExistentes(): void
    {
        $clientes = DB::table('clientes')->whereNull('codigo_referido')->select('id')->get();

        foreach ($clientes as $cliente) {
            $codigo = $this->nuevoCodigoUnico();
            DB::table('clientes')->where('id', $cliente->id)->update(['codigo_referido' => $codigo]);
        }
    }

    private function nuevoCodigoUnico(): string
    {
        do {
            $codigo = Str::upper(Str::random(10));
            $exists = DB::table('clientes')->where('codigo_referido', $codigo)->exists();
        } while ($exists);

        return $codigo;
    }
};
