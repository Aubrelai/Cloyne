<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Contact;
use App\Models\Draft;
use App\Models\Reply;
use App\Models\Setting;
use App\Support\FriendlyError;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Version 2.0: hands approved drafts to Instantly within safe daily limits, and brings back what happened
 * (replies, bounces, opt-outs). Nothing sends unless every item on readiness() is ticked, including the
 * Owner's switch on the Campaigns screen. Sending pauses by itself when bounces or opt-outs run high.
 */
class Sender
{
    public function __construct(private Instantly $instantly) {}

    public static function isOn(): bool
    {
        return (bool) config('cloyne.settings.sending_on', false);
    }

    public static function campaignId(): ?string
    {
        return config('cloyne.settings.instantly_campaign') ?: null;
    }

    /**
     * What must be true before anything sends.
     *
     * @return list<array{key: string, label: string, ok: bool, detail: ?string}>
     */
    public function readiness(?array $accounts = null): array
    {
        $accounts ??= $this->accountsOrNull();
        $sending = collect($accounts ?? [])->where('sending', true);

        return [
            ['key' => 'key', 'label' => 'Instantly connected', 'ok' => Instantly::isConfigured() && $accounts !== null,
                'detail' => Instantly::isConfigured() ? ($accounts === null ? 'The key didn\'t work. Check it in Settings.' : null) : 'Add INSTANTLY_API_KEY on the server (settings command).'],
            ['key' => 'inboxes', 'label' => 'Sending inboxes connected', 'ok' => $sending->isNotEmpty(),
                'detail' => $accounts === null ? null : $sending->count().' of '.count($accounts).' ready to send'],
            ['key' => 'warm', 'label' => 'Inboxes warmed up (2–3 weeks)', 'ok' => $sending->isNotEmpty() && $sending->every(fn ($a) => $a['warmup'] && ($a['score'] ?? 0) >= 80),
                'detail' => $sending->isNotEmpty() ? 'Warm-up scores: '.$sending->map(fn ($a) => $a['email'].' '.($a['score'] ?? '–'))->implode(', ').' (80+ needed)' : null],
            ['key' => 'address', 'label' => 'Mailing address in the footer', 'ok' => Draft::readyToSend(),
                'detail' => Draft::readyToSend() ? null : 'Required by law. Add it in Settings → Outreach emails.'],
        ];
    }

    public function isReady(?array $accounts = null): bool
    {
        return collect($this->readiness($accounts))->every('ok');
    }

    /** Most emails Cloyne may hand over today: inboxes × per-inbox limit, minus what already went today. */
    public function remainingToday(array $accounts): int
    {
        $cap = collect($accounts)->where('sending', true)->count() * (int) config('services.instantly.per_inbox_daily', 30);
        $today = Draft::where('queued_at', '>=', now(config('services.instantly.timezone'))->startOfDay())->count();

        return max(0, $cap - $today);
    }

    /** Owner switch. Turning on creates the Instantly campaign the first time and starts it. */
    public function turnOn(): void
    {
        $accounts = $this->instantly->accounts();
        if (! $this->isReady($accounts)) {
            throw new \RuntimeException('Not ready yet: finish the checklist on the Campaigns screen first.');
        }
        $inboxes = collect($accounts)->where('sending', true)->pluck('email')->all();
        $campaign = self::campaignId() ?? $this->instantly->createCampaign($inboxes, count($inboxes) * (int) config('services.instantly.per_inbox_daily', 30));
        Setting::put('instantly_campaign', $campaign);
        $this->instantly->activate($campaign);
        Setting::put('sending_on', true);
        Setting::forget('sending_paused_reason');
    }

    public function turnOff(?string $reason = null): void
    {
        Setting::put('sending_on', false);
        $reason ? Setting::put('sending_paused_reason', $reason) : Setting::forget('sending_paused_reason');
        if (self::campaignId() && Instantly::isConfigured()) {
            rescue(fn () => $this->instantly->pause(self::campaignId()), report: true);
        }
    }

