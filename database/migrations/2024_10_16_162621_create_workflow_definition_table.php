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
        Schema::create('workflow_definitions', function (Blueprint $table) { 
            $table->string('name')->primary();  // Nome da definição; Atua como chave primária
            $table->string('description');      // Descrição da definição
            $table->json('definition');         // Definição do worklfow (formato .json)
            $table->timestamps();               // Momento em que foi criado e/ou atualizado
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_definition');
    }
};
