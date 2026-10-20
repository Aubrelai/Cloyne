<?php

namespace App\Support;

/**
 * Sample data so every screen renders before the database exists.
 * Replace each method with real queries (Eloquent models) as features are built.
 */
class SampleData
{
    public static function counts(): array
    {
        return ['review' => 12, 'inbox' => 3];
    }

    public static function stats(): array
    {
        return [
            ['label' => 'Leads', 'value' => '20,027', 'delta' => '+1,204 this week', 'trend' => 'up'],
            ['label' => 'Verified emails', 'value' => '3,412', 'delta' => '17% of leads', 'trend' => null],
            ['label' => 'Awaiting review', 'value' => '12', 'delta' => 'Oldest 3h ago', 'trend' => null],
            ['label' => 'Replies', 'value' => '9', 'delta' => '4.1% reply rate', 'trend' => 'up'],
            ['label' => 'Booked', 'value' => '4', 'delta' => '-1 vs last week', 'trend' => 'down', 'brand' => true],
        ];
    }

    public static function metros(): array
    {
        return [
            ['name' => 'Houston', 'count' => 2340],
            ['name' => 'Dallas', 'count' => 1980],
            ['name' => 'Miami', 'count' => 1610],
            ['name' => 'Tampa', 'count' => 1220],
            ['name' => 'Denver', 'count' => 940],
            ['name' => 'Phoenix', 'count' => 810],
        ];
    }

    /** Map dots: x/y are percentages across the map box. */
    public static function mapDots(): array
    {
        return [
            [47, 72, false], [49, 70, true], [48, 75, false], [50, 73, false],
            [46, 60, false], [47.5, 58, true], [45, 62, false],
            [77, 86, true], [78, 82, false], [76, 84, true], [73, 74, false], [74, 77, true],
            [32, 40, false], [33.5, 42, false], [22, 62, false], [23.5, 64, true],
            [60, 34, false], [70, 50, false], [85, 28, false], [10, 38, false],
            [66, 62, false], [58, 66, true], [8, 70, false], [52, 46, false],
        ];
    }

    public static function mapLabels(): array
    {
        return [
            ['Houston', 50, 80], ['Dallas', 49, 54], ['Miami', 80, 88],
            ['Tampa', 70, 70], ['Denver', 35, 36], ['Phoenix', 25, 66],
        ];
    }

    public static function searches(): array
    {
        return [
            ['name' => 'Dentists · Dallas metro, TX', 'meta' => 'Google Maps + license records · started 1h ago', 'found' => '612', 'verified' => '201', 'hot' => '38', 'status' => 'running', 'step' => 'Auditing websites', 'progress' => 64, 'repeats' => 'No'],
            ['name' => 'Med spas · all of Florida', 'meta' => 'Google Maps + license records · started 3h ago', 'found' => '1,840', 'verified' => '404', 'hot' => '—', 'status' => 'running', 'step' => 'Verifying emails', 'progress' => 22, 'repeats' => 'Weekly'],
            ['name' => 'Roofers · Palm Beach County, FL', 'meta' => 'Google Maps + license records · Oct 18', 'found' => '418', 'verified' => '97', 'hot' => '29', 'status' => 'done', 'repeats' => 'Weekly'],
            ['name' => 'Restaurants · Phoenix, AZ', 'meta' => 'Google Maps · Oct 16', 'found' => '350', 'verified' => '0', 'hot' => '—', 'status' => 'failed', 'repeats' => 'No'],
        ];
    }



    public static function drafts(): array
    {
        return [
            ['business' => 'Sunshine Roofing Co.', 'subject' => 'Quick question about your Meta ads', 'score' => 92],
            ['business' => 'Bayside Plumbing', 'subject' => 'Your 4.9 stars deserve a better site', 'score' => 88],
            ['business' => 'Lone Star HVAC', 'subject' => 'What made you stop running ads?', 'score' => 84],
            ['business' => 'Summit Garage Doors', 'subject' => 'Homeowners search before they call', 'score' => 81],
            ['business' => 'Clearwater Pest Control', 'subject' => 'Paying for clicks with no booking page', 'score' => 80],
            ['business' => 'Peak Electric', 'subject' => 'Follow-up: step 2 of 3', 'score' => 66],
        ];
    }

