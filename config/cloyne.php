<?php

/*
|--------------------------------------------------------------------------
| LaiQ Digital packages
|--------------------------------------------------------------------------
| Every lead Cloyne audits is matched to the package that fits it best.
| The "signs" and "not_fit" lists are the rules the AI audit uses (Step 3).
|
| - Edit names, prices and rules freely.
| - Don't rename a key (left side) once leads use it: it's saved in the database.
| - "order" sets the ladder from smallest to biggest. Packages without an
|   order (custom work) sit outside the ladder and are never auto-recommended.
| - Anything still undecided is marked TBD.
*/

return [

    /*
    | Which packages LaiQ is selling right now. Cloyne still shows the package
    | each business fits best, but only *pitches* packages in this list.
    | To pause one, list the others in .env, e.g. CLOYNE_SELLING=website
    */
    'selling' => array_filter(array_map('trim', explode(',', env('CLOYNE_SELLING', 'website,ads_crm,scale')))),

    'packages' => [

        'website' => [
            'name' => 'Starter — Website',
            'short' => 'Starter',
            'order' => 1,
            'price' => '$500 one-time + $39/mo hosting (optional)',
            'includes' => [
                'Custom website',
                'Lead capture / booking form',
                'Mobile-friendly design',
                'Instant lead alerts to phone & email',
                'Connected to Google Business Profile',
                'Links to their booking app (Vagaro, Square, etc.)',
                'Help registering a domain (client pays ~$15–20/yr)',
                'With hosting: security, backups and small updates',
            ],
            'fits' => 'Local businesses with no website, or a weak one, who aren’t ready for monthly ads.',
            'signs' => [
                'No website (Google listing has no link, or links to Facebook/Instagram/Linktree)',
                'Site isn’t mobile-friendly',
                'Site looks outdated, broken, or the domain is dead or expired',
                'No contact, quote or booking form',
                'No clear phone number or call-to-action above the fold',
                'Free builder subdomain (e.g. wixsite.com, business.site)',
                'Hours, prices or services on the site don’t match Google',
            ],
            'not_fit' => [
                'Already has a modern, mobile-friendly site with a working lead/booking form',
                'Business is closed, or the listing is marked permanently closed',
                'Needs online payments or an e-commerce checkout (not offered)',
            ],
            'upsell' => 'ads_crm',
        ],

        'ads_crm' => [
            'name' => 'Growth — Ads + CRM',
            'short' => 'Growth',
            'order' => 2,
            'price' => '$1,500/mo + $750 setup (ad spend separate, ~$1,000–2,000/mo)',
            'includes' => [
                'Meta ads management (1 service area)',
                'Ad creatives made for you',
                'CRM with instant lead text-back',
                'Automated follow-up',
                'Review request automation',
                'Google Business Profile optimization',
                'Monthly report',
                'Client owns the ad account; LaiQ gets partner access',
            ],
            'fits' => 'Businesses with a decent website that want steady Meta ad leads plus a CRM that follows up, in one service area.',
            'signs' => [
                'Working website but no active Meta ads (Meta Ad Library)',
                'Running Meta ads with no booking/lead form, or ads point to a homepage with no next step',
                'Under 20 Google reviews, or few recent reviews',
                'Incomplete Google Business Profile (missing photos, hours, services or description)',
                'Facebook page inactive or barely posted to',
                'Slow or no reply to Facebook messages',
                'Serves one city or area',
            ],
            'not_fit' => [
                'Can’t support ~$2,500–3,500/mo total ($1,500 + at least $1,000 ad spend)',
                'No website at all (needs Starter first, or Scale)',
                'No Facebook page and unwilling to create one',
                'Business type Meta restricts for ads (list TBD)',
                'Already using another agency for ads + CRM, unless unhappy with it',
            ],
            'upsell' => 'scale',
        ],

        'scale' => [
            'name' => 'Scale — Full System',
            'short' => 'Scale',
            'order' => 3,
            'price' => '$2,000/mo + $1,250 setup + $39/mo hosting (ad spend separate)',
            'includes' => [
                'Everything in Growth',
                '24/7 AI receptionist that texts back and books leads',
                'Meta + Google ads management (multiple areas)',
                'Full website build',
                'Ongoing local SEO',
                'Monthly social posts & reels',
                'Review response help',
                'More ad campaigns & creative testing',
                'Bi-weekly check-in calls & reports',
            ],
            'fits' => 'Established businesses serving several areas that can fund Meta and Google ads and want ads, website and CRM handled together.',
            'signs' => [
                'Serves multiple cities, areas or locations',
                'Running Google or Meta ads on its own with weak landing pages or no follow-up',
                'Weak website and no ads, but signs of a bigger operation (multiple crews, many services, high-ticket jobs)',
                'Not in Google’s local map results for its main service and city',
                'Reviews go unanswered',
                'Social accounts inactive or rarely posting, no reels',
                'Strong local competitors clearly out-marketing them',
            ],
            'not_fit' => [
                'Can’t support roughly $4,000+/mo total (exact minimum TBD)',
                'Single-person or very small operation in one small area (Growth fits better)',
                'Mainly online or national, with no local service area',
            ],
            'upsell' => null,
        ],

        'other' => [
            'name' => 'Other — Custom work',
            'short' => 'Custom',
            'order' => null,
            'price' => 'Quoted per job',
            'includes' => ['Whatever is quoted'],
            'fits' => 'One-off work outside the three packages. Usually comes from the prospect asking.',
            'signs' => [],
            'not_fit' => [],
            'upsell' => null,
        ],
    ],

    /* Extras that ride on top of a package. Not recommended on their own. */
    'add_ons' => [
        'ai_receptionist' => [
            'name' => 'AI receptionist',
            'price' => 'TBD (included in Scale)',
            'includes' => '24/7 AI that texts back and books new leads from the client’s own number',
            'signs' => ['Misses calls', 'Slow to reply', 'No after-hours response'],
        ],
        'hosting' => [
            'name' => 'Hosting & upkeep',
            'price' => '$39/mo whenever LaiQ hosts the site',
            'includes' => 'Hosting, security, backups and small updates',
            'signs' => [],
        ],
    ],

];
