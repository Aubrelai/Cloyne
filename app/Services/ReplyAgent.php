<?php

namespace App\Services;

use App\Models\Reply;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * v3.0: sorts each reply (interested, question, not now, opt-out, other) and drafts an answer using ONLY the
 * LaiQ fact sheet (config/factsheet.php, Settings → Fact sheet). A person approves every answer before it goes.
 * Prices, complaints, legal questions, anything the fact sheet doesn't answer, and the third reply in a
 * conversation always go to a person instead.
 */
class ReplyAgent
{
    public const CATEGORIES = [
        'interested' => 'Interested',
        'question' => 'Question',
        'not_now' => 'Not now',
        'opt_out' => 'Opt-out',
        'other' => 'Other',
    ];

    public function __construct(private Claude $claude) {}

    public static function isOn(): bool
    {
        return (bool) config('cloyne.settings.reply_agent_on', false);
    }

    public function handle(Reply $reply): Reply
    {
        Setting::applyToConfig();

        if ($reply->is_auto_reply) {
            return tap($reply)->update(['category' => 'other', 'answer_status' => 'skipped']);
        }
        if ($reply->opt_out) {
            // They asked us to stop: no answer, nothing more from us.
            return tap($reply)->update(['category' => 'opt_out', 'answer_status' => 'skipped']);
        }

        $earlier = Reply::where('id', '!=', $reply->id)->where('is_auto_reply', false)
            ->where(fn ($q) => $reply->business_id ? $q->where('business_id', $reply->business_id) : $q->where('from_email', $reply->from_email))
            ->where('received_at', '<=', $reply->received_at)->count();

        $result = $this->claude->callTool(
            model: config('services.anthropic.draft_model'),
            system: $this->systemPrompt(),
            userMessage: $this->brief($reply),
            tool: $this->tool(),
            maxTokens: 900,
            purpose: 'reply',
        );

        $category = array_key_exists($result['category'] ?? '', self::CATEGORIES) ? $result['category'] : 'other';
        $needsPerson = (bool) ($result['needs_person'] ?? true);
        $reason = $result['handoff_reason'] ?? null;
        if ($earlier >= 2) {
            [$needsPerson, $reason] = [true, 'Third reply in this conversation: time for a person.'];
        }
        $answer = $needsPerson || $category === 'opt_out' ? null : trim((string) ($result['answer'] ?? ''));

        $reply->update([
            'category' => $category,
            'opt_out' => $reply->opt_out || $category === 'opt_out',
            'needs_person' => $needsPerson,
            'handoff_reason' => $needsPerson ? Str::limit((string) ($reason ?: 'Needs a person.'), 250, '') : null,
            'answer' => $answer ?: null,
            'answer_status' => $category === 'opt_out' ? 'skipped' : ($answer ? 'drafted' : null),
            'answer_error' => null,
        ]);

        return $reply;
    }

    private function systemPrompt(): string
    {
        $o = config('cloyne.outreach');
        $facts = collect(config('factsheet.entries', []))->map(fn ($f) => "Q: {$f['q']}\nA: {$f['a']}")->implode("\n\n");
        $handoff = implode("\n", array_map(fn ($h) => "- {$h}", config('factsheet.handoff', [])));
        $never = implode("\n", array_map(fn ($rule) => "- {$rule}", $o['never']));

        return <<<PROMPT
        You read replies to {$o['company']}'s cold emails and draft the answer {$o['sender_name']} ({$o['sender_title']})
        would send. A person checks every answer before it goes.

        1. category: interested (wants to talk or book), question (asks something), not_now (later / busy / maybe),
           opt_out (stop, unsubscribe, not interested, remove me), other (anything else).
        2. needs_person = true when the reply touches any of these, or you are unsure in any way:
        {$handoff}
           Give handoff_reason in one short sentence. Then answer = null.
        3. Otherwise write answer, using ONLY the approved answers below. Never add facts, numbers, prices, dates,
           names or promises that aren't in them. If they're interested, thank them and give the booking link on its
           own line: {$o['booking_url']}. If not now, thank them and ask if it's alright to check back in a few months.
           Under 80 words, friendly and direct, plain text, no greeting beyond "Hi {first name}," and no sign-off
           (Cloyne adds it).
        Never:
        {$never}

        Approved answers (the only facts you may use):
        {$facts}
        PROMPT;
    }

    private function brief(Reply $reply): string
    {
        $business = $reply->business;
        $sent = $reply->draft;

        return json_encode(array_filter([
            'business' => $business?->displayName(),
            'category' => $business?->category,
            'our email they replied to' => $sent ? ['subject' => $sent->subject, 'body' => $sent->body] : null,
            'their reply' => ['from' => $reply->from_email, 'subject' => $reply->subject, 'text' => Str::limit((string) $reply->body, 4000)],
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\nSort it and draft the answer with the record_reply tool.";
    }

    private function tool(): array
    {
        return [
            'name' => 'record_reply',
            'description' => 'Record how this reply is sorted and the drafted answer.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'category' => ['type' => 'string', 'enum' => array_keys(self::CATEGORIES)],
                    'needs_person' => ['type' => 'boolean'],
                    'handoff_reason' => ['type' => ['string', 'null']],
                    'answer' => ['type' => ['string', 'null']],
                ],
                'required' => ['category', 'needs_person', 'answer'],
            ],
        ];
    }
}
