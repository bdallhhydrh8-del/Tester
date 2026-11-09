<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('token');
        });

        DB::table('invitations')->whereNotNull('stopped_at')->update(['status' => 'disabled']);

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'stopped_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
        });

        DB::table('invitations')->where('status', 'disabled')->update(['stopped_at' => now()]);

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};