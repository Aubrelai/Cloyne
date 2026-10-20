<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Visits a business's website (home page + contact page) and records
 * plain facts Claude can judge: tracking tags, forms, booking tools,
 * mobile setup, emails, social links and a short text sample.
 */
class WebsiteScanner
{
    private const MAX_BYTES = 1_500_000;

    private const BOOKING_TOOLS = [
        'calendly.com', 'vagaro.com', 'squareup.com/appointments', 'square.site', 'booksy.com', 'acuityscheduling.com',
        'mindbodyonline.com', 'fresha.com', 'housecallpro.com', 'jobber.com', 'opentable.com', 'resy.com',
        'zocdoc.com', 'setmore.com', 'schedulicity.com', 'glossgenius.com', 'servicetitan.com', 'gohighlevel', 'leadconnectorhq.com',
    ];

    private const FREE_BUILDERS = [
        'wixsite.com', 'business.site', 'godaddysites.com', 'weebly.com', 'square.site', 'wordpress.com',
        'carrd.co', 'webflow.io', 'sites.google.com', 'myshopify.com', 'jimdosite.com', 'linktr.ee',
    ];

    private const SOCIAL_ONLY = ['facebook.com', 'instagram.com', 'linktr.ee', 'yelp.com', 'nextdoor.com', 'tiktok.com'];

    /** @return array<string, mixed> */
    public function scan(?string $url): array
    {
        if (blank($url)) {
            return ['has_website' => false, 'reason' => 'No website listed on Google'];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (Str::contains($host, self::SOCIAL_ONLY)) {
            return ['has_website' => false, 'reason' => "Google listing links to {$host}, not a real website", 'url' => $url];
        }

        $home = $this->fetch($url);
        if (! $home['ok']) {
            return ['has_website' => true, 'reachable' => false, 'url' => $url, 'error' => $home['error']];
        }

        $html = $home['html'];
        $contactUrl = $this->findContactPage($html, $home['final_url']);
        $contactHtml = $contactUrl ? ($this->fetch($contactUrl)['html'] ?? '') : '';
        $allHtml = $html . "\n" . $contactHtml;
        $lower = strtolower($allHtml);

        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title);
        preg_match_all('/©\s*(?:\d{4}\s*[-–]\s*)?(20\d{2})|copyright\s*(?:\d{4}\s*[-–]\s*)?(20\d{2})/i', $html, $years);
        $copyrightYear = collect(array_merge($years[1] ?? [], $years[2] ?? []))->filter()->map(fn ($y) => (int) $y)->max();

        return [
            'has_website' => true,
            'reachable' => true,
            'url' => $home['final_url'],
            'https' => Str::startsWith($home['final_url'], 'https://'),
            'title' => isset($title[1]) ? Str::limit(trim(html_entity_decode(strip_tags($title[1]))), 120) : null,
            'free_builder_domain' => Str::contains(strtolower((string) parse_url($home['final_url'], PHP_URL_HOST)), self::FREE_BUILDERS),
            'built_with' => $this->platform($lower),
            'mobile_viewport_tag' => (bool) preg_match('/<meta[^>]+name=["\']viewport["\']/i', $html),
            'meta_pixel' => Str::contains($lower, ['fbevents.js', "fbq('init'", 'fbq("init"']),
            'google_tag' => Str::contains($lower, ['googletagmanager.com', 'gtag(', 'google-analytics.com']),
            'google_ads_tag' => (bool) preg_match('/aw-\d{6,}/i', $allHtml),
            'has_form' => (bool) preg_match('/<form[\s>]/i', $allHtml),
            'booking_tools' => array_values(array_filter(self::BOOKING_TOOLS, fn ($tool) => Str::contains($lower, $tool))),
            'phone_link' => Str::contains($lower, 'href="tel:') || Str::contains($lower, "href='tel:"),
            'chat_widget' => Str::contains($lower, ['tawk.to', 'intercom', 'drift.com', 'livechat', 'tidio', 'crisp.chat', 'podium']),
            'has_video' => Str::contains($lower, ['<video', 'youtube.com/embed', 'player.vimeo.com', 'wistia']),
            'facebook_page' => $this->firstMatch('/https?:\/\/(?:www\.)?facebook\.com\/(?!(?:sharer|share|tr|plugins|dialog)(?:[\/?.]|$))[a-z0-9.\-_\/]+/i', $allHtml),
            'instagram' => $this->firstMatch('/https?:\/\/(?:www\.)?instagram\.com\/[a-z0-9._\-]+/i', $allHtml),
            'copyright_year' => $copyrightYear,
            'contact_page' => $contactUrl,
            'emails' => $this->emails($allHtml, $home['final_url']),
            'text_sample' => $this->visibleText($html, 3500),
        ];
    }

