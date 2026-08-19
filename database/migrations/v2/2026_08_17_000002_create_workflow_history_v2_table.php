<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_object_id')
                ->constrained('workflow_objects')
                ->restrictOnDelete();
            $table->string('transition_name');
            $table->json('from_places');
            $table->json('to_places');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('form_submission_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id', 'workflow_history_user_index');
            $table->index('form_submission_id', 'workflow_history_form_submission_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_history');
    }
};
