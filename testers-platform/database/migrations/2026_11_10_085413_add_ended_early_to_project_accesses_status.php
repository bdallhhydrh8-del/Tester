<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_accesses', function (Blueprint $table) {
            $table->enum('status', ['not_started', 'in_progress', 'finished', 'ended_early'])
                ->default('not_started')->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_accesses', function (Blueprint $table) {
            $table->enum('status', ['not_started', 'in_progress', 'finished'])
                ->default('not_started')->change();
        });
    }
};
