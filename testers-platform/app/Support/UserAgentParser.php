<?php

namespace App\Support;

/**
 * Reads the browser, operating system and device type from a User-Agent header.
 * Coarse on purpose: it feeds the device split on the dashboard, nothing more.
 *
 * Known limit: iPadOS Safari in its default "desktop site" mode sends a Mac user agent,
 * so it is reported as a macOS desktop. The header alone cannot tell them apart.
 */
class UserAgentParser
{
    /** @return array{browser: ?string, os: ?string, device_type: ?string} */
    public function parse(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return ['browser' => null, 'os' => null, 'device_type' => null];
        }

        return [
            'browser' => $this->browser($ua),
            'os' => $this->os($ua),
            'device_type' => $this->deviceType($ua),
        ];
    }

    // The order matters: many browsers also say "Chrome" and "Safari" in their header.
    private function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/'), str_contains($ua, 'EdgA/'), str_contains($ua, 'EdgiOS/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Chrome/'), str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Other',
        };
    }

    // Android first (its header also says "Linux"), iOS before macOS (it says "like Mac OS X").
    private function os(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Android') => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Macintosh'), str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Other',
        };
    }

    private function deviceType(string $ua): string
    {
        if (str_contains($ua, 'iPad') || stripos($ua, 'Tablet') !== false) {
            return 'tablet';
        }

        // Android phones say "Mobile"; Android tablets do not.
        if (str_contains($ua, 'Android')) {
            return str_contains($ua, 'Mobile') ? 'phone' : 'tablet';
        }

        if (preg_match('/iPhone|iPod|Mobile/i', $ua)) {
            return 'phone';
        }

        return 'desktop';
    }
}