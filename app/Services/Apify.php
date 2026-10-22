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
                'locationQuery' => $area,
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
            $status === 402 => 'Apify says the account is out of credit for this month.',
            filled($message) => "Apify error: {$message}",
            default => "Apify error (HTTP {$status}).",
        };
    }
}
