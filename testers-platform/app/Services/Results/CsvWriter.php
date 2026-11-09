<?php

namespace App\Services\Results;

class CsvWriter
{
    public function write($stream, iterable $rows): void
    {
        fwrite($stream, "\xEF\xBB\xBF"); // byte order mark so Arabic opens correctly in Excel
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
    }

    public function toString(iterable $rows): string
    {
        $s = fopen('php://temp', 'r+');
        $this->write($s, $rows);
        rewind($s);

        return stream_get_contents($s);
    }
}