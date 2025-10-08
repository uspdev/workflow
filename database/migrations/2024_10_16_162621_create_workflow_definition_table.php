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
            $table->string('name')->primary();  // 'name' of the definition acts as a primary key
            $table->string('description');      // Description of the workflow definition
            $table->json('definition');         // Definition of the workflow (json format)
            $table->timestamps();                       // **Time that it was created(confirmar isso)
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