    /** @return array{ok: bool, html?: string, final_url?: string, error?: string} */
    private function fetch(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->timeout(15)->connectTimeout(8)->get($url);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Site did not load (' . Str::limit($e->getMessage(), 80) . ')'];
        }

        if ($response->failed()) {
            return ['ok' => false, 'error' => 'Site returned HTTP ' . $response->status()];
        }

        $finalUrl = (string) ($response->effectiveUri() ?? $url);

        return ['ok' => true, 'html' => substr($response->body(), 0, self::MAX_BYTES), 'final_url' => $finalUrl];
    }

    private function findContactPage(string $html, string $baseUrl): ?string
    {
        if (! preg_match_all('/<a[^>]+href=["\']([^"\'#]+)["\'][^>]*>(.*?)<\/a>/is', $html, $links, PREG_SET_ORDER)) {
            return null;
        }
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        foreach ($links as [, $href, $text]) {
            if (! preg_match('/contact|get-in-touch|about/i', $href . ' ' . strip_tags($text))) {
                continue;
            }
            $absolute = $this->absoluteUrl($href, $baseUrl);
            if ($absolute && parse_url($absolute, PHP_URL_HOST) === $baseHost && $absolute !== $baseUrl) {
                return $absolute;
            }
        }

        return null;
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        $href = trim(html_entity_decode($href));
        if (Str::startsWith($href, ['mailto:', 'tel:', 'javascript:'])) {
            return null;
        }
        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }
        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (Str::startsWith($href, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $href;
        }

        return $root . '/' . ltrim($href, '/');
    }

    /** @return array<int, string> */
    private function emails(string $html, string $siteUrl): array
    {
        $html = html_entity_decode(str_ireplace(['[at]', '(at)', ' at '], '@', $html));
        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $html, $matches);
        $siteHost = preg_replace('/^www\./', '', strtolower((string) parse_url($siteUrl, PHP_URL_HOST)));

        return collect($matches[0])
            ->map(fn ($e) => strtolower(trim($e, '.')))
            ->reject(fn ($e) => preg_match('/\.(png|jpe?g|gif|webp|svg|css|js)$/', $e)
                || Str::contains($e, ['sentry', 'wixpress', 'example.com', 'domain.com', 'yourname', 'email.com', '@2x', 'godaddy', 'squarespace']))
            ->unique()
            // Emails on the business's own domain first.
            ->sortBy(fn ($e) => Str::endsWith($e, '@' . $siteHost) ? 0 : 1)
            ->take(5)
            ->values()
            ->all();
    }

    private function platform(string $lower): ?string
    {
        return match (true) {
            Str::contains($lower, 'wix.com') || Str::contains($lower, 'wixstatic') => 'Wix',
            Str::contains($lower, 'squarespace') => 'Squarespace',
            Str::contains($lower, 'wp-content') => 'WordPress',
            Str::contains($lower, 'shopify') => 'Shopify',
            Str::contains($lower, 'godaddy') || Str::contains($lower, 'img1.wsimg.com') => 'GoDaddy',
            Str::contains($lower, 'webflow') => 'Webflow',
            Str::contains($lower, 'weebly') => 'Weebly',
            Str::contains($lower, 'duda') => 'Duda',
            default => null,
        };
    }

    private function firstMatch(string $pattern, string $html): ?string
    {
        return preg_match($pattern, $html, $m) ? rtrim($m[0], '/') : null;
    }

    private function visibleText(string $html, int $limit): string
    {
        $html = preg_replace('/<(script|style|noscript|svg|iframe)[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = html_entity_decode(strip_tags((string) $html));

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $text)), $limit);
    }
}
