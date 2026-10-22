<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Draft;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Writes the first outreach email for a lead, from its latest audit and the
 * outreach rules in config/cloyne.php. The draft waits in the Review queue.
 */
class Drafter
{
    public function __construct(private Claude $claude) {}

    /** Fill in a draft (creates one if needed). */
    /** Quick rewrites offered in the Review queue. */
    public const REWRITES = [
        'shorter' => 'Rewrite it shorter: 60 words or fewer, same gap, same call to action.',
        'casual' => 'Rewrite it more casual and conversational, like a quick note from one owner to another. Same gap, same call to action.',
        'angle' => 'Rewrite it built on a DIFFERENT gap from the audit findings than the previous version used.',
    ];

    public function write(Business $business, ?Draft $draft = null, ?string $rewrite = null): Draft
    {
        Setting::applyToConfig(); // the worker runs for hours; pick up rules changed in Settings
        $business->loadMissing(['latestAudit', 'primaryContact']);
        $contact = $business->contacts()->whereNotNull('email')->oldest()->first();

        $draft ??= $business->drafts()->create(['status' => 'writing']);
        $previous = $rewrite && $draft->body ? ['subject' => $draft->subject, 'body' => $draft->body, 'angle' => $draft->angle] : null;
        $draft->update(['status' => 'writing', 'error' => null]);

        $message = $this->leadBrief($business, $contact);
        if ($previous && isset(self::REWRITES[$rewrite])) {
            $message .= "\n\nPrevious version (angle: {$previous['angle']}):\nSubject: {$previous['subject']}\n{$previous['body']}\n\n"
                .self::REWRITES[$rewrite];
        }

        $model = config('services.anthropic.draft_model');
        $result = $this->claude->callTool(
            model: $model,
            system: $this->systemPrompt(),
            userMessage: $message,
            tool: $this->tool(),
            maxTokens: 1200,
        );

        $draft->update([
            'contact_id' => $contact?->id,
            'to_email' => $contact?->email,
            'subject' => Str::limit(trim((string) ($result['subject'] ?? '')), 120, ''),
            'body' => $this->cleanBody((string) ($result['body'] ?? '')),
            'angle' => Str::limit(trim((string) ($result['angle'] ?? '')), 250),
            'package' => $business->pitchPackage(),
            'model' => $model,
            'status' => 'pending',
            'edited' => false,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        if (in_array($business->stage, ['new', 'audited'], true)) {
            $business->update(['stage' => 'draft_ready']);
        }

        return $draft;
    }

    /** Remove any sign-off Claude adds anyway; Cloyne adds the signature and footer itself. */
    private function cleanBody(string $body): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", trim($body)));
        $o = config('cloyne.outreach');
        $first = Str::lower(explode(' ', trim($o['sender_name']))[0]);
        $signOff = '/^(best|thanks|thank you|cheers|regards|kind regards|talk soon|warmly|sincerely|all the best)\b.{0,25}$/i';

        // Drop up to 6 trailing lines that are a sign-off, the sender's name/title, or blank.
        for ($i = 0; $i < 6 && $lines; $i++) {
            $last = trim(end($lines));
            $lower = Str::lower($last);
            if ($last === '' || preg_match($signOff, $last)
                || ($first !== '' && str_starts_with($lower, $first))
                || $lower === Str::lower($o['sender_title']) || $lower === Str::lower($o['company'])) {
                array_pop($lines);
            } else {
                break;
            }
        }

        return trim(implode("\n", $lines));
    }

    private function systemPrompt(): string
    {
        $o = config('cloyne.outreach');
        $never = implode("\n", array_map(fn ($rule) => "- {$rule}", $o['never']));
        $packages = collect(Business::packages())
            ->filter(fn ($p) => $p['order'] !== null)
            ->map(fn ($p, $key) => "- {$p['name']} (key: {$key}): {$p['fits']} Includes: ".implode('; ', $p['includes'] ?? []))
            ->implode("\n");
        $cta = $o['booking_url']
            ? "End with one low-pressure question inviting a short call, then the booking link on its own line exactly as given: {$o['booking_url']}"
            : 'End with one low-pressure question asking them to reply if they want to talk. No links.';

        return <<<PROMPT
        You write first cold emails for {$o['company']}, a marketing agency for local businesses. The email comes from
        {$o['sender_name']} ({$o['sender_title']}).

        LaiQ's packages (for your understanding only; never quote prices):
        {$packages}

        You get one business: its Google details, the AI audit of its website (what it has and what's missing), the
        package to pitch, and an opening line idea. Write ONE email built on that business's single biggest, most
        concrete gap from the audit, and how fixing it gets them more customers.

        Tone: {$o['tone']}

        Rules:
        - Under {$o['max_words']} words in the body. Short paragraphs (1–3 sentences). Plain text, no bullet lists, no bold.
        - Greet the contact by first name if one is given, otherwise "Hi {Business name} team,".
        - Mention one specific, checkable fact from the audit so it's clearly about them (e.g. "your site doesn't
          resize on phones", "there's no way to book online"). Only use facts in the data. Never invent anything.
        - Point the gap out kindly and factually; never insult the business or the website.
        - Say in one sentence what LaiQ would do about it, in plain words, without naming the package or listing features.
        - {$cta}
        - Do NOT add a sign-off, name, signature, address or unsubscribe line. Cloyne adds those.
        - Subject line: 2–6 words, lowercase is fine, specific to them, no clickbait, no emoji, no "quick question".
        Never:
        {$never}
        PROMPT;
    }

    private function leadBrief(Business $business, $contact): string
    {
        $audit = $business->latestAudit;
        $packageKey = $business->pitchPackage();
        $package = $packageKey ? (Business::packages()[$packageKey] ?? null) : null;

        $data = array_filter([
            'business' => $business->displayName(),
            'category' => $business->google_category ?: $business->category,
            'location' => $business->location() ?: null,
            'website' => $business->website ?: 'none',
            'google rating' => $business->rating ? "{$business->rating} stars from {$business->review_count} reviews" : null,
            'contact first name' => $contact?->name ? explode(' ', trim($contact->name))[0] : null,
            'package to pitch' => $package ? $package['name'].' – '.$package['fits'] : null,
            'opening line idea' => $audit?->pitch,
            'audit findings' => collect($audit?->checks ?? [])
                ->sortByDesc('points')
                ->map(fn ($c) => ($c['passed'] ? 'HAS ' : 'MISSING ').$c['title'].': '.$c['detail'])
                ->values()->all(),
        ], fn ($v) => ! is_null($v) && $v !== []);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ."\n\nWrite the email with the record_email tool.";
    }

    private function tool(): array
    {
        return [
            'name' => 'record_email',
            'description' => 'Record the first outreach email for this business.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'angle' => ['type' => 'string', 'description' => 'The one gap this email is built on, in under 12 words.'],
                    'subject' => ['type' => 'string'],
                    'body' => ['type' => 'string', 'description' => 'Greeting through the call to action. No sign-off or signature.'],
                ],
                'required' => ['angle', 'subject', 'body'],
            ],
        ];
    }
}
