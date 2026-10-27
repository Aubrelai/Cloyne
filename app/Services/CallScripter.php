<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * v2.1: writes a short call script for a lead from its latest audit: a 30-second opener built on one or two
 * real findings, answers to common pushback, and the goal (book a strategy call). Shown to callers in the
 * LaiQ Digital CRM (through GET /api/crm/leads and /api/crm/call-list) and on the lead in Cloyne.
 * Same rules as the emails: no prices, no promised results, no fake familiarity.
 */
class CallScripter
{
    public function __construct(private Claude $claude) {}

    public function write(Business $business): array
    {
        Setting::applyToConfig();
        $business->loadMissing('latestAudit');

        $result = $this->claude->callTool(
            model: config('services.anthropic.script_model'),
            system: $this->systemPrompt(),
            userMessage: $this->brief($business),
            tool: $this->tool(),
            maxTokens: 1200,
            purpose: 'script',
        );

        $script = [
            'opener' => trim((string) ($result['opener'] ?? '')),
            'objections' => collect($result['objections'] ?? [])
                ->filter(fn ($o) => filled($o['they_say'] ?? null) && filled($o['you_say'] ?? null))
                ->map(fn ($o) => ['they_say' => Str::limit(trim($o['they_say']), 120, ''), 'you_say' => Str::limit(trim($o['you_say']), 500, '')])
                ->take(6)->values()->all(),
            'goal' => 'Book a 15-minute strategy call',
            'booking_url' => config('cloyne.outreach.booking_url'),
        ];

        $business->update(['call_script' => $script, 'call_script_at' => now()]);

        return $script;
    }

    private function systemPrompt(): string
    {
        $o = config('cloyne.outreach');
        $never = implode("\n", array_map(fn ($rule) => "- {$rule}", $o['never']));

        return <<<PROMPT
        You write cold-call scripts for {$o['company']}'s callers, who phone local businesses to book a short strategy
        call with {$o['sender_name']} ({$o['sender_title']}). The caller reads it live, so it must sound like a person.

        You get one business: its Google details, the AI audit of its website and the package to pitch.

        Write:
        1. opener: what the caller says after "Hi, is this the owner? This is [name] with LaiQ Digital", about 30
           seconds spoken (60–90 words). Name one or two specific, checkable findings from the audit (good reviews,
           site not working on phones, no booking form…), say kindly why it costs them customers, and end with one
           yes/no question about whether they'd want to fix it. Use [name] where the caller says their own name.
        2. objections: 4 or 5 likely pushbacks for THIS business (e.g. "we already have a website guy", "just send me
           an email", "how much is it?", "we're busy enough", "not interested"), each with a short, calm answer
           (1–2 sentences) that steers toward the 15-minute call. "Not interested" always ends politely and ends the
           call. "How much is it?" is answered without any number: it depends, and that's what the call is for.

        Rules: plain spoken words, no jargon, no hype. Only facts in the data; never invent anything.
        Never:
        {$never}
        PROMPT;
    }

    private function brief(Business $business): string
    {
        $audit = $business->latestAudit;
        $key = $business->pitchPackage() ?? $business->package;
        $package = $key ? (Business::packages()[$key] ?? null) : null;

        return json_encode(array_filter([
            'business' => $business->displayName(),
            'category' => $business->google_category ?: $business->category,
            'location' => $business->location() ?: null,
            'website' => $business->website ?: 'none',
            'google rating' => $business->rating ? "{$business->rating} stars from {$business->review_count} reviews" : null,
            'package to pitch' => $package ? $package['name'].' – '.$package['fits'] : null,
            'opening line idea' => $audit?->pitch,
            'audit findings' => collect($audit?->checks ?? [])->sortByDesc('points')
                ->map(fn ($c) => (($c['passed'] ?? $c['found'] ?? false) ? 'HAS ' : 'MISSING ').$c['title'].': '.$c['detail'])->values()->all(),
        ], fn ($v) => ! is_null($v) && $v !== []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ."\n\nWrite the call script with the record_call_script tool.";
    }

    private function tool(): array
    {
        return [
            'name' => 'record_call_script',
            'description' => 'Record the cold-call script for this business.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'opener' => ['type' => 'string'],
                    'objections' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'they_say' => ['type' => 'string'], 'you_say' => ['type' => 'string'],
                    ], 'required' => ['they_say', 'you_say']]],
                ],
                'required' => ['opener', 'objections'],
            ],
        ];
    }
}
