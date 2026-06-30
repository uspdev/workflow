<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a migração para criar a tabela workflow_history.
     */
    public function up(): void
    {
        Schema::create('workflow_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_object_id')->constrained()->onDelete('cascade');
            $table->string('transition'); // nome da transiçao que gerou o histórico
            $table->string('from_place'); // Estado de origem
            $table->string('to_places'); // Estado(s) de destino
            $table->foreignId('user_id')->nullable()->constrained('users'); // Usuário que realizou a ação
            $table->unsignedBigInteger('form_submission_id')->nullable(); // ID da submissão do formulário (opcional)
            $table->json('metadata')->nullable(); // Metadados adicionais em formato JSON
            $table->timestamps();
        });
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_history');
    }
};
