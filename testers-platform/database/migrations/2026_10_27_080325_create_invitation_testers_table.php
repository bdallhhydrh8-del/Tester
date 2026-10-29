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
      Schema::create('invitation_testers', function (Blueprint $table) {
    $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tester_id')->constrained('users');
    $table->timestamp('joined_at')->useCurrent();
    $table->primary(['invitation_id', 'tester_id']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_testers');
    }
};
