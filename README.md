# Cloyne

LaiQ Digital's internal lead engine: it finds businesses in any trade and area, audits their websites and ads with AI, scores them, drafts outreach for review, and hands booked calls to the LaiQ Digital CRM.

Built in Laravel (PHP) with Blade views. **The Leads screen runs on the real database** (businesses, contacts, audits), with working filters, search and lead profiles. The other screens still use sample data from `app/Support/SampleData.php` until their features are built.

| Screen | URL | View file |
| --- | --- | --- |
| Dashboard | `/` | `resources/views/screens/dashboard.blade.php` |
| Lead Finder | `/lead-finder` | `resources/views/screens/finder.blade.php` |
| Leads + lead profile | `/leads` | `resources/views/screens/leads.blade.php` |
| Review queue | `/review` | `resources/views/screens/review.blade.php` |
| Campaigns | `/campaigns` | `resources/views/screens/campaigns.blade.php` |
| Inbox | `/inbox` | `resources/views/screens/inbox.blade.php` |
| Booked | `/booked` | `resources/views/screens/booked.blade.php` |
| Settings | `/settings` | `resources/views/screens/settings.blade.php` |

## Where things live

- **Colors, fonts, spacing:** `public/css/cloyne.css`. Every color is a variable at the top of the file (dark theme first, then light). Change a value there and the whole app updates.
- **Shared layout:** `resources/views/layouts/app.blade.php` (page shell + top bar) and `resources/views/partials/sidebar.blade.php` (left menu).
- **Reusable pieces:** `resources/views/components/`: `<x-stat>`, `<x-score>`, `<x-pill>`, `<x-icon>`.
- **Sample data:** `app/Support/SampleData.php`. Each screen's numbers and rows come from here.
- **Which data each screen gets:** `app/Http/Controllers/ScreenController.php`.
- **URLs:** `routes/web.php`.

## Run it on Windows

You need PHP 8.3+ and Composer once. If you don't have them, open **PowerShell** and run:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Close and reopen PowerShell, then from the project folder:

```powershell
composer install
copy .env.example .env
php artisan key:generate
New-Item database\database.sqlite -ItemType File
php artisan migrate
php artisan db:seed
php artisan serve
```

`db:seed` loads 8 sample businesses into the database so the Leads screen has data. Run `php artisan test` any time to check that things still work.

Open http://127.0.0.1:8000 in your browser. Leave `php artisan serve` running while you edit; refresh the page to see changes.

No `npm` step is needed: Cloyne's styles are plain CSS in `public/css/cloyne.css`.

## Edit in VS Code with Claude

1. **Get the code:** in VS Code, open the Command Palette (`Ctrl+Shift+P`) → **Git: Clone** → paste this repo's GitHub URL → pick a folder → **Open**. ([Git for Windows](https://git-scm.com/downloads/win) must be installed.)
2. **Add Claude:** open Extensions (`Ctrl+Shift+X`), search **Claude Code**, install it, and sign in with your Claude account (a paid Pro, Max, Team or Enterprise plan). Requires VS Code 1.94 or later. [Docs](https://code.claude.com/docs/en/vs-code)
3. **Work:** open the Claude panel and ask for changes, e.g. "make the sidebar 260px wide" or "add a Notes field to the lead profile". It reads `CLAUDE.md` in this repo for project context.
4. **Save to GitHub:** in VS Code's Source Control panel (`Ctrl+Shift+G`), write a message, **Commit**, then **Sync Changes**. Pull before you start work if you edit from more than one place.

Prefer the terminal? Install Claude Code from PowerShell with `irm https://claude.ai/install.ps1 | iex`, then run `claude` inside the project folder. [Setup docs](https://code.claude.com/docs/en/setup)

## What's next (not built yet)

- ~~Database tables for businesses, contacts and audits~~ ✓ done (`app/Models/Business.php`, `Contact.php`, `Audit.php`)
- Database tables for campaigns, messages and bookings
- Background workers: Lead Finder searches (Apify), email verification, AI audits (Claude API), sending (Instantly), reply checking
- CRM connection: push booked leads to the LaiQ Digital CRM's API; receive deal-stage webhooks
- Logins and roles (Owner, Reviewer, Viewer)
- Hosting on Railway or Render (not HostGator shared hosting, which can't run always-on workers)