    /** Hand approved drafts to Instantly, oldest approval first, within today's limit. Returns how many. */
    public function send(): int
    {
        Setting::applyToConfig();
        if (! self::isOn() || ! self::campaignId()) {
            return 0;
        }
        $accounts = $this->instantly->accounts();
        if (! $this->isReady($accounts)) {
            return 0;
        }

        $sent = 0;
        $drafts = Draft::where('status', 'approved')->whereNull('send_status')->whereNotNull('to_email')
            ->whereHas('business', fn ($b) => $b->whereNotIn('stage', ['excluded', 'booked', 'won', 'lost', 'replied'])->whereNull('crm_id'))
            ->with(['business', 'contact'])->orderBy('reviewed_at')->limit($this->remainingToday($accounts))->get();

        foreach ($drafts as $draft) {
            if (Contact::where('email', $draft->to_email)->whereIn('email_status', ['invalid', 'bounced'])->exists()) {
                $draft->update(['send_status' => 'bounced', 'error' => 'Not sent: this address failed an email check or bounced before.']);

                continue;
            }
            try {
                $leadId = $this->instantly->addLead(self::campaignId(), $draft);
            } catch (Throwable $e) {
                $draft->update(['error' => 'Not sent yet: '.FriendlyError::message($e, 'Instantly')]);
                report($e);

                break; // Instantly is having trouble; try the rest next run
            }
            $draft->update(['send_status' => 'queued', 'queued_at' => now(), 'instantly_lead_id' => $leadId, 'error' => null]);
            if (in_array($draft->business->stage, ['new', 'audited', 'draft_ready'], true)) {
                $draft->business->update(['stage' => 'emailed']);
            }
            $sent++;
        }

        return $sent;
    }

    /** Bring back bounces, opt-outs, finished sequences and replies; pause if bounces or opt-outs run high. */
    public function sync(): array
    {
        Setting::applyToConfig();
        $campaign = self::campaignId();
        if (! $campaign || ! Instantly::isConfigured()) {
            return ['updated' => 0, 'replies' => 0];
        }

        $updated = 0;
        $after = null;
        do {
            $page = $this->instantly->leads($campaign, $after);
            foreach ($page['items'] as $lead) {
                $updated += $this->applyLeadState($lead);
            }
            $after = $page['next'];
        } while ($after);

        $new = 0;
        $since = Reply::max('received_at');
        $after = null;
        do {
            $page = $this->instantly->replies($campaign, $since ? Carbon::parse($since)->subMinute()->toIso8601String() : null, $after);
            foreach ($page['items'] as $email) {
                $new += $this->saveReply($email) ? 1 : 0;
            }
            $after = $page['next'];
        } while ($after);

        $this->pauseIfUnhealthy();

        return ['updated' => $updated, 'replies' => $new];
    }

    /** Bounce and opt-out rates for emails handed over in the last 7 days. */
    public static function health(): array
    {
        $recent = Draft::where('queued_at', '>=', now()->subDays(7));
        $sent = (clone $recent)->count();

        return [
            'sent' => $sent,
            'bounce_rate' => $sent ? (clone $recent)->where('send_status', 'bounced')->count() / $sent : 0.0,
            'opt_out_rate' => $sent ? (clone $recent)->where('send_status', 'unsubscribed')->count() / $sent : 0.0,
        ];
    }

    private function pauseIfUnhealthy(): void
    {
        $h = self::health();
        if (! self::isOn() || $h['sent'] < 50) {
            return;
        }
        $c = config('services.instantly');
        if ($h['bounce_rate'] > $c['max_bounce_rate']) {
            $this->turnOff(sprintf('Paused by itself: %.1f%% of last week\'s emails bounced (limit %.0f%%). Clean the list, then turn sending back on.', $h['bounce_rate'] * 100, $c['max_bounce_rate'] * 100));
        } elseif ($h['opt_out_rate'] > $c['max_opt_out_rate']) {
            $this->turnOff(sprintf('Paused by itself: %.1f%% of last week\'s emails were opt-outs (limit %.0f%%). Check the emails and who they go to.', $h['opt_out_rate'] * 100, $c['max_opt_out_rate'] * 100));
        }
    }

