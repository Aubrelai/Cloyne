<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Apify's Google Maps scraper.
 * Docs: https://docs.apify.com/api/v2
 * Needs APIFY_TOKEN in .env (Apify Console → Settings → API & Integrations).
 */
class Apify
{
    public static function isConfigured(): bool
    {
        return filled(config('services.apify.token'));
    }

    private const US_STATES = [
        'alabama', 'alaska', 'arizona', 'arkansas', 'california', 'colorado', 'connecticut', 'delaware', 'florida', 'georgia',
        'hawaii', 'idaho', 'illinois', 'indiana', 'iowa', 'kansas', 'kentucky', 'louisiana', 'maine', 'maryland',
        'massachusetts', 'michigan', 'minnesota', 'mississippi', 'missouri', 'montana', 'nebraska', 'nevada', 'new hampshire', 'new jersey',
        'new mexico', 'new york', 'north carolina', 'north dakota', 'ohio', 'oklahoma', 'oregon', 'pennsylvania', 'rhode island', 'south carolina',
        'south dakota', 'tennessee', 'texas', 'utah', 'vermont', 'virginia', 'washington', 'west virginia', 'wisconsin', 'wyoming',
    ];

    /**
     * Google reads "georgia" as the country, so an area that ends in a US state name gets ", USA" added.
     * "Atlanta, Georgia" -> "Atlanta, Georgia, USA". Areas that name another country are left alone.
     */
    public static function location(string $area): string
    {
        $area = trim($area);
        $last = strtolower(trim((string) collect(explode(',', $area))->last()));

        return in_array($last, self::US_STATES, true) ? $area.', USA' : $area;
    }

    /**
     * Start a Google Maps search in the background.
     *
     * @return array{run_id: string, dataset_id: string}
     */
    public function startMapsSearch(string $category, string $area, int $maxResults): array
    {
        $actor = str_replace('/', '~', config('services.apify.maps_actor'));

        $response = $this->client()
            ->withQueryParameters(['maxItems' => $maxResults])
            ->post("/acts/{$actor}/runs", [
                'searchStringsArray' => [$category],
                'locationQuery' => self::location($area),
                'maxCrawledPlacesPerSearch' => $maxResults,
                'language' => 'en',
                'skipClosedPlaces' => true,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        return [
            'run_id' => $response->json('data.id'),
            'dataset_id' => $response->json('data.defaultDatasetId'),
        ];
    }

    /** Check the key works. Returns the Apify username. */
    public function testConnection(): string
    {
        $response = $this->client()->timeout(15)->get('/users/me');

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        return (string) ($response->json('data.username') ?: 'your account');
    }

    /** Apify's status for a run: READY, RUNNING, SUCCEEDED, FAILED, ABORTED, TIMED-OUT, ... */
    public function runStatus(string $runId): string
    {
        $response = $this->client()->get("/actor-runs/{$runId}");

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        return (string) $response->json('data.status');
    }

    /** All places a finished run found. */
    public function datasetItems(string $datasetId): array
    {
        $response = $this->client()
            ->timeout(120)
            ->get("/datasets/{$datasetId}/items", ['clean' => 'true', 'format' => 'json']);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Apify key missing. Add APIFY_TOKEN to your .env file.');
        }

        return Http::baseUrl('https://api.apify.com/v2')
            ->withToken(config('services.apify.token'))
            ->acceptJson()
            ->timeout(30);
    }

    private function errorMessage(?array $body, int $status): string
    {
        $message = $body['error']['message'] ?? null;

        return match (true) {
            $status === 401 => 'Apify rejected the key. Check APIFY_TOKEN in your .env file.',
            $status === 402, str_contains(strtolower((string) $message), 'usage hard limit'), str_contains(strtolower((string) $message), 'not enough usage')
                => "Apify's monthly spending limit is used up, so no new searches can start. Raise the limit or upgrade the plan at console.apify.com → Billing. Leads you already have are not affected.",
            filled($message) => "Apify error: {$message}",
            default => "Apify error (HTTP {$status}).",
        };
    }
}
