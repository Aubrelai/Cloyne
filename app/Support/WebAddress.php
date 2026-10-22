<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Website links from Google: add https, drop tracking bits, compare by host. */
class WebAddress
{
    private const TRACKING = '/^(utm_[a-z]+|gclid|fbclid|msclkid|y_source|ref|source)$/i';

    public static function clean(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! Str::startsWith(Str::lower($url), ['http://', 'https://'])) {
            $url = 'https://'.ltrim($url, '/');
        }

        $parts = parse_url($url);
        if (! $parts || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter($query, fn ($key) => ! preg_match(self::TRACKING, (string) $key), ARRAY_FILTER_USE_KEY);

        // Upgrade to https; nearly every site supports it, and the scanner follows redirects anyway.
        $clean = 'https://'.Str::lower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
        if ($query) {
            $clean .= (isset($parts['path']) ? '' : '/').'?'.http_build_query($query);
        } else {
            $clean = rtrim($clean, '/');
        }

        return $clean;
    }

    /** "https://www.Bright-Smile.com/dallas" → "bright-smile.com" */
    public static function host(?string $url): ?string
    {
        $host = parse_url((string) self::clean($url), PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', Str::lower($host)) : null;
    }
}
