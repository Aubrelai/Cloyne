<?php

/*
|--------------------------------------------------------------------------
| LaiQ Digital packages
|--------------------------------------------------------------------------
| Every lead Cloyne audits is matched to the package that fits it best.
| Edit names and prices here; the key (left side) is what's saved in the
| database, so don't rename a key once leads use it. Add new packages freely.
*/

return [

    'packages' => [
        'website' => [
            'name' => 'Website',
            'price' => '$500 setup',
            'fits' => 'No website, or a site that is dated, slow or not mobile-friendly.',
        ],
        'ads_crm' => [
            'name' => 'Meta ads + CRM',
            'price' => '$750 setup, then $1,500–2,000/mo',
            'fits' => 'Has a decent site but no ads, or runs ads with no lead form, booking or follow-up.',
        ],
        'scale' => [
            'name' => 'Scale',
            'price' => '[PRICE]',
            'fits' => 'Already advertising and growing; needs the full system: site, Meta ads and CRM.',
        ],
    ],

];
