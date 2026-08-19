<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as alterações no banco de dados.
     */
    public function up(): void
    {
        Schema::create('workflow_role_users', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_name');
            $table->string('role_name');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Impede que o mesmo usuário seja cadastrado duas vezes na mesma role do mesmo fluxo.
            $table->unique(['workflow_name', 'role_name', 'user_id'], 'wf_role_user_unique');
        });
    }

    /**
     * Reverte as alterações no banco de dados.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_role_users');
    }
};
