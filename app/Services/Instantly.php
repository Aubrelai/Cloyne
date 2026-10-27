<?php

namespace App\Services;

use App\Models\Draft;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Instantly API v2 (https://developer.instantly.ai). Key INSTANTLY_API_KEY, Bearer auth.
 * Cloyne writes every email itself, so its campaign's steps are just placeholders filled from each
 * lead's custom variables: {{cloyne_subject}}, {{cloyne_body_1}}, {{cloyne_body_2}}, {{cloyne_body_3}}.
 */
class Instantly
{
    public static function isConfigured(): bool
    {
        return filled(config('services.instantly.key'));
    }

    /** Sending inboxes connected in Instantly: [['email', 'sending' => bool, 'warmup' => bool, 'score' => ?int]]. */
    public function accounts(): array
    {
        $items = $this->get('/accounts', ['limit' => 100])['items'] ?? [];

        return array_map(fn ($a) => [
            'email' => (string) ($a['email'] ?? ''),
            'sending' => (int) ($a['status'] ?? 0) === 1,
            'warmup' => (int) ($a['warmup_status'] ?? 0) === 1,
            'score' => isset($a['stat_warmup_score']) ? (int) $a['stat_warmup_score'] : null,
        ], $items);
    }

    /** Create Cloyne's campaign: 3 steps, weekday business hours, stops on reply. Returns its id. */
    public function createCampaign(array $inboxes, int $dailyLimit): string
    {
        $c = config('services.instantly');
        [$day3, $day7] = $c['follow_up_days'];
        $step = fn (int $delay, string $subject, string $body) => [
            'type' => 'email', 'delay' => $delay, 'delay_unit' => 'days',
            'variants' => [['subject' => $subject, 'body' => $body]],
        ];

        $campaign = $this->post('/campaigns', [
            'name' => 'Cloyne outreach',
            'campaign_schedule' => ['schedules' => [[
                'name' => 'Weekdays',
                'timing' => $c['hours'],
                'days' => ['0' => false, '1' => true, '2' => true, '3' => true, '4' => true, '5' => true, '6' => false],
                'timezone' => $c['timezone'],
            ]]],
            // Delay = days to wait after this step before the next one; an empty subject stays in the same thread.
            'sequences' => [['steps' => [
                $step($day3, '{{cloyne_subject}}', '{{cloyne_body_1}}'),
                $step($day7, '', '{{cloyne_body_2}}'),
                $step(0, '', '{{cloyne_body_3}}'),
            ]]],
            'email_list' => array_values($inboxes),
            'daily_limit' => $dailyLimit,
            'stop_on_reply' => true,
            'stop_on_auto_reply' => false,
            'link_tracking' => false, // tracked links hurt deliverability on new domains
            'open_tracking' => false,
            'text_only' => true,
        ]);

        return (string) ($campaign['id'] ?? throw new RuntimeException('Instantly did not return a campaign id.'));
    }

    public function activate(string $campaignId): void
    {
        $this->post("/campaigns/{$campaignId}/activate");
    }

    public function pause(string $campaignId): void
    {
        $this->post("/campaigns/{$campaignId}/pause");
    }

    /** Hand one approved draft to the campaign. Returns Instantly's lead id. */
    public function addLead(string $campaignId, Draft $draft): string
    {
        $business = $draft->business;
        [$follow1, $follow2] = $draft->followUps();
        $html = fn (string $text) => self::html($text."\n\n".Draft::signature()."\n\n".Draft::footer());

        $lead = $this->post('/leads', [
            'campaign' => $campaignId,
            'email' => $draft->to_email,
            'first_name' => $draft->contact?->name ? strtok($draft->contact->name, ' ') : null,
            'company_name' => $business->displayName(),
            'website' => $business->website,
            'custom_variables' => [
                'cloyne_subject' => (string) $draft->subject,
                'cloyne_body_1' => $html((string) $draft->body),
                'cloyne_body_2' => $html($follow1),
                'cloyne_body_3' => $html($follow2),
                'cloyne_draft' => $draft->id,
            ],
            'skip_if_in_workspace' => true,
        ]);

        return (string) ($lead['id'] ?? throw new RuntimeException('Instantly did not accept the lead.'));
    }

    /**
     * One page of the campaign's leads with their state. Lead status: 1 active, 2 paused, 3 finished,
     * -1 bounced, -2 unsubscribed, -3 skipped.
     *
     * @return array{items: array, next: ?string}
     */
    public function leads(string $campaignId, ?string $after = null): array
    {
        $page = $this->post('/leads/list', array_filter(['campaign' => $campaignId, 'limit' => 100, 'starting_after' => $after]));

        return ['items' => $page['items'] ?? [], 'next' => $page['next_starting_after'] ?? null];
    }

    /** Replies received for the campaign since a time, oldest first. */
    public function replies(string $campaignId, ?string $sinceIso, ?string $after = null): array
    {
        $page = $this->get('/emails', array_filter([
            'campaign_id' => $campaignId,
            'email_type' => 'received',
            'sort_order' => 'asc',
            'limit' => 100,
            'min_timestamp_created' => $sinceIso,
            'starting_after' => $after,
        ]));

        return ['items' => $page['items'] ?? [], 'next' => $page['next_starting_after'] ?? null];
    }

    /** Answer a reply in its thread, from the inbox that received it (v3.0). */
    public function replyTo(string $emailId, string $eaccount, string $subject, string $text): void
    {
        $this->post('/emails/reply', [
            'reply_to_uuid' => $emailId,
            'eaccount' => $eaccount,
            'subject' => $subject,
            'body' => ['text' => $text, 'html' => self::html($text)],
        ]);
    }

    /** Never email this address again from any campaign. */
    public function block(string $email): void
    {
        $this->post('/block-lists-entries', ['bl_value' => mb_strtolower($email)]);
    }

    /** Check the key works. Returns how many sending inboxes are connected. */
    public function testConnection(): int
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Instantly key missing. Add INSTANTLY_API_KEY to your .env file.');
        }

        return count($this->accounts());
    }

    /** Plain text → the simple HTML Instantly sends (line breaks kept, nothing else). */
    public static function html(string $text): string
    {
        return nl2br(e(trim(str_replace("\r\n", "\n", $text))), false);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.instantly.base_url'))
            ->withToken((string) config('services.instantly.key'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 2000, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException
                || in_array(optional($e->response ?? null)->status(), [429, 500, 502, 503], true), throw: false);
    }

    private function get(string $path, array $query = []): array
    {
        return $this->check($this->client()->get($path, $query));
    }

    private function post(string $path, array $body = []): array
    {
        return $this->check($body ? $this->client()->post($path, $body) : $this->client()->withBody('{}', 'application/json')->post($path));
    }

    private function check($response): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }
        $message = $response->json('message') ?? $response->json('error');

        throw new RuntimeException(match ($response->status()) {
            401 => 'Instantly rejected the key. Check INSTANTLY_API_KEY in your .env file.',
            402 => 'Instantly says the workspace has no active paid plan.',
            429 => 'Instantly asked Cloyne to slow down. It will try again shortly.',
            default => 'Instantly error (HTTP '.$response->status().')'.($message ? ': '.(is_string($message) ? $message : json_encode($message)) : '.'),
        });
    }
}
