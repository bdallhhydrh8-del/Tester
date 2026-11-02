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
        Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('role', 20)->default('tester');      // super_admin, admin, tester
    $table->string('status', 20)->default('active');    // active, deactivated (admins only)
    $table->string('name');
    $table->string('phone', 20)->nullable()->unique();  // testers only, E.164
    $table->string('username')->nullable()->unique();   // admins only
    $table->string('password')->nullable();             // admins only
    $table->unsignedInteger('tester_number')->nullable()->unique();
    $table->timestamp('last_login_at')->nullable();
    $table->softDeletes();
    $table->timestamps();
});
      

    

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
