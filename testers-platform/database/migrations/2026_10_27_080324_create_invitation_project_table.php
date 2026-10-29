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
        Schema::create('invitation_project', function (Blueprint $table) {
    $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('project_id');
    $table->primary(['invitation_id', 'project_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_project');
    }
};
