<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
   public function up(): void
{
    Schema::table('settings', function (Blueprint $table) {
        $table->text('default_note')->nullable()->after('default_cards');
    });

    // Convert the saved default form, if a row already exists.
    foreach (DB::table('settings')->get() as $row) {
        $cards = json_decode($row->default_cards, true) ?: [];

        $cards = array_map(function (array $card) {
            $card['ask_ease'] = $card['ask_ease'] ?? $card['ask_difficulty'] ?? true;
            unset($card['ask_difficulty'], $card['ask_success']);

            return $card;
        }, $cards);

        DB::table('settings')->where('id', $row->id)->update([
            'default_cards' => json_encode($cards, JSON_UNESCAPED_UNICODE),
            'default_note' => $row->default_note ?? 'شكراً لمشاركتك في اختبار منتجنا. اتبع التعليمات في كل مهمة، وأجب بصدق، ولا تتردد في الإبلاغ عن أي مشكلة تواجهك.',
        ]);
    }
}

public function down(): void
{
    Schema::table('settings', fn (Blueprint $table) => $table->dropColumn('default_note'));
}
};
