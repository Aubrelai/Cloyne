<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Seeder;

/**
 * Loads sample businesses so the Leads screen has something to show
 * before the Lead Finder is connected. Safe to run more than once.
 * Remove these rows any time with: php artisan migrate:fresh
 */
class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $names = array_column($this->samples(), 'name');

        // Rebuild the sample rows each time so they pick up changes to this file.
        // Also clears sample businesses from earlier versions.
        Business::whereIn('name', [...$names, 'Lone Star HVAC', 'Coastal Air Pros', 'Ridgeline Painting', 'Peak Electric'])->delete();

        foreach ($this->samples() as $sample) {
            $business = Business::create(
                collect($sample)->except(['contact', 'audit', 'pitch', 'package', 'pitch_package', 'stage'])->all()
            );

            if ($sample['contact']) {
                $business->contacts()->create($sample['contact']);
            }
            if ($sample['audit']) {
                $business->recordAudit($sample['audit'], $sample['pitch'] ?? null, $sample['package'] ?? null, $sample['pitch_package'] ?? null);
            }
            $business->update(['stage' => $sample['stage']]);
        }
    }

    private function check(string $title, string $detail, bool $passed, int $points): array
    {
        return compact('title', 'detail', 'passed', 'points');
    }

    private function samples(): array
    {
        return [
            [
                'name' => 'Sunshine Roofing Co.', 'category' => 'Roofing', 'city' => 'Wellington', 'state' => 'FL',
                'phone' => '(561) 555-0142', 'website' => 'https://example.com/sunshine-roofing',
                'rating' => 4.8, 'review_count' => 126, 'license_status' => 'Active', 'license_expires_on' => '2027-08-31',
                'stage' => 'draft_ready',
                'contact' => ['name' => 'Mike Torres', 'role' => 'Owner', 'email' => 'mike@example.com', 'email_status' => 'verified'],
                'audit' => [
                    $this->check('Active Meta ads', '3 ads running now', true, 25),
                    $this->check('Meta Pixel installed', 'Already running tracked ads', true, 15),
                    $this->check('No lead form or booking', 'Paid clicks have no way to book', false, 15),
                    $this->check('Strong reviews, dated site', '4.8 stars, not mobile-friendly', true, 15),
                    $this->check('License active', 'From state license records', true, 10),
                ],
                'pitch' => 'Paying for Meta clicks that land on a site with no way to book.',
                'package' => 'ads_crm',
                'pitch_package' => 'website',
            ],
            [
                'name' => 'Bayside Plumbing', 'category' => 'Plumbing', 'city' => 'Tampa', 'state' => 'FL',
                'phone' => '(813) 555-0187', 'rating' => 4.9, 'review_count' => 212, 'stage' => 'emailed',
                'contact' => ['name' => 'Dana Reyes', 'role' => 'Owner', 'email' => 'dana@example.com', 'email_status' => 'verified'],
                'audit' => [
                    $this->check('Meta Pixel installed', 'Tracking set up, no active ads', true, 15),
                    $this->check('No lead form or booking', 'Phone number only', false, 15),
                    $this->check('Strong reviews, dated site', '4.9 stars, slow site', true, 15),
                    $this->check('Google Ads tag present', 'Has a marketing budget', true, 5),
                    $this->check('License active', 'From state license records', true, 10),
                    $this->check('No video on homepage', 'Useful for the creative pitch', false, 0),
                ],
                'pitch' => 'Great reviews, slow site with no form: start with a new site, then ads.',
                'package' => 'ads_crm',
                'pitch_package' => 'website',
            ],
            [
                'name' => 'Bright Smile Dental', 'category' => 'Dentist', 'city' => 'Houston', 'state' => 'TX',
                'phone' => '(713) 555-0110', 'rating' => 4.6, 'review_count' => 88, 'stage' => 'audited',
                'contact' => ['name' => 'Chris Nguyen', 'role' => 'Manager', 'email' => 'chris@example.com', 'email_status' => 'verified'],
                'audit' => [
                    $this->check('Active Meta ads', '1 ad running now', true, 25),
                    $this->check('Meta Pixel installed', 'Already running tracked ads', true, 15),
                    $this->check('No lead form or booking', 'Contact form is broken', false, 15),
                    $this->check('Google Ads tag present', 'Has a marketing budget', true, 5),
                    $this->check('Strong reviews, weak site', 'Site looks fine', false, 0),
                ],
                'pitch' => 'Running ads into a broken contact form.',
                'package' => 'scale',
                'pitch_package' => 'website',
            ],
            [
                'name' => 'Iron Peak Fitness', 'category' => 'Gym', 'city' => 'Denver', 'state' => 'CO',
                'phone' => '(303) 555-0164', 'rating' => 4.7, 'review_count' => 54, 'stage' => 'booked',
                'contact' => ['name' => 'Jordan Blake', 'role' => 'Owner', 'email' => 'jordan@example.com', 'email_status' => 'verified'],
                'audit' => [
                    $this->check('Meta Pixel installed', 'Tracking set up, no active ads', true, 15),
                    $this->check('No lead form or booking', 'Phone number only', false, 15),
                    $this->check('Strong reviews, dated site', '4.7 stars, not mobile-friendly', true, 15),
                    $this->check('Recently opened', 'On Google for 2 years', true, 10),
                    $this->check('Google Ads tag present', 'Has a marketing budget', true, 5),
                    $this->check('Active Meta ads', 'None running', false, 0),
                ],
                'pitch' => 'Good reviews, but the site gives visitors no way to book.',
                'package' => 'website',
            ],
            [
                'name' => 'Glow Med Spa', 'category' => 'Med spa', 'city' => 'Miami', 'state' => 'FL',
                'rating' => 4.4, 'review_count' => 39, 'stage' => 'audited',
                'contact' => ['name' => null, 'role' => null, 'email' => 'info@example.com', 'email_status' => 'risky'],
                'audit' => [
                    $this->check('Active Meta ads', '2 ads running now', true, 25),
                    $this->check('Meta Pixel installed', 'Already running tracked ads', true, 15),
                    $this->check('Google Ads tag present', 'Has a marketing budget', true, 5),
                    $this->check('Has a booking form', 'Already captures leads', true, 0),
                    $this->check('Strong reviews, weak site', 'Few reviews', false, 0),
                ],
                'pitch' => 'Already has a good site and booking: wait for Scale (ads in more areas, SEO, social).',
                'package' => 'scale',
                'pitch_package' => null,
            ],
            [
                'name' => 'Desert Sun Landscaping', 'category' => 'Landscaping', 'city' => 'Phoenix', 'state' => 'AZ',
                'rating' => 4.5, 'review_count' => 61, 'stage' => 'audited',
                'contact' => ['name' => null, 'role' => null, 'email' => 'hello@example.com', 'email_status' => 'verified'],
                'audit' => [
                    $this->check('No lead form or booking', 'Phone number only', false, 15),
                    $this->check('Strong reviews, dated site', '4.5 stars, dated site', true, 15),
                    $this->check('Google Ads tag present', 'Has a marketing budget', true, 5),
                    $this->check('Active Meta ads', 'None running', false, 0),
                    $this->check('Meta Pixel installed', 'Not found', false, 0),
                ],
                'pitch' => 'Strong reviews but no online way to book.',
                'package' => 'website',
            ],
            [
                'name' => 'Casa Verde Taqueria', 'category' => 'Restaurant', 'city' => 'Dallas', 'state' => 'TX',
                'rating' => 3.9, 'review_count' => 12, 'stage' => 'audited',
                'contact' => null,
                'audit' => [
                    $this->check('No lead form or booking', 'Phone number only', false, 15),
                    $this->check('No website at all', 'Google listing only', false, 10),
                    $this->check('Strong reviews, weak site', 'Only 12 reviews', false, 0),
                ],
                'pitch' => 'No website: offer the $500 site as a first step.',
                'package' => 'website',
            ],
            [
                'name' => 'Gulf Coast Pools', 'category' => 'Pools', 'city' => 'Sarasota', 'state' => 'FL',
                'rating' => 4.1, 'review_count' => 20, 'stage' => 'excluded',
                'contact' => ['name' => null, 'role' => null, 'email' => 'office@example.com', 'email_status' => 'bounced'],
                'audit' => [
                    $this->check('Has a booking form', 'Already captures leads', true, 0),
                    $this->check('Google Ads tag present', 'Has a marketing budget', true, 5),
                    $this->check('Active license', 'From state license records', true, 10),
                    $this->check('Meta Pixel installed', 'Not found', false, 0),
                    $this->check('Email bounced', 'Excluded from outreach', false, 0),
                ],
                'pitch' => null,
            ],
        ];
    }
}