    private function applyLeadState(array $lead): int
    {
        $draftId = $lead['payload']['cloyne_draft'] ?? $lead['custom_variables']['cloyne_draft'] ?? null;
        $draft = $draftId ? Draft::find($draftId) : Draft::where('instantly_lead_id', $lead['id'] ?? '')->first();
        if (! $draft || in_array($draft->send_status, ['replied', 'bounced', 'unsubscribed'], true)) {
            return 0;
        }

        $state = match ((int) ($lead['status'] ?? 1)) {
            -1 => 'bounced',
            -2 => 'unsubscribed',
            3 => ((int) ($lead['email_reply_count'] ?? 0)) > 0 ? 'replied' : 'finished',
            default => ((int) ($lead['email_reply_count'] ?? 0)) > 0 ? 'replied' : null,
        };
        if (! $state || $state === $draft->send_status) {
            return 0;
        }

        $draft->update(['send_status' => $state]);
        match ($state) {
            'bounced' => Contact::where('email', $draft->to_email)->update(['email_status' => 'bounced']),
            'unsubscribed' => $this->optOut($draft->business, $draft->to_email),
            'replied' => in_array($draft->business->stage, ['booked', 'won', 'lost'], true) ? null : $draft->business->update(['stage' => 'replied']),
            default => null,
        };

        return 1;
    }

    private function saveReply(array $email): bool
    {
        $id = (string) ($email['id'] ?? '');
        if ($id === '' || Reply::where('instantly_email_id', $id)->exists()) {
            return false;
        }
        $from = mb_strtolower((string) ($email['lead'] ?? $email['from_address_email'] ?? ''));
        $draft = Draft::where('to_email', $from)->whereNotNull('send_status')->latest('queued_at')->first();
        $text = (string) ($email['body']['text'] ?? strip_tags((string) ($email['body']['html'] ?? '')));
        $auto = (int) ($email['is_auto_reply'] ?? 0) === 1;
        $optOut = ! $auto && Reply::isOptOut($text);

        $reply = Reply::create([
            'business_id' => $draft?->business_id,
            'draft_id' => $draft?->id,
            'instantly_email_id' => mb_substr($id, 0, 64),
            'from_email' => mb_substr($from, 0, 250),
            'eaccount' => isset($email['eaccount']) ? mb_substr((string) $email['eaccount'], 0, 250) : null,
            'subject' => mb_substr((string) ($email['subject'] ?? ''), 0, 250),
            'body' => mb_substr($text, 0, 20000),
            'is_auto_reply' => $auto,
            'opt_out' => $optOut,
            'received_at' => isset($email['timestamp_email']) ? Carbon::parse($email['timestamp_email']) : now(),
        ]);

        // v3.0: sort it and draft an answer from the fact sheet (a person approves before anything goes).
        if (! $auto && ReplyAgent::isOn() && Claude::isConfigured()) {
            \App\Jobs\HandleReply::dispatch($reply);
        }

        if ($draft && ! $auto) {
            $draft->update(['send_status' => $optOut ? 'unsubscribed' : 'replied']);
            $optOut ? $this->optOut($draft->business, $from) : (in_array($draft->business->stage, ['booked', 'won', 'lost'], true) ? null : $draft->business->update(['stage' => 'replied']));
        }

        return true;
    }

    /** Never contact them again: excluded in Cloyne and blocked in Instantly. */
    private function optOut(?Business $business, string $email): void
    {
        if ($business && $business->stage !== 'excluded') {
            $business->exclude('Asked not to be contacted');
        }
        if ($email !== '') {
            rescue(fn () => $this->instantly->block($email), report: true);
        }
    }

    private function accountsOrNull(): ?array
    {
        if (! Instantly::isConfigured()) {
            return null;
        }

        return rescue(fn () => $this->instantly->accounts(), null, report: false);
    }
}
