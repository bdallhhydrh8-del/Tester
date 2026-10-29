<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Returns the number as +967XXXXXXXXX, or null if it is not a Yemeni mobile number.
     */
    public static function normalize(string $input): ?string
    {
        // Arabic-Indic and Persian digits to ASCII
        $digits = strtr($input, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $digits = preg_replace('/\D+/', '', $digits);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00967')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '967')) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }

        // Yemeni mobile numbers: 9 digits starting with 7
        if (! preg_match('/^7\d{8}$/', $national)) {
            return null;
        }

        return '+967' . $national;
    }
}