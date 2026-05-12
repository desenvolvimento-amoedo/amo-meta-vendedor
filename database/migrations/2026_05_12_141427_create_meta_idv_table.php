<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meta_idv', function (Blueprint $table) {
            $table->id();
            $table->integer('CODVENDR');
            $table->integer('CODFILRH');
            $table->integer('ANO');
            $table->integer('MES');
            $table->decimal('META', 15, 2);
            $table->string('CREATED_BY', 150)->nullable();
            $table->string('UPDATED_BY', 150)->nullable();

            $table->timestamps();

            $table->unique(['CODVENDR', 
                            'CODFILRH', 
                                 'ANO', 
                                 'MES'
                            ]);


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_idv');
    }
};
