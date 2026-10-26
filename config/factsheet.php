<?php

/*
| LaiQ fact sheet: the ONLY things the reply agent (version 3.0) may tell a business in an email.
| Each entry is a question people ask and LaiQ's approved answer. The Owner edits these in
| Settings → Fact sheet; saved edits override this file. Keep answers short and true.
| Anything not answered here, or on the handoff list, goes to a person.
*/

return [
    'entries' => [
        ['q' => 'What does LaiQ Digital do?', 'a' => 'We help local businesses get more customers: websites that turn visitors into calls and bookings, Meta and Google ads, and a CRM that texts every new lead back right away and follows up for you.'],
        ['q' => 'Who do you work with?', 'a' => 'Local service businesses of all kinds: contractors, dentists, med spas, gyms, restaurants, law firms and more.'],
        ['q' => 'How did you find me?', 'a' => 'We look at local businesses on Google Maps and review their websites. Yours came up when we looked at your area, and we noticed something we could help with.'],
        ['q' => 'What happens on the call?', 'a' => 'A short call with Aubre’, our CEO. We look at what you have now, where leads may be slipping through, and whether we can help. No pressure, and no obligation.'],
        ['q' => 'What does the website package include?', 'a' => 'A custom, mobile-friendly website with a lead or booking form, instant lead alerts to your phone and email, connection to your Google Business Profile and links to your booking app. Hosting with security, backups and small updates is optional.'],
        ['q' => 'What does the ads and CRM package include?', 'a' => 'Meta ads in one service area with the creatives made for you, a CRM that texts new leads back instantly, automated follow-up, review requests, Google Business Profile optimization and a monthly report. You own the ad account; we get partner access.'],
        ['q' => 'What does the full system include?', 'a' => 'Everything in the ads and CRM package, plus a 24/7 AI receptionist that texts back and books leads, Meta and Google ads in several areas, a full website, local SEO, monthly social posts and reels, help responding to reviews, and check-in calls every two weeks.'],
        ['q' => 'Is there a contract?', 'a' => 'No long-term contracts. Monthly services are month to month on autopay.'],
        ['q' => 'Who pays for the ads?', 'a' => 'Ad spend is paid directly to Meta (and Google), separate from our fee, so you always see exactly what is spent. We suggest a budget on the call.'],
        ['q' => 'Can you guarantee results?', 'a' => 'We don’t promise specific numbers. We show you what’s working every month in a plain report, and you can stop any month.'],
        ['q' => 'Please stop emailing me.', 'a' => 'Understood. You won’t hear from us again. Sorry for the interruption.'],
    ],

    // Never answered by the reply agent: a person takes over (and is told why).
    'handoff' => [
        'Prices, quotes or discounts',
        'Complaints or anyone upset',
        'Legal questions, threats or anything about spam laws',
        'Anything the fact sheet doesn’t answer',
        'The third reply in a conversation',
    ],
];
