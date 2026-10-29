<?php

namespace Tests\Unit;

use App\Services\Results\ExportRowBuilder;
use PHPUnit\Framework\TestCase;

class ExportRowBuilderTest extends TestCase
{
    private function rows(array $answers): array
    {
        return iterator_to_array((new ExportRowBuilder)->rows($answers), false);
    }

    public function test_first_row_is_the_header(): void
    {
        $this->assertSame(ExportRowBuilder::HEADER, $this->rows([])[0]);
    }

    public function test_name_and_phone_never_reach_the_output(): void
    {
        $out = json_encode($this->rows([[
            'tester_number' => 7, 'name' => 'Sara Ahmed', 'phone' => '+967700000000',
            'duration_seconds' => 90, 'success' => true, 'difficulty' => 3, 'notes' => 'ok',
        ]]), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('Sara', $out);
        $this->assertStringNotContainsString('+9677', $out);
    }

    public function test_formula_like_notes_are_neutralised(): void
    {
        $row = $this->rows([['tester_number' => 1, 'notes' => '=HYPERLINK("http://x")']])[1];
        $this->assertSame("'=HYPERLINK(\"http://x\")", $row[5]);
    }

    public function test_missing_values_become_empty_cells_and_arabic_is_kept(): void
    {
        $row = $this->rows([['tester_number' => 2, 'notes' => 'ممتاز']])[1];
        $this->assertSame([2, '', '', '', '', 'ممتاز'], $row);
    }
}