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
        Schema::create('workflow_role_emails', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_name');
            $table->string('role_name');
            $table->string('email');
            $table->timestamps();
            $table->unique(['workflow_name', 'role_name', 'email'], 'wf_role_email_unique');
        });
    }

    /**
     * Reverte as alterações no banco de dados.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_role_emails');
    }
};
