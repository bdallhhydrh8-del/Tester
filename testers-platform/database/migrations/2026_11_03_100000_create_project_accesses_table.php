<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_accesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tester_id')->constrained('users');
            // Cascade so the developer can remove a project permanently (FR-P6) in one step.
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            // The first link that granted access. Never overwritten.
            $table->foreignId('invitation_id')->nullable()->constrained('invitations')->nullOnDelete();

            $table->enum('status', ['not_started', 'in_progress', 'finished'])->default('not_started');
            $table->timestamp('started_at')->nullable();    // first time the tester opened the project
            $table->timestamp('finished_at')->nullable();   // last card submitted

            // Filled once, when the tester first opens the project.
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device_type')->nullable();

            $table->timestamps();

            // One access per tester and project. Its first column also serves lookups by tester,
            // so a separate (tester_id) index is not needed.
            $table->unique(['tester_id', 'project_id']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_accesses');
    }
};