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
       Schema::create('invitations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('token', 64)->unique();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('stopped_at')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
