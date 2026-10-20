# Cloyne — project context

Cloyne is LaiQ Digital's internal lead engine (internal only, never client-facing). It finds any kind of business (any category, any area), audits their websites and ads with AI, scores them 0–100, matches each to the best-fit LaiQ package (defined in `config/cloyne.php`), drafts outreach for human review, sends through Instantly, handles replies, and pushes booked calls to the separate, custom-built LaiQ Digital CRM (PHP + MySQL) via an API.

Current state: all eight screens are Blade views. The Leads screen reads the real database (`Business`, `Contact`, `Audit` models; save audits with `Business::recordAudit()`, which also updates the cached `score` and `stage`). The other screens still read `app/Support/SampleData.php`. See README.md for the file map. Run `php artisan test` after changes.

Packages: `config/cloyne.php` holds every LaiQ package (Starter — Website, Growth — Ads + CRM, Scale — Full System, Other), with prices, what's included, the signs a business needs it, and the signs it's not a fit. The AI audit must use those lists. Each audit records `package` (best fit) and `pitch_package` (a smaller package that also genuinely fits). `CLOYNE_SELLING` in `.env` lists what's on sale; right now Starter only, while the CRM is debugged. `Business::pitchPackage()` decides what to pitch.

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
