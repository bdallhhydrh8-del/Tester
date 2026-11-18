<?php

namespace Tests\Unit;

use App\Services\Results\NotesPromptBuilder;
use PHPUnit\Framework\TestCase;

class NotesPromptBuilderTest extends TestCase
{
    public function test_returns_null_when_there_are_no_usable_notes(): void
    {
        $this->assertNull((new NotesPromptBuilder)->build([]));
        $this->assertNull((new NotesPromptBuilder)->build(['', '   ']));
    }

    public function test_masks_emails_phones_and_links(): void
    {
        $out = (new NotesPromptBuilder)->build([
            'call me on +967 700 000 000 or mail sara@example.com, see https://x.com/a?b=1',
        ]);

        $this->assertStringNotContainsString('700 000', $out);
        $this->assertStringNotContainsString('sara@', $out);
        $this->assertStringNotContainsString('x.com', $out);
        $this->assertStringContainsString('[number]', $out);
        $this->assertStringContainsString('[email]', $out);
        $this->assertStringContainsString('[link]', $out);
    }

    public function test_masks_arabic_indic_digit_phone_numbers(): void
    {
        $out = (new NotesPromptBuilder)->build(['رقمي ٠٥٥١٢٣٤٥٦٧ شكرا']);
        $this->assertStringNotContainsString('٠٥٥١٢٣٤٥٦٧', $out);
        $this->assertStringContainsString('شكرا', $out);
    }

    public function test_stops_at_the_size_limit_without_cutting_a_note_in_half(): void
    {
        $out = (new NotesPromptBuilder(maxChars: 30))->build(['first note', 'second note', 'third note that does not fit']);
        $this->assertStringContainsString('1. first note', $out);
        $this->assertStringNotContainsString('third', $out);
    }
}