# Cloyne — project context

Cloyne is LaiQ Digital's internal lead engine (internal only, never client-facing). It finds any kind of business (any category, any area), audits their websites and ads with AI, scores them 0–100, matches each to the best-fit LaiQ package (defined in `config/cloyne.php`), drafts outreach for human review, sends through Instantly, handles replies, and pushes booked calls to the separate, custom-built LaiQ Digital CRM (PHP + MySQL) via an API.

Current state: all eight screens are Blade views. The Leads screen reads the real database (`Business`, `Contact`, `Audit` models; save audits with `Business::recordAudit()`, which also updates the cached `score` and `stage`). The Lead Finder is live: `SearchController` starts a Google Maps run on Apify (`App\Services\Apify`, token `APIFY_TOKEN`), `Search::sync()` checks it whenever the Lead Finder page loads, and `App\Services\LeadImporter` turns results into `Business` rows (deduped by Google place ID, closed places skipped). Searches run in the background on Apify, so no queue worker is needed yet. The AI audit is live: `App\Services\Auditor` runs `WebsiteScanner` (home + contact page: pixel, tags, forms, booking tools, viewport, emails, text sample), then asks Claude (`App\Services\Claude`, forced `record_audit` tool call, key `ANTHROPIC_API_KEY`, model `ANTHROPIC_AUDIT_MODEL`) to judge the business against the package rules, and saves via `recordAudit()`. Emails are only saved if the scan actually found them. Bulk audits run as `App\Jobs\AuditBusiness` on the database queue; `update.bat` opens a "Cloyne worker" window running `queue:work`. New leads are auto-queued when a search finishes (`CLOYNE_AUTO_AUDIT`). Tests block all real HTTP (`Http::preventStrayRequests()`) and blank the API keys in `phpunit.xml`. Leads can be deleted one at a time (profile) or in bulk (checkboxes, `LeadController`); deleting removes contacts and audits too, and records the Google place ID in `dismissed_places` so `LeadImporter` never re-adds it (all deletes go through `App\Services\LeadRemover`, which also removes finished searches left with no leads; a search can be deleted with all its leads from the Lead Finder). The Dashboard, Booked and Settings screens read real data. The Review queue is live (Step 4): `App\Services\Drafter` has Claude (`ANTHROPIC_DRAFT_MODEL`, forced `record_email` tool) write a first email for each hot lead from its latest audit and the rules in `config/cloyne.php` → `outreach` (sender, tone, never-say list, booking link `CLOYNE_BOOKING_URL`, mailing address `CLOYNE_MAILING_ADDRESS`). Drafts live in `drafts` (`App\Models\Draft`: writing → pending → approved/skipped, or failed); Cloyne adds the signature and CAN-SPAM footer itself (`Draft::signature()`, `Draft::footer()`), and `Draft::warnings()` flags prices, promises and fake familiarity. `Business::draftable()` = score 60+, has an email, pitches a package on sale, no draft in play. Hot leads get a draft automatically after their audit (`App\Jobs\WriteDraft`, `CLOYNE_AUTO_DRAFT`, off in tests). `DraftController` handles write, edit, approve, skip, rewrite and reopen; nothing sends until Step 5. Campaigns and Inbox show `<x-coming-soon>` empty states until their steps are built. Everything needs a login (`auth` + `fresh-password` middleware in `routes/web.php`). Roles live on `users.role`: `owner` (everything, including Settings → Team), `reviewer` (searches, audits, deletes; later approving emails), `viewer` (look only). Protect changing routes with `can:edit` and team routes with `can:manage-team` (gates in `AppServiceProvider`), and hide buttons with `@can('edit')`. The first visit with no users goes to `/setup` to create the Owner; after that `/setup` is closed. Owners add people in Settings → Team, which makes a temporary password; `must_change_password` holds them on `/account` until they pick their own. Locked out: `php artisan cloyne:owner you@email`. Feature tests sign in as an Owner automatically (`Tests\TestCase`); set `$signInAsOwner = false` to test as a guest. Small details (shared by every screen): `public/js/cloyne.js` (plain JS, no build) powers `data-confirm` forms (Cloyne-styled confirm box in `partials/dialogs`), `data-busy` buttons (lock + "Auditing…" while a POST runs), fading toasts with Undo (`session('undo')` → `partials/toasts`), `data-copy`, `data-show-password`, `data-strength`, `data-word-count`, `data-warn-unsaved`, `data-toggle-class`, `data-cost-per`, and keyboard shortcuts (`/`, `?`, `data-key-list` + `data-key-item` lists, `data-key="a"` buttons). Never use `onsubmit="return confirm()"`. Components: `<x-phone>` (click-to-call + copy), `<x-copy>`, `<x-time>` (relative, exact date in the user's time zone on hover), `<x-score :checks>` (point breakdown on hover). Data helpers in `app/Support`: `Phone` (store +1XXXXXXXXXX, show (305) 555-0100), `BusinessName::clean()` (use `$business->displayName()` in views and emails; `name` stays as Google has it), `WebAddress` (clean links, `website_host`), `FriendlyError::message()` for any error shown to people. `LeadImporter` merges duplicates by place ID, phone, or website+city, counts skipped results on the search, and flags chains (`is_chain`, 4+ listings sharing a website). Leads can be excluded with a reason (`Business::exclude()`, hidden unless `stage=excluded`) instead of deleted. Leads remembers its last filters in the session (`?clear=1` forgets), sorts (`sort`, `dir`), pages (`per`), and has saved views (`saved_views`, per person). Settings stores Owner edits in `settings` (`App\Models\Setting`), which override `config/cloyne.php`/.env via `Setting::applyToConfig()` (called at boot and before each audit/draft job). Users have `theme` (dark/light/system) and `timezone`. Online: DreamHost shared hosting at https://cloyne.laiqdigital.com, MySQL `cloyne` on mysql.admin.laiqdigital.com; `deploy/dreamhost.sh` installs and updates (app in `~/cloyne-app`, web root symlinked to its `public`, `.env` written with `deploy/set-env.php`, cron runs `schedule:run` every minute). No always-on worker there: `routes/console.php` schedules `queue:work --stop-when-empty --max-time=50` every minute and `cloyne:sync-searches` every two minutes. MySQL rejects values longer than their column, so trim imported text (`LeadImporter::short()`). Never commit server passwords or keys. There is no fake data in the app; `LeadSeeder` sample businesses are for tests only (never seeded by default). See README.md for the file map. Run `php artisan test` after changes.

Packages: `config/cloyne.php` holds every LaiQ package (Starter — Website, Growth — Ads + CRM, Scale — Full System, Other), with prices, what's included, the signs a business needs it, and the signs it's not a fit. The AI audit must use those lists. Each audit records `package` (best fit) and `pitch_package` (a smaller package that also genuinely fits). `CLOYNE_SELLING` in `.env` lists what's on sale; all three packages are on sale (default). `Business::pitchPackage()` decides what to pitch.

Design rules (from the Cloyne design system):
- Use the CSS variables in `public/css/cloyne.css`; never hard-code hex colors in views.
- Dark theme is the default. Crimson (`--brand`) is scarce: one primary action per region, hot leads (score 60+), the booked count.
- Cyan (`--signal`) means data and AI. Status colors (`--success`, `--warning`, `--danger`) always come with a word; danger is orange, never red.
- Fonts: Space Grotesk for numbers and titles, IBM Plex Sans for text, IBM Plex Mono for scores, phones and IDs.
- Reuse the Blade components in `resources/views/components/` (`x-stat`, `x-score`, `x-pill`, `x-icon`) before writing new markup.
- Copy is plain and direct: numbers first, sentence case, verbs on buttons, no emoji.

Stack decisions: Laravel + MySQL in production (SQLite locally is fine), Laravel queues on Redis for workers, hosted on Railway or Render.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
