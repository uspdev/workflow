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
        Schema::create('workflow_objects', function (Blueprint $table) { // workflows
            $table->id();
            $table->json('state')->nullable();          // Estado atual do workflows (controlado pela aplicação)
            $table->string('workflow_definition_name'); // Nome da definição referente ao objeto
            $table->foreign('workflow_definition_name')->references('name')->on('workflow_definitions')->onDelete('cascade');   // Nome do objeto de workflow deve estar na tabela 'workflow_definitions', na coluna 'name'


            $table->foreignId('user_id')->constrained()->onDelete('cascade');   // Usuário que criou o objeto

            $table->timestamps();   // Tempo de criação / atualização do objeto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
