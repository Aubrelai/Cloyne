<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Google listing names are often messy: "BRIGHT SMILE DENTAL LLC",
 * "Dentist Dallas TX | Bright Smile Dental". This makes the name a person
 * would write in an email. The original stays in the database.
 */
class BusinessName
{
    private const SUFFIXES = '/[\s,]+(l\.?l\.?c\.?|inc\.?|incorporated|corp\.?|corporation|co\.|ltd\.?|pllc|p\.?a\.?|p\.?c\.?|dds,? pa)$/i';

    private const SMALL_WORDS = ['and', 'of', 'the', 'at', 'in', 'on', 'for', 'a', 'an', 'to', 'by', '&'];

    /** Kept in capitals when a shouting name is fixed. */
    private const ACRONYMS = ['hvac', 'ac', 'abc', 'usa', 'dds', 'dmd', 'md', 'cpa', 'bbq', 'tv', 'llp', 'diy', 'atm', 'ii', 'iii', 'iv', 'k9', 'ymca', 'suv', 'rv', 'dj'];

    public static function clean(?string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '') {
            return '';
        }

        // "Dentist Dallas TX | Bright Smile Dental" or "Bright Smile Dental - Dentist in Dallas": keep the part that looks like a name.
        $parts = preg_split('/\s+[|•·–—]\s+|\s+-\s+/u', $name) ?: [$name];
        if (count($parts) > 1) {
            $name = self::pickNamePart($parts);
        }

        // Drop legal suffixes, twice for "Inc. LLC" (but keep "Smith & Co.").
        $stripped = trim(preg_replace(self::SUFFIXES, '', preg_replace(self::SUFFIXES, '', $name)), " ,.-");
        if ($stripped !== '' && ! preg_match('/(&|\band)$/i', $stripped)) {
            $name = $stripped;
        }

        // Fix SHOUTING (keep short acronyms like "ABC" or "HVAC").
        $letters = preg_replace('/[^a-z]/i', '', $name);
        if (strlen($letters) > 4 && strtoupper($letters) === $letters) {
            $name = self::titleCase($name);
        }

        return $name;
    }

    /** The part with no location or keyword stuffing, else the first. */
    private static function pickNamePart(array $parts): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts)));
        foreach ($parts as $part) {
            if (! preg_match('/\b(near me|in [A-Z][a-z]+|best|top rated|\d{5}|[A-Z]{2}$)/', $part)) {
                return $part;
            }
        }

        return $parts[0] ?? '';
    }

    private static function titleCase(string $name): string
    {
        $words = explode(' ', Str::lower($name));
        foreach ($words as $i => $word) {
            if ($i > 0 && in_array($word, self::SMALL_WORDS, true)) {
                continue;
            }
            // Known acronyms and vowel-less words stay in capitals: "hvac" → "HVAC", "jmk" → "JMK".
            $bare = trim($word, '.,');
            $words[$i] = in_array($bare, self::ACRONYMS, true) || (strlen($bare) <= 4 && ctype_alpha($bare) && ! preg_match('/[aeiouy]/', $bare))
                ? Str::upper($word)
                : Str::ucfirst($word);
        }

        return implode(' ', $words);
    }
}
