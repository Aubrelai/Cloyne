<?php

namespace App\Http\Controllers;

use App\Jobs\AuditBusiness;
use App\Models\Business;
use App\Models\Call;
use App\Models\User;
use App\Services\Claude;
use App\Support\Phone;
use App\Support\WebAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The LaiQ Digital CRM's connection to Cloyne. Every route needs the CRM key (crm.key middleware).
 *
 *   POST /api/crm/leads            CRM sends its cold-call list rows; Cloyne audits them.
 *   GET  /api/crm/leads            CRM reads back score + package (Starter / Growth / Scale) for its rows.
 *   GET  /api/crm/call-list        Leads Cloyne found that pass the CRM rules (Settings) and aren't in the CRM yet.
 *   POST /api/crm/call-list/added  CRM says which of those it added, with its own IDs.
 *   POST /api/crm/calls            CRM sends call results (no answer, booked, ...).
 *   GET  /api/crm/users            Emails of everyone who can sign in to Cloyne.
 *   GET  /api/crm/booked           v4.0: booked calls for the CRM pipeline, with audit, emails and calls.
 *   POST /api/crm/deals            v4.0: the CRM reports each deal's stage (showed, quoted, won, lost…).
 */
class CrmController extends Controller
{
    /** CRM → Cloyne: rows from the CRM's lists. Matched to existing leads, otherwise added; then audited. */
    public function importLeads(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leads' => ['required', 'array', 'min:1', 'max:500'],
            'leads.*.crm_id' => ['required', 'string', 'max:64', 'distinct'],
            'leads.*.name' => ['required', 'string', 'max:250'],
            'leads.*.phone' => ['nullable', 'string', 'max:40'],
            'leads.*.website' => ['nullable', 'string', 'max:500'],
            'leads.*.email' => ['nullable', 'email', 'max:250'],
            'leads.*.city' => ['nullable', 'string', 'max:120'],
            'leads.*.state' => ['nullable', 'string', 'max:40'],
            'leads.*.category' => ['nullable', 'string', 'max:120'],
            'leads.*.list' => ['nullable', 'string', 'max:120'],
            'audit_again' => ['sometimes', 'boolean'],
        ]);

        $counts = ['received' => count($data['leads']), 'added' => 0, 'matched' => 0, 'queued' => 0];
        $toAudit = [];

        foreach ($data['leads'] as $row) {
            $phone = Phone::normalize($row['phone'] ?? null);
            $website = WebAddress::clean($row['website'] ?? null);
            $host = WebAddress::host($website);
            $city = $row['city'] ?? null;

            $business = Business::where('crm_id', $row['crm_id'])->first()
                ?? ($phone ? Business::whereNull('crm_id')->where('phone', $phone)->first() : null)
                ?? ($host && $city && ! in_array($host, Business::SHARED_HOSTS, true)
                    ? Business::whereNull('crm_id')->where('website_host', $host)->where('city', $city)->first() : null);

            $fields = array_filter([
                'phone' => $phone,
                'website' => $website ? mb_substr($website, 0, 250) : null,
                'website_host' => $host,
                'city' => $city ? mb_substr($city, 0, 120) : null,
                'state' => $this->stateCode($row['state'] ?? null),
                'category' => isset($row['category']) ? mb_substr($row['category'], 0, 120) : null,
            ], fn ($v) => $v !== null && $v !== '');

            if ($business) {
                $counts['matched']++;
                // Fill gaps only; never overwrite what Google Maps gave Cloyne.
                $business->fill(array_filter($fields, fn ($v, $k) => blank($business->{$k}), ARRAY_FILTER_USE_BOTH));
                $business->crm_id = $row['crm_id'];
                $business->crm_list = isset($row['list']) ? mb_substr($row['list'], 0, 120) : $business->crm_list;
                $business->save();
            } else {
                $counts['added']++;
                $business = Business::create($fields + [
                    'category' => mb_substr($row['list'] ?? 'Uncategorized', 0, 120),
                    'name' => mb_substr($row['name'], 0, 250),
                    'source' => 'crm',
                    'crm_id' => $row['crm_id'],
                    'crm_list' => isset($row['list']) ? mb_substr($row['list'], 0, 120) : null,
                ]);
            }

            if (! empty($row['email']) && ! $business->contacts()->where('email', $row['email'])->exists()) {
                $business->contacts()->create(['email' => mb_strtolower($row['email'])]);
            }

            $needsAudit = is_null($business->score) || ($data['audit_again'] ?? false);
            if ($needsAudit && $business->audit_status !== 'queued') {
                $toAudit[] = $business;
            }
        }

        if (Claude::isConfigured()) {
            $counts['queued'] = AuditBusiness::queueFor($toAudit);
        }

        return response()->json($counts + [
            'note' => Claude::isConfigured()
                ? 'Audits run in the background, about one a minute per worker run. Read results from GET /api/crm/leads.'
                : 'Claude is not connected in Cloyne, so nothing was audited yet.',
        ]);
    }

    /** Cloyne → CRM: results for the CRM's rows, changed since a time (or for given IDs). */
    public function leads(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
            'crm_ids' => ['nullable', 'string', 'max:20000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = Business::whereNotNull('crm_id')->with('latestAudit')->orderBy('updated_at')->orderBy('id');

        if ($request->filled('crm_ids')) {
            $query->whereIn('crm_id', array_filter(array_map('trim', explode(',', $request->query('crm_ids')))));
        } elseif ($request->filled('since')) {
            $query->where('updated_at', '>', Carbon::parse($request->query('since')));
        }

        $rows = $query->limit((int) $request->query('limit', 500))->get();

        return response()->json([
            'leads' => $rows->map(fn (Business $b) => $this->result($b))->values(),
            'next_since' => optional($rows->last()?->updated_at)->toIso8601String(),
            'packages' => collect(Business::packages())->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name'], 'short' => $p['short'] ?? $p['name']])->values(),
        ]);
    }

    /** Cloyne → CRM: leads that pass Settings → CRM connection (score or package), with a phone, not in the CRM yet, best first. */
    public function callList(Request $request): JsonResponse
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:500']]);
        $limit = (int) $request->query('limit', 200);

        $rows = Business::forCrmCallList()
            ->with('latestAudit')
            ->orderByDesc('score')->orderByDesc('review_count')
            ->limit($limit)
            ->get();

        return response()->json(['leads' => $rows->map(fn (Business $b) => $this->result($b))->values()]);
    }

    /** CRM → Cloyne: "I added these to my call list", with the CRM's own IDs. */
    public function callListAdded(Request $request): JsonResponse
    {
        $data = $request->validate([
            'added' => ['required', 'array', 'min:1', 'max:500'],
            'added.*.cloyne_id' => ['required', 'integer'],
            'added.*.crm_id' => ['required', 'string', 'max:64', 'distinct'],
            'added.*.list' => ['nullable', 'string', 'max:120'],
        ]);

        $linked = 0;
        foreach ($data['added'] as $row) {
            $taken = Business::where('crm_id', $row['crm_id'])->where('id', '!=', $row['cloyne_id'])->exists();
            if (! $taken) {
                $linked += Business::whereKey($row['cloyne_id'])->update([
                    'crm_id' => $row['crm_id'],
                    'crm_list' => isset($row['list']) ? mb_substr($row['list'], 0, 120) : null,
                ]);
            }
        }

        return response()->json(['linked' => $linked]);
    }

    /** CRM → Cloyne: call results. Booked leads are marked booked; "not interested" leads are excluded. */
    public function calls(Request $request): JsonResponse
    {
        $data = $request->validate([
            'calls' => ['required', 'array', 'min:1', 'max:500'],
            'calls.*.crm_id' => ['required_without:calls.*.cloyne_id', 'nullable', 'string', 'max:64'],
            'calls.*.cloyne_id' => ['required_without:calls.*.crm_id', 'nullable', 'integer'],
            'calls.*.crm_call_id' => ['nullable', 'string', 'max:64'],
            'calls.*.outcome' => ['required', Rule::in(array_keys(Call::OUTCOMES))],
            'calls.*.notes' => ['nullable', 'string', 'max:5000'],
            'calls.*.caller' => ['nullable', 'string', 'max:120'],
            'calls.*.called_at' => ['nullable', 'date'],
            'calls.*.call_back_on' => ['nullable', 'date'],
        ]);

        $saved = 0;
        $unknown = [];

        DB::transaction(function () use ($data, &$saved, &$unknown) {
            foreach ($data['calls'] as $row) {
                $business = ! empty($row['crm_id'])
                    ? Business::where('crm_id', $row['crm_id'])->first()
                    : Business::find($row['cloyne_id']);

                if (! $business) {
                    $unknown[] = $row['crm_id'] ?? $row['cloyne_id'];

                    continue;
                }

                $attributes = [
                    'business_id' => $business->id,
                    'outcome' => $row['outcome'],
                    'notes' => $row['notes'] ?? null,
                    'caller' => $row['caller'] ?? null,
                    'called_at' => isset($row['called_at']) ? Carbon::parse($row['called_at']) : now(),
                    'call_back_on' => $row['call_back_on'] ?? null,
                ];

                if (! empty($row['crm_call_id'])) {
                    Call::updateOrCreate(['crm_call_id' => $row['crm_call_id']], $attributes);
                } else {
                    Call::create($attributes);
                }
                $saved++;

                match ($row['outcome']) {
                    'booked' => $business->markBooked('call'),
                    'not_interested' => in_array($business->stage, ['booked', 'won', 'lost'], true) ? null : $business->exclude('Not interested'),
                    default => $business->touch(),
                };
            }
        });

        return response()->json(['saved' => $saved, 'unknown' => $unknown]);
    }

    /**
     * Cloyne → CRM: the email of every active Cloyne login, so the CRM shows "Open in Cloyne" only to
     * people who can sign in. Removed team members are deleted, so every user here is active.
     * Emails only: no names, roles, passwords or anything else.
     */
    public function users(): JsonResponse
    {
        return response()->json([
            'users' => User::orderBy('email')->pluck('email')
                ->map(fn ($email) => ['email' => mb_strtolower($email)])->values(),
        ]);
    }

    /** v4.0 Cloyne → CRM: booked calls, changed since a time, with everything the closer needs. */
    public function booked(Request $request): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date'], 'limit' => ['nullable', 'integer', 'min:1', 'max:500']]);

        $rows = Business::whereNotNull('booked_at')
            ->when($request->filled('since'), fn ($q) => $q->where('updated_at', '>', Carbon::parse($request->query('since'))))
            ->with(['latestAudit', 'calls', 'drafts' => fn ($d) => $d->whereNotNull('send_status')])
            ->orderBy('updated_at')->orderBy('id')
            ->limit((int) $request->query('limit', 200))->get();

        return response()->json([
            'booked' => $rows->map(fn (Business $b) => $this->result($b) + [
                'booked_at' => optional($b->booked_at)->toIso8601String(),
                'booked_via' => $b->booked_via,
                'crm_deal_id' => $b->crm_deal_id,
                'deal_stage' => $b->deal_stage,
                'audit' => collect($b->latestAudit?->checks ?? [])->map(fn ($c) => [
                    'title' => $c['title'] ?? '', 'detail' => $c['detail'] ?? '', 'has' => (bool) ($c['passed'] ?? $c['found'] ?? false),
                ])->values(),
                'thread' => $this->thread($b),
                'calls' => $b->calls->map(fn ($c) => [
                    'outcome' => $c->outcome, 'caller' => $c->caller, 'notes' => $c->notes, 'called_at' => optional($c->called_at)->toIso8601String(),
                ])->values(),
            ])->values(),
            'next_since' => optional($rows->last()?->updated_at)->toIso8601String(),
            'deal_stages' => array_keys(Business::DEAL_STAGES),
        ]);
    }

    /** v4.0 CRM → Cloyne: where each deal stands. won → the lead becomes a client; lost → lost. */
    public function deals(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deals' => ['required', 'array', 'min:1', 'max:500'],
            'deals.*.cloyne_id' => ['required_without:deals.*.crm_id', 'nullable', 'integer'],
            'deals.*.crm_id' => ['required_without:deals.*.cloyne_id', 'nullable', 'string', 'max:64'],
            'deals.*.crm_deal_id' => ['nullable', 'string', 'max:64'],
            'deals.*.stage' => ['required', Rule::in(array_keys(Business::DEAL_STAGES))],
            'deals.*.package' => ['nullable', 'string', 'max:40'],
            'deals.*.value' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'deals.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $saved = 0;
        $unknown = [];
        foreach ($data['deals'] as $row) {
            $business = ! empty($row['cloyne_id']) ? Business::find($row['cloyne_id']) : Business::where('crm_id', $row['crm_id'])->first();
            if (! $business) {
                $unknown[] = $row['cloyne_id'] ?? $row['crm_id'];

                continue;
            }
            $package = $row['package'] ?? null;
            if ($package && ! array_key_exists($package, Business::packages())) {
                // Accept "Growth" or "Growth — Ads + CRM" as well as the key.
                $package = collect(Business::packages())->search(fn ($p) => in_array(mb_strtolower($package), [mb_strtolower($p['short'] ?? ''), mb_strtolower($p['name'])], true)) ?: null;
            }
            $business->update(array_filter([
                'crm_deal_id' => $row['crm_deal_id'] ?? null,
                'deal_package' => $package,
                'deal_value' => $row['value'] ?? null,
                'deal_note' => isset($row['note']) ? mb_substr($row['note'], 0, 500) : null,
            ], fn ($v) => ! is_null($v)) + [
                'deal_stage' => $row['stage'],
                'deal_updated_at' => now(),
                'booked_at' => $business->booked_at ?? now(),
                'stage' => match ($row['stage']) { 'won' => 'won', 'lost' => 'lost', default => 'booked' },
            ]);
            $saved++;
        }

        return response()->json(['saved' => $saved, 'unknown' => $unknown]);
    }

    /** The emails between LaiQ and this business, oldest first. */
    private function thread(Business $b): array
    {
        $sent = $b->drafts->map(fn ($d) => ['direction' => 'sent', 'at' => optional($d->queued_at)->toIso8601String(), 'subject' => $d->subject, 'text' => $d->body]);
        $received = \App\Models\Reply::where('business_id', $b->id)->where('is_auto_reply', false)->orderBy('received_at')->get()
            ->flatMap(fn ($r) => array_filter([
                ['direction' => 'received', 'at' => optional($r->received_at)->toIso8601String(), 'subject' => $r->subject, 'text' => $r->body],
                $r->answer_status === 'sent' ? ['direction' => 'sent', 'at' => optional($r->answered_at)->toIso8601String(), 'subject' => 'Re: '.$r->subject, 'text' => $r->answer] : null,
            ]));

        return $sent->concat($received)->sortBy('at')->values()->all();
    }

    /** One lead as the CRM sees it. */
    private function result(Business $b): array
    {
        $audit = $b->latestAudit;
        $packages = Business::packages();
        $package = fn (?string $key) => $key ? ['key' => $key, 'name' => $packages[$key]['name'] ?? $key, 'short' => $packages[$key]['short'] ?? $key] : null;

        $status = match (true) {
            ! is_null($b->score) => 'audited', // a re-audit in progress keeps showing the last result
            $b->audit_status === 'queued' => 'auditing',
            $b->audit_status === 'failed' => 'failed',
            default => 'waiting',
        };

        $issues = collect($audit?->checks ?? [])->where('passed', false)->sortByDesc('points')->pluck('title')->take(3)->values();

        return [
            'crm_id' => $b->crm_id,
            'cloyne_id' => $b->id,
            'name' => $b->displayName(),
            'phone' => $b->phone,
            'phone_display' => $b->phoneDisplay(),
            'website' => $b->website,
            'category' => $b->category,
            'city' => $b->city,
            'state' => $b->state,
            'rating' => $b->rating ? (float) $b->rating : null,
            'review_count' => $b->review_count,
            'status' => $status,
            'error' => $status === 'failed' ? $b->audit_error : null,
            'score' => $b->score,
            'hot' => ! is_null($b->score) && $b->score >= (int) config('cloyne.outreach.min_score', 60),
            'package' => $package($b->package),
            'pitch_package' => $package($b->pitchPackage()),
            'pitch' => $audit?->pitch,
            'issues' => $issues,
            'stage' => $b->stage,
            'audited_at' => optional($audit?->audited_at)->toIso8601String(),
            'updated_at' => optional($b->updated_at)->toIso8601String(),
            'cloyne_url' => route('leads', ['lead' => $b->id]),
            // v2.1: what the caller says. null until written (a few minutes after the audit).
            'call_script' => $b->call_script ? [
                'opener' => $b->call_script['opener'] ?? '',
                'objections' => $b->call_script['objections'] ?? [],
                'goal' => $b->call_script['goal'] ?? 'Book a 15-minute strategy call',
                'booking_url' => $b->call_script['booking_url'] ?? config('cloyne.outreach.booking_url'),
                'written_at' => optional($b->call_script_at)->toIso8601String(),
            ] : null,
        ];
    }

    private function stateCode(?string $state): ?string
    {
        $state = trim((string) $state);

        return strlen($state) === 2 ? strtoupper($state) : null;
    }
}
