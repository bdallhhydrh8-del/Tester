<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{public function up(): void
{
    Schema::table('responses', fn (Blueprint $t) => $t->renameColumn('difficulty', 'ease'));
}

public function down(): void
{
    Schema::table('responses', fn (Blueprint $t) => $t->renameColumn('ease', 'difficulty'));
}
};
