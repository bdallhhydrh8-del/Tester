<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
    Schema::table('cards', fn (Blueprint $t) => $t->renameColumn('ask_difficulty', 'ask_ease'));
    }

   public function down(): void
    {
    Schema::table('cards', fn (Blueprint $t) => $t->renameColumn('ask_ease', 'ask_difficulty'));
    }
};
