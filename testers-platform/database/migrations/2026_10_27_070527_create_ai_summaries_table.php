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
        Schema::create('ai_summaries', function (Blueprint $t) {
            $t->id();
            // add the foreign key to projects once Dev 2's table is merged
            $t->unsignedBigInteger('project_id')->unique();
            $t->text('summary')->nullable();
            $t->unsignedInteger('notes_count')->default(0);
            $t->string('status')->default('pending'); // pending | ready | failed
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_summaries');
    }
};
class AiSummary extends Model {
    protected $guarded = [];
    protected $casts = ['generated_at' => 'datetime'];
}
