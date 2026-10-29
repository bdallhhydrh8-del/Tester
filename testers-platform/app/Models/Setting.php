<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['default_cards'];

    protected function casts(): array
    {
        return [
            'default_cards' => 'array',
        ];
    }

    //The one settings row. Created with the default form if it is missing.
    public static function current(): self
    {
        return static::query()->first() ?? static::create(static::defaults());
    }

    public static function defaults(): array
    {
        return [
            // Same fields as a card. Position comes from the order in this list.
            'default_cards' => [
                [
                    'instructions' => 'افتح التطبيق أو الموقع وسجّل الدخول',
                    'ask_success' => true,
                    'ask_difficulty' => true,
                    'ask_notes' => true,
                    'ask_file' => false,
                    'required' => true,
                    'custom_questions' => null,
                ],
                [
                    'instructions' => 'ابحث عن المنتج الذي تريده وأضفه إلى السلة',
                    'ask_success' => true,
                    'ask_difficulty' => true,
                    'ask_notes' => true,
                    'ask_file' => true,
                    'required' => true,
                    'custom_questions' => null,
                ],
            ],
        ];
    }
}