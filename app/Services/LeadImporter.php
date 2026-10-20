<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Search;
use Illuminate\Support\Str;

/** Turns Google Maps results from Apify into Business rows. */
class LeadImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $items  Apify dataset items
     * @return array{found: int, added: int}
     */
    public function import(Search $search, array $items): array
    {
        $added = 0;
        $found = 0;

        foreach ($items as $item) {
            if (! empty($item['permanentlyClosed']) || blank($item['title'] ?? null)) {
                continue;
            }
            $found++;

            $attributes = [
                'name' => Str::limit(trim($item['title']), 250, ''),
                'google_category' => $item['categoryName'] ?? null,
                'street' => $item['street'] ?? null,
                'city' => $item['city'] ?? null,
                'state' => $this->stateCode($item['state'] ?? null),
                'phone' => $item['phone'] ?? null,
                'website' => $this->cleanUrl($item['website'] ?? null),
                'rating' => isset($item['totalScore']) ? round((float) $item['totalScore'], 1) : null,
                'review_count' => isset($item['reviewsCount']) ? (int) $item['reviewsCount'] : null,
                'latitude' => $item['location']['lat'] ?? null,
                'longitude' => $item['location']['lng'] ?? null,
                'source' => 'google_maps',
            ];

            $placeId = $item['placeId'] ?? null;

            $existing = $placeId
                ? Business::where('place_id', $placeId)->first()
                : Business::where('name', $attributes['name'])->where('city', $attributes['city'])->first();

            if ($existing) {
                // Already in Cloyne: refresh Google's details, keep its stage, score and search.
                $existing->update(array_filter($attributes, fn ($v) => ! is_null($v)));

                continue;
            }

            Business::create($attributes + [
                'search_id' => $search->id,
                'place_id' => $placeId,
                'category' => Str::ucfirst(trim($search->category)),
            ]);
            $added++;
        }

        return ['found' => $found, 'added' => $added];
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

    private function cleanUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://' . ltrim($url, '/');
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
