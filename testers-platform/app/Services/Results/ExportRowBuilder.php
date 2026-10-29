<?php

namespace App\Services\Results;

class ExportRowBuilder
{
    public const HEADER = ['tester_number', 'card_position', 'seconds', 'success', 'difficulty', 'notes'];

    /** @param iterable<array> $answers */
    public function rows(iterable $answers): \Generator
    {
        yield self::HEADER;

        foreach ($answers as $a) {
            yield [
                $a['tester_number'],
                $a['card_position'] ?? '',
                $a['duration_seconds'] ?? '',
                isset($a['success']) ? (int) (bool) $a['success'] : '',
                $a['difficulty'] ?? '',
                $this->safe((string) ($a['notes'] ?? '')),
            ];
        }
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}