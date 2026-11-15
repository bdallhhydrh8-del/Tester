<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
  One row per attempt per task. The duration is calculated by the server,
  from the Start task press to the Yes or No answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_access_id')->constrained('project_accesses')->cascadeOnDelete();
            // Cards are only soft-deleted, so this is normally never null.
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();

            // The card as the tester saw it: text, enabled questions, required flag, position.
            // Editing the card later never changes it.
            $table->json('card_snapshot')->nullable();

  // Server times only. The duration is calculated by the server, from the Start task press to the Yes or No answer.            $table->timestamp('task_started_at')->nullable();
            $table->timestamp('task_ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // Answers. Empty until given.
            $table->boolean('success')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();    // 1 to 5. Renamed to "ease" (5 is easiest) by 2026_11_10_085820.

            $table->text('notes')->nullable();

            // Empty means the card is still in progress.
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->unique(['project_access_id', 'card_id']);
            $table->index('card_id');   // per-card statistics
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};