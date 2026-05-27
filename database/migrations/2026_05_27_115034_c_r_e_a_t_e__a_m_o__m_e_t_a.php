<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('AMO_META', function (Blueprint $table) {
        $table->id('ID'); // Cria o BIGINT IDENTITY(1,1) PRIMARY KEY
        
        $table->integer('CODVENDR')->nullable(false);
        $table->integer('CODFIL')->nullable(false);
        $table->integer('ANO')->nullable(false);
        $table->integer('MES')->nullable(false);
        $table->decimal('META', 18, 2)->nullable(false);
        
        // Campo de auditoria com o padrão do SQL Server
        $table->dateTime('DATA_CADASTRO')->default(DB::raw('GETDATE()'));

        // Garante que não haverá duplicidade de meta para o mesmo vendedor no mesmo mês/ano
        $table->unique(['CODVENDR', 'ANO', 'MES'], 'UK_META_VENDEDOR');
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('AMO_META');
    }
};
