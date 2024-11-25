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
            $table->string('state')->nullable();
            $table->string('workflow_definition_name');
            $table->foreign('workflow_definition_name')->references('name')->on('workflow_definitions')->onDelete('cascade');


            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamps();
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
