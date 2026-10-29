<?php

namespace App\Services\Results;

class NotesPromptBuilder
{
    public function __construct(
        private int $maxChars = 12000,
        private int $maxNoteChars = 1000,
    ) {}

    /** @param string[] $notes */
    public function build(array $notes): ?string
    {
        $clean = collect($notes)
            ->map(fn ($n) => $this->clean((string) $n))
            ->filter()
            ->values();

        if ($clean->isEmpty()) {
            return null;
        }

        $out = '';
        foreach ($clean as $i => $note) {
            $line = ($i + 1).'. '.$note."\n";
            if (mb_strlen($out) + mb_strlen($line) > $this->maxChars) {
                break;
            }
            $out .= $line;
        }

        return trim($out) ?: null;
    }

    private function clean(string $note): string
    {
        $note = preg_replace('~https?://\S+~iu', '[link]', $note);
        $note = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[email]', $note);
        $note = preg_replace('/\+?\p{Nd}[\p{Nd}\s().-]{6,}\p{Nd}/u', '[number]', $note);
        $note = trim(preg_replace('/\s+/u', ' ', $note));

        return mb_substr($note, 0, $this->maxNoteChars);
    }
}