    public static function campaigns(): array
    {
        return [
            ['name' => 'Roofers · Florida', 'status' => 'Active', 'sent' => 412, 'replies' => 19, 'booked' => 3],
            ['name' => 'Plumbers · Texas', 'status' => 'Active', 'sent' => 186, 'replies' => 7, 'booked' => 1],
            ['name' => 'HVAC · Florida', 'status' => 'Paused', 'sent' => 0, 'replies' => 0, 'booked' => 0],
        ];
    }

    public static function steps(): array
    {
        return [
            ['day' => 'Day 0', 'title' => 'Email 1 · lead with their biggest gap', 'detail' => 'AI-written per lead from the audit · reviewed before sending', 'stats' => '412 sent · 14 replies'],
            ['day' => 'Day 3', 'title' => 'Email 2 · a second, different angle', 'detail' => 'Skipped if they replied', 'stats' => '298 sent · 4 replies'],
            ['day' => 'Day 7', 'title' => 'Email 3 · short final check-in', 'detail' => 'Then the lead is marked “No reply”', 'stats' => '121 sent · 1 reply'],
        ];
    }

    public static function inboxes(): array
    {
        return [
            ['address' => 'alex@getlaiq.com', 'usage' => '38 of 40 today', 'cap' => '40/day', 'health' => 'Healthy'],
            ['address' => 'sam@getlaiq.com', 'usage' => '35 of 40 today', 'cap' => '40/day', 'health' => 'Healthy'],
            ['address' => 'alex@trylaiq.com', 'usage' => 'Warm-up day 9 of 21', 'cap' => 'warming', 'health' => 'Warming'],
        ];
    }

    public static function replies(): array
    {
        return [
            ['name' => 'Mike Torres', 'business' => 'Sunshine Roofing Co.', 'when' => '12 min ago', 'preview' => "Yeah, we've been wondering why the ads..."],
            ['name' => 'Dana Reyes', 'business' => 'Bayside Plumbing', 'when' => '1h ago', 'preview' => 'Sure, Thursday afternoon works for me'],
            ['name' => 'Chris Nguyen', 'business' => 'Lone Star HVAC', 'when' => '3h ago', 'preview' => 'Send me more info on the CRM part'],
        ];
    }

    public static function replyFilters(): array
    {
        return [['Interested', 3, true], ['Question', 2, false], ['Not now', 3, false], ['Opt-out', 1, false], ['Needs a human', 1, false]];
    }

    public static function bookings(): array
    {
        return [
            ['business' => 'Sunshine Roofing Co.', 'contact' => 'Mike Torres · Wellington, FL', 'when' => 'Thu, Oct 22 · 10:00 AM', 'campaign' => 'Roofers · Florida', 'synced' => true, 'stage' => 'Booked'],
            ['business' => 'Bayside Plumbing', 'contact' => 'Dana Reyes · Tampa, FL', 'when' => 'Thu, Oct 22 · 2:30 PM', 'campaign' => 'Plumbers · Texas', 'synced' => true, 'stage' => 'Booked'],
            ['business' => 'Peak Electric', 'contact' => 'Jordan Blake · Denver, CO', 'when' => 'Fri, Oct 23 · 11:00 AM', 'campaign' => 'Roofers · Florida', 'synced' => false, 'stage' => '—'],
            ['business' => 'Gator Fence & Gate', 'contact' => 'Luis Ortega · Orlando, FL', 'when' => 'Mon, Oct 19 · 1:00 PM', 'campaign' => 'Roofers · Florida', 'synced' => true, 'stage' => 'Quoted'],
            ['business' => 'Brightline Solar', 'contact' => 'Priya Shah · Austin, TX', 'when' => 'Wed, Oct 14 · 3:00 PM', 'campaign' => 'Plumbers · Texas', 'synced' => true, 'stage' => 'Won'],
        ];
    }

    public static function funnel(): array
    {
        return [['Booked', 11, 'brand'], ['Showed', 7, null], ['Quoted', 4, null], ['Won', 2, 'success']];
    }

    public static function scoring(): array
    {
        return [
            ['Active Meta ads', 25], ['Meta Pixel on site', 15], ['No lead form or booking', 15],
            ['Strong reviews, weak site', 15], ['Active license, recently issued', 10], ['No website at all', 10],
        ];
    }
}
