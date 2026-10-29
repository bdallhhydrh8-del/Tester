<?php

namespace App\Services\CsvWriter;

use Illuminate\Support\Collection;

class CsvWriter
{
    public function write($stream, iterable $rows): void
    {
        fwrite($stream, "\xEF\xBB\xBF");               // byte order mark
        foreach ($rows as $row) fputcsv($stream, $row);
    }

    public function toString(iterable $rows): string
    {
        $s = fopen('php://temp', 'r+');
        $this->write($s, $rows);
        rewind($s);
        return stream_get_contents($s);
    }

    public function test_csv_starts_with_bom_and_keeps_arabic(): void
    {
        $csv = (new CsvWriter)->toString([['tester_number', 'notes'], [3, 'ممتاز']]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ممتاز', $csv);
    }
}