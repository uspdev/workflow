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
            $table->json('state')->nullable();  // Current state of the workflow (controlled by application)
            $table->string('workflow_definition_name'); // Name of the definition
            $table->foreign('workflow_definition_name')->references('name')->on('workflow_definitions')->onDelete('cascade');   // The name of the object needs to be on the 'workflow_definition' table, at the 'name' column.


            $table->foreignId('user_id')->constrained()->onDelete('cascade');   // User whom created the object

            $table->timestamps();   // Time of creation / update of the objecct
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
