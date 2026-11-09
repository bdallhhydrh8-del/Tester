<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Must run AFTER create_responses_table. Rename the file if its timestamp is not later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            // Answers to the card's custom questions, keyed by question id.
            // {"q1": "text", "q2": "chosen option", "q3": ["option", "option"]}
            $table->json('custom_answers')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn('custom_answers');
        });
    }
};