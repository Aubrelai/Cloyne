<?php

/*
| Cloyne versions, newest first. The top one shows under "Cloyne" in the sidebar;
| all of them are on the What's new page (/versions).
| Add a new entry at the top with every update people will notice.
*/

return [
    ['version' => '2.0', 'date' => '2026-10-26', 'title' => 'Sending (built, switched off)', 'notes' => [
        'Campaigns: approved emails go out through Instantly as a 3-email sequence (day 0, 3, 7) on weekdays, at most 30 a day per inbox. Any reply stops it.',
        'Nothing sends until the checklist is done (Instantly key, inboxes connected, warm-up score 80+, mailing address) and the Owner clicks Start sending.',
        'Claude now writes the day-3 and day-7 follow-ups with each email; edit them in the Review queue.',
        'Inbox: replies come back every 10 minutes. "No thanks", "stop" and unsubscribes exclude the business and block the address. Bounced addresses are never used again.',
        'Sending pauses by itself if more than 2% of last week\'s emails bounce or 5% opt out.',
    ]],
    ['version' => '1.11', 'date' => '2026-10-26', 'title' => 'Fact sheet for replies', 'notes' => [
        'Settings → Fact sheet: the approved answers (what LaiQ does, each package, contracts, ad spend, how the call works) that the reply agent may use in version 3.0.',
        'Prices, complaints, legal questions and anything not on the sheet always go to a person.',
        'The Owner can edit, add or remove answers, or reset to LaiQ defaults.',
    ]],
    ['version' => '1.10', 'date' => '2026-10-26', 'title' => 'Updates install themselves', 'notes' => [
        'The server checks GitHub every 15 minutes and installs new versions by itself. No more Terminal for updates.',
        'Settings → Updates shows the version running, when it last checked and installed, and lets the Owner turn it off.',
        'If an update stops part way, Settings says so and the server keeps retrying.',
    ]],
    ['version' => '1.9', 'date' => '2026-10-25', 'title' => "What's working", 'notes' => [
        'Dashboard: this month from found to audited, hot, called, talked and booked, with the share that made it to each step.',
        'Results per caller (calls, conversations, bookings, booked per call) and per category, from the call results the CRM sends.',
        'What Apify and Claude cost this month, and the cost per booked call.',
    ]],
    ['version' => '1.8', 'date' => '2026-10-25', 'title' => 'Real emails only', 'notes' => [
        'With a MillionVerifier key, Cloyne checks each email is real before writing to it. Invalid ones are never used; "catch-all" ones are marked Risky.',
        'Leads whose only email failed the check drop out of the email queue.',
        'Settings shows MillionVerifier with a Test button and how many emails were checked this month. It stays off until you add a key.',
    ]],
    ['version' => '1.7', 'date' => '2026-10-25', 'title' => 'Sharper audits', 'notes' => [
        'Audits weigh Google reviews against the website: a well-loved business with a weak site is flagged as a top prospect, in a "Reviews vs website" finding and in the talking point.',
        'Leads are re-audited automatically once their audit is 60 days old (up to 100 a night, oldest first), so scores and packages stay current.',
    ]],
    ['version' => '1.6', 'date' => '2026-10-25', 'title' => 'Clean-up and trust', 'notes' => [
        'The Dashboard says why audits failed (bad key, no credit, timeouts) and how many each.',
        'Retry all checks the Claude key first, so hundreds of audits don\'t fail the same way again.',
        'Leads: filter by audit (failed) and by where a lead came from (Lead Finder or CRM call lists); CRM leads carry a CRM tag.',
        'Export CSV downloads the leads you are looking at, ready for Excel or Google Sheets.',
    ]],
    ['version' => '1.5', 'date' => '2026-10-24', 'title' => 'Choose what goes to the CRM', 'notes' => [
        'Settings → CRM connection: set the minimum score for leads sent to the CRM call lists.',
        'Tick packages (Starter, Growth, Scale) whose leads always go, whatever their score.',
        'Shows how many leads are waiting for the CRM to pick up.',
    ]],
    ['version' => '1.4', 'date' => '2026-10-23', 'title' => 'CRM cold-call lists', 'notes' => [
        'The CRM can send its cold-call lists to Cloyne; Cloyne audits every business and sends back Starter, Growth or Scale, the score and a talking point.',
        'New hot leads Cloyne finds can go straight onto the CRM call lists.',
        'Call results from the CRM (no answer, booked, not interested…) show on each lead; booked leads count as booked, not-interested leads are excluded.',
        'Leads on CRM call lists are never cold-emailed by Cloyne.',
        'Settings → CRM connection holds the one key the CRM uses for all of this.',
        'The CRM can check who has a Cloyne login, so "Open in Cloyne" only shows for them.',
    ]],
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
