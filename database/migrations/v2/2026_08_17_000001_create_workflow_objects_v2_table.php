<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_objects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')
                ->constrained('workflow_definitions')
                ->restrictOnDelete();
            $table->string('object_type');
            $table->string('object_id');
            $table->json('current_places');
            $table->json('variables')->nullable();
            $table->timestamps();

            $table->index(['object_type', 'object_id'], 'workflow_objects_object_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_objects');
    }
};
