<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Search;
use App\Support\Phone;
use App\Support\WebAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Turns Google Maps results from Apify into Business rows. */
class LeadImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $items  Apify dataset items
     * @return array{found: int, added: int, existing: int, dismissed: int, closed: int}
     */
    public function import(Search $search, array $items): array
    {
        $counts = ['found' => 0, 'added' => 0, 'existing' => 0, 'dismissed' => 0, 'closed' => 0];
        $dismissed = DB::table('dismissed_places')->pluck('place_id')->flip();
        $hosts = [];

        foreach ($items as $item) {
            if (blank($item['title'] ?? null)) {
                continue;
            }
            $counts['found']++;

            if (! empty($item['permanentlyClosed'])) {
                $counts['closed']++;

                continue;
            }

            $website = WebAddress::clean($item['website'] ?? null);
            if ($website && strlen($website) > 255) {
                $website = strtok($website, '?'); // MySQL columns hold 255 characters; drop a long query string
                $website = strlen($website) > 255 ? 'https://'.WebAddress::host($website) : $website;
            }
            $attributes = [
                'name' => Str::limit(trim($item['title']), 250, ''),
                'google_category' => $this->short($item['categoryName'] ?? null),
                'street' => $this->short($item['street'] ?? null),
                'city' => $this->short($item['city'] ?? null, 120),
                'state' => $this->stateCode($item['state'] ?? null),
                'phone' => $this->short(Phone::normalize($item['phone'] ?? null), 40),
                'website' => $website,
                'website_host' => WebAddress::host($website),
                'rating' => isset($item['totalScore']) ? round((float) $item['totalScore'], 1) : null,
                'review_count' => isset($item['reviewsCount']) ? (int) $item['reviewsCount'] : null,
                'latitude' => $item['location']['lat'] ?? null,
                'longitude' => $item['location']['lng'] ?? null,
                'source' => 'google_maps',
            ];

            $placeId = $item['placeId'] ?? null;
            if ($placeId && $dismissed->has($placeId)) {
                $counts['dismissed']++; // you deleted this business before

                continue;
            }

            $existing = $this->findExisting($placeId, $attributes);

            if ($existing) {
                // Already in Cloyne: refresh Google's details, keep its stage, score and search.
                $existing->update(array_filter($attributes, fn ($v) => ! is_null($v)));
                $counts['existing']++;

                continue;
            }

            Business::create($attributes + [
                'search_id' => $search->id,
                'place_id' => $placeId,
                'category' => Str::ucfirst(trim($search->category)),
            ]);
            $counts['added']++;
            if ($attributes['website_host']) {
                $hosts[] = $attributes['website_host'];
            }
        }

        Business::flagChains($hosts);

        return $counts;
    }

    /**
     * The same business can appear under two Google listings. Match by place ID,
     * then by phone number, then by website (unless that website is shared, like facebook.com).
     */
    private function findExisting(?string $placeId, array $attributes): ?Business
    {
        if ($placeId && ($match = Business::where('place_id', $placeId)->first())) {
            return $match;
        }
        if ($attributes['phone'] && ($match = Business::where('phone', $attributes['phone'])->first())) {
            return $match;
        }
        $host = $attributes['website_host'];
        if ($host && ! in_array($host, Business::SHARED_HOSTS, true)
            && ($match = Business::where('website_host', $host)->where('city', $attributes['city'])->first())) {
            return $match;
        }
        if (! $placeId) {
            return Business::where('name', $attributes['name'])->where('city', $attributes['city'])->first();
        }

        return null;
    }

    /** Fit text into a database column (MySQL refuses values that are too long). */
    private function short(?string $value, int $max = 250): ?string
    {
        return blank($value) ? null : Str::limit(trim($value), $max, '');
    }

    /** "Florida" → "FL". Leaves 2-letter codes alone; unknown → null. */
    private function stateCode(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }
        $state = trim($state);
        if (strlen($state) === 2) {
            return strtoupper($state);
        }

        return self::STATES[strtolower($state)] ?? null;
    }

    private const STATES = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA',
        'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'district of columbia' => 'DC',
        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL',
        'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA',
        'maine' => 'ME', 'maryland' => 'MD', 'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN',
        'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
        'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK', 'oregon' => 'OR',
        'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC', 'south dakota' => 'SD',
        'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT', 'virginia' => 'VA',
        'washington' => 'WA', 'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY',
        'puerto rico' => 'PR',
    ];
}
