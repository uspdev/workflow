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
        Schema::create('workflow_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->onDelete('restrict');
            $table->nullableMorphs('object');
            $table->json('current_places');
            $table->json('variables')->nullable(); //variáveis customizadas
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_objects');
    }
};
