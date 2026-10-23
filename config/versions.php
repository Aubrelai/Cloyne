<?php

/*
| Cloyne versions, newest first. The top one shows under "Cloyne" in the sidebar;
| all of them are on the What's new page (/versions).
| Add a new entry at the top with every update people will notice.
*/

return [
    ['version' => '1.3', 'date' => '2026-10-23', 'title' => 'Costs and versions', 'notes' => [
        'Settings shows what Apify and Claude cost this month.',
        'The LaiQ Digital CRM can read those costs for its Profit page, with a private key.',
        'Version number under the Cloyne name, and this What\'s new page.',
        'Plain message when Apify\'s monthly limit is used up.',
    ]],
    ['version' => '1.2', 'date' => '2026-10-22', 'title' => 'Real lead map', 'notes' => [
        'The Dashboard map is a real map you can zoom and drag, centered on your leads.',
        'Click a dot to see the lead; hide or show hot and other leads from the legend.',
        'Searches for a US state (like "georgia") now search the US.',
        'New styles load right after every update.',
    ]],
    ['version' => '1.1', 'date' => '2026-10-22', 'title' => 'Online', 'notes' => [
        'Cloyne runs at cloyne.laiqdigital.com on DreamHost with a MySQL database.',
        'Audits, email drafts and Lead Finder results run in the background every minute.',
        'One script installs and updates the server.',
    ]],
    ['version' => '1.0', 'date' => '2026-10-21', 'title' => 'Small details', 'notes' => [
        'Confirm boxes, Undo, busy buttons, keyboard shortcuts, light and dark theme.',
        'Sortable Leads with saved views, exclude with a reason, bulk actions.',
        'Clean names, phones and links; duplicates merged and chains flagged.',
        'Email rules and packages on sale editable in Settings.',
    ]],
    ['version' => '0.6', 'date' => '2026-10-20', 'title' => 'Email drafts', 'notes' => [
        'Claude writes a first email for every hot lead; edit, approve, skip or rewrite it in the Review queue.',
    ]],
    ['version' => '0.5', 'date' => '2026-10-20', 'title' => 'Logins and roles', 'notes' => [
        'Owner, Reviewer and Viewer logins; the Owner adds the team in Settings.',
    ]],
    ['version' => '0.4', 'date' => '2026-10-20', 'title' => 'AI audit', 'notes' => [
        'Claude checks each website, scores the lead 0–100 and picks the best LaiQ package.',
        'Delete leads and searches; deleted businesses never come back.',
    ]],
    ['version' => '0.3', 'date' => '2026-10-20', 'title' => 'Lead Finder', 'notes' => [
        'Search any type of business in any area on Google Maps through Apify.',
    ]],
    ['version' => '0.2', 'date' => '2026-10-20', 'title' => 'Real leads', 'notes' => [
        'The Leads screen reads a real database, with filters, search and a lead profile.',
        'Every kind of business, matched to a LaiQ package.',
    ]],
    ['version' => '0.1', 'date' => '2026-10-20', 'title' => 'First screens', 'notes' => [
        'All eight Cloyne screens in the Cloyne design.',
    ]],
];
