@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1 class="page-title">Settings</h1>

    @error('connection')
        <div role="alert"><x-pill tone="danger">Not connected</x-pill> {{ $message }}</div>
    @enderror

    <section class="card">
        <div class="card__head">
            <h2 class="card__title">Connections</h2>
            <span class="caption">Keys live in the <span class="mono">.env</span> file in your Cloyne folder, never in this app or on GitHub.</span>
        </div>

        @foreach ([
            ['key' => 'apify', 'name' => 'Apify', 'about' => 'Lead Finder: Google Maps searches', 'env' => 'APIFY_TOKEN', 'on' => $apifyConnected,
             'usage' => number_format($usage['searches']) . ' ' . Str::plural('search', $usage['searches']) . ' · ' . number_format($usage['found']) . ' businesses · about $' . number_format($usage['apifyCost'], 2) . ' this month'],
            ['key' => 'claude', 'name' => 'Claude API', 'about' => 'AI audit, scoring and email drafts', 'env' => 'ANTHROPIC_API_KEY', 'on' => $claudeConnected,
             'usage' => number_format($usage['audits']) . ' ' . Str::plural('audit', $usage['audits']) . ' · ' . number_format($usage['drafts']) . ' ' . Str::plural('email', $usage['drafts']) . ' written this month'],
            ['key' => 'millionverifier', 'name' => 'MillionVerifier', 'about' => 'Checks every email is real before a draft is written (optional)', 'env' => 'MILLIONVERIFIER_KEY', 'on' => $verifierConnected,
             'usage' => number_format($usage['checked']) . ' ' . Str::plural('email', $usage['checked']) . ' checked this month · ' . number_format($usage['invalid']) . ' invalid'],
        ] as $service)
            <div class="split connection-row">
                <div>
                    <strong>{{ $service['name'] }}</strong>
                    <div class="caption">{{ $service['about'] }} · <span class="mono">{{ $service['env'] }}</span></div>
                    @if ($service['on'])<div class="caption" style="margin-top: 2px">{{ $service['usage'] }}</div>@endif
                </div>
                <div class="btn-row" style="align-items: center">
                    @if ($service['on'])
                        @can('manage-team')
                            <form method="post" action="{{ route('settings.test', $service['key']) }}">
                                @csrf
                                <button type="submit" class="btn btn--ghost btn--sm" data-busy="Testing…">Test</button>
                            </form>
                        @endcan
                        <x-pill tone="success">Connected</x-pill>
                    @else
                        <x-pill tone="warning">Not connected</x-pill>
                    @endif
                </div>
            </div>
        @endforeach
        <div class="split connection-row">
            <div><strong>Instantly</strong><div class="caption">Sending and inbox warm-up · <span class="mono">INSTANTLY_API_KEY</span> · switch sending on in <a href="{{ route('campaigns') }}" class="results-link">Campaigns</a></div></div>
            <div class="btn-row" style="align-items: center">
                @if (\App\Services\Instantly::isConfigured())
                    @can('manage-team')
                        <form method="post" action="{{ route('settings.test', 'instantly') }}">
                            @csrf
                            <button type="submit" class="btn btn--ghost btn--sm" data-busy="Testing…">Test</button>
                        </form>
                    @endcan
                    <x-pill tone="success">Connected</x-pill>
                @else
                    <x-pill tone="neutral">Not connected</x-pill>
                @endif
            </div>
        </div>
        <div class="split connection-row" style="border-bottom: 0">
            <div><strong>LaiQ Digital CRM</strong><div class="caption">Cold-call lists, call results and costs · see CRM connection below</div></div>
            @if ($costsKey)<x-pill tone="success">Key made</x-pill>@else<x-pill tone="neutral">No key yet</x-pill>@endif
        </div>
    </section>

    <section class="card" id="costs">
        <div class="card__head">
            <h2 class="card__title">Costs this month</h2>
            <span class="caption">Estimates: Apify from businesses found, Claude from the words it read and wrote. Your bills at console.apify.com and the Claude Console are exact.</span>
        </div>
        <div class="stats stats--3">
            <x-stat label="Total" :value="'$' . number_format($costs['total'], 2)" />
            @foreach ($costs['items'] as $item)
                <x-stat :label="$item['name']" :value="'$' . number_format($item['amount'], 2)" :delta="$item['note']" />
            @endforeach
        </div>

    </section>

    <section class="card" id="crm">
        <div class="card__head">
            <h2 class="card__title">CRM connection</h2>
            <span class="caption">{{ number_format($crmStats['linked']) }} {{ Str::plural('lead', $crmStats['linked']) }} on CRM call lists · {{ number_format($crmStats['audited']) }} audited · {{ number_format($crmStats['calls']) }} {{ Str::plural('call', $crmStats['calls']) }} logged</span>
        </div>
        <div class="split connection-row" style="border-bottom: 0">
            <div>
                <strong>Key for the CRM</strong>
                <div class="caption">One private key lets the CRM send its cold-call lists for audits, read back Starter / Growth / Scale, pull new leads, send call results, and read these costs.</div>
                <div class="caption mono" style="margin-top: 2px">{{ url('/api') }}</div>
            </div>
            <div class="btn-row" style="align-items: center">
                @if ($costsKey)
                    <x-pill tone="success">Key made</x-pill>
                @else
                    <x-pill tone="neutral">No key yet</x-pill>
                @endif
                @can('manage-team')
                    <form method="post" action="{{ route('settings.costs-key') }}" @if ($costsKey) data-confirm="Make a new key? The CRM stops syncing until you paste the new key into it." data-confirm-button="Make new key" @endif>
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm">{{ $costsKey ? 'Make new key' : 'Make key' }}</button>
                    </form>
                    @if ($costsKey)
                        <form method="post" action="{{ route('settings.costs-key.revoke') }}" data-confirm="Turn off the CRM key? The CRM stops syncing with Cloyne." data-confirm-button="Turn off">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn--ghost btn--sm">Turn off</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
        <div class="crm-rules">
            <strong>Which leads go to the CRM call lists</strong>
            <div class="caption">Audited leads with a phone number. A lead goes if it scores at least the minimum, or if its best fit is a package you tick. {{ number_format($crmStats['waiting']) }} {{ Str::plural('lead', $crmStats['waiting']) }} waiting for the CRM to pick up.</div>
            @if ($errors->crm->any())
                <div role="alert"><x-pill tone="danger">Not saved</x-pill> {{ $errors->crm->first() }}</div>
            @endif
            @php
                $crmMin = (int) config('cloyne.crm.min_score', 60);
                $crmAlways = (array) config('cloyne.crm.always_packages', []);
            @endphp
            @can('manage-team')
                <form method="post" action="{{ route('settings.crm') }}" class="crm-rules__form">
                    @csrf
                    @method('PUT')
                    <div class="field">
                        <label for="crm_min_score" class="label">Minimum score</label>
                        <input id="crm_min_score" name="crm_min_score" type="number" class="input" min="0" max="100" required value="{{ old('crm_min_score', $crmMin) }}">
                    </div>
                    <div class="field" role="group" aria-labelledby="crm-always-label">
                        <span class="label" id="crm-always-label">Also send every lead whose best fit is</span>
                        <div class="crm-rules__packages">
                        @foreach (\App\Models\Business::packages() as $key => $pkg)
                            <label class="check">
                                <input type="checkbox" name="crm_always_packages[]" value="{{ $key }}" @checked(in_array($key, old('crm_always_packages', $crmAlways), true))>
                                {{ $pkg['short'] ?? $pkg['name'] }}
                            </label>
                        @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn--secondary btn--sm">Save</button>
                </form>
            @else
                <div class="caption" style="margin-top: var(--space-2)">Score {{ $crmMin }}+{{ $crmAlways ? ', plus every ' . collect($crmAlways)->map(fn ($k) => \App\Models\Business::packages()[$k]['short'] ?? $k)->implode(', ') . ' lead' : '' }}. Only the Owner can change this.</div>
            @endcan
        </div>
        @if (session('costs_key'))
            <div class="key-once" role="status">
                <div class="caption">Your CRM key. Copy it now; Cloyne won't show it again. Keep it out of chats and GitHub.</div>
                <div class="split" style="margin-top: var(--space-2)">
                    <span class="mono key-once__value">{{ session('costs_key') }}</span>
                    <x-copy :value="session('costs_key')" label="CRM key" />
                </div>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card__head">
            <h2 class="card__title">LaiQ packages</h2>
            <span class="caption">Names, prices and fit rules live in <span class="mono">config/cloyne.php</span></span>
        </div>
        @if ($errors->selling->any())
            <div role="alert"><x-pill tone="danger">Not saved</x-pill> {{ $errors->selling->first() }}</div>
        @endif
        <form method="post" action="{{ route('settings.selling') }}" id="selling-form">
            @csrf
            @method('PUT')
            <div class="grid-3">
                @foreach (\App\Models\Business::packages() as $key => $pkg)
                    @php $onSale = in_array($key, \App\Models\Business::sellingNow(), true); @endphp
                    <div class="campaign-card">
                        <div class="split">
                            <strong>{{ $pkg['name'] }}</strong>
                            @if (is_null($pkg['order']))
                                <x-pill tone="neutral">Quote only</x-pill>
                            @elseif (auth()->user()->can('manage-team'))
                                <label class="switch">
                                    <input type="checkbox" name="selling[]" value="{{ $key }}" @checked($onSale) onchange="this.form.requestSubmit()">
                                    <span>{{ $onSale ? 'On sale' : 'Paused' }}</span>
                                </label>
                            @elseif ($onSale)
                                <x-pill tone="success">On sale</x-pill>
                            @else
                                <x-pill tone="warning">Paused</x-pill>
                            @endif
                        </div>
                        <div class="caption">{{ $pkg['price'] }}</div>
                        <div style="font-size: 13px">{{ $pkg['fits'] }}</div>
                    </div>
                @endforeach
            </div>
            <noscript><button type="submit" class="btn btn--secondary btn--sm">Save</button></noscript>
        </form>
        <div class="caption">Cloyne still shows each lead's best fit, but only pitches and writes emails for packages on sale.</div>
    </section>

    @php $o = config('cloyne.outreach'); @endphp
    <section class="card" id="outreach">
        <div class="card__head">
            <h2 class="card__title">Outreach emails</h2>
            <span class="caption">From {{ $o['sender_name'] }}, {{ $o['sender_title'] }} · written by <span class="mono">{{ config('services.anthropic.draft_model') }}</span></span>
        </div>

        @can('manage-team')
            @if ($errors->outreach->any())
                <div role="alert" style="display: flex; flex-direction: column; gap: 4px">
                    @foreach ($errors->outreach->all() as $error)
                        <span><x-pill tone="danger">Not saved</x-pill> {{ $error }}</span>
                    @endforeach
                </div>
            @endif
            <form method="post" action="{{ route('settings.outreach') }}" class="settings-form" data-warn-unsaved>
                @csrf
                @method('PUT')
                <div class="field">
                    <label for="booking_url" class="label">Booking link</label>
                    <input id="booking_url" name="booking_url" type="url" class="input" value="{{ old('booking_url', $o['booking_url']) }}" placeholder="https://…">
                    <span class="caption">Ends every email. Leave empty and emails ask people to reply instead.</span>
                </div>
                <div class="field">
                    <label for="mailing_address" class="label">Mailing address</label>
                    <input id="mailing_address" name="mailing_address" type="text" class="input" maxlength="255" value="{{ old('mailing_address', $o['mailing_address']) }}" placeholder="LaiQ Digital, street, city, state ZIP">
                    <span class="caption">Required by law (CAN-SPAM) in every email before sending starts. A PO box or virtual mailbox is fine.</span>
                </div>
                <div class="field">
                    <label for="tone" class="label">Tone</label>
                    <textarea id="tone" name="tone" class="textarea" rows="2" maxlength="500" required>{{ old('tone', $o['tone']) }}</textarea>
                </div>
                <div class="field">
                    <label for="never" class="label">Never (one rule per line)</label>
                    <textarea id="never" name="never" class="textarea" rows="4" maxlength="2000">{{ old('never', implode("\n", $o['never'])) }}</textarea>
                </div>
                <div class="stats">
                    <div class="field">
                        <label for="min_score" class="label">Hot score (gets an email)</label>
                        <input id="min_score" name="min_score" type="number" class="input" min="30" max="95" required value="{{ old('min_score', $o['min_score']) }}">
                    </div>
                    <div class="field">
                        <label for="max_words" class="label">Most words per email</label>
                        <input id="max_words" name="max_words" type="number" class="input" min="40" max="250" required value="{{ old('max_words', $o['max_words']) }}">
                    </div>
                </div>
                <div class="split" style="border-top: 1px solid var(--border); padding-top: 16px; flex-wrap: wrap">
                    <span class="caption">New drafts use these right away. Drafts already written stay as they are.</span>
                    <div class="btn-row">
                        @if ($outreachCustom)
                            <button type="submit" form="outreach-reset" class="btn btn--ghost">Reset to defaults</button>
                        @endif
                        <button type="submit" class="btn btn--primary">Save email rules</button>
                    </div>
                </div>
            </form>
            @if ($outreachCustom)
                <form method="post" action="{{ route('settings.outreach.reset') }}" id="outreach-reset"
                      data-confirm="Reset the email rules to the defaults in config/cloyne.php and .env?" data-confirm-button="Reset">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @else
            <dl class="facts" style="grid-template-columns: 140px minmax(0, 1fr)">
                <dt>Booking link</dt><dd>@if ($o['booking_url'])<a href="{{ $o['booking_url'] }}" target="_blank" rel="noopener">{{ $o['booking_url'] }}</a>@else <span class="caption">None: emails ask people to reply</span> @endif</dd>
                <dt>Mailing address</dt><dd>@if ($o['mailing_address']){{ $o['mailing_address'] }}@else <x-pill tone="warning">Missing</x-pill> <span class="caption">required before sending</span>@endif</dd>
                <dt>Tone</dt><dd>{{ $o['tone'] }}</dd>
                <dt>Never</dt><dd>{{ implode(' ', $o['never']) }}</dd>
                <dt>Who gets one</dt><dd>Leads scoring {{ $o['min_score'] }}+ with an email, pitching a package on sale · under {{ $o['max_words'] }} words</dd>
            </dl>
            <div class="caption">Only the Owner can change these.</div>
        @endcan
    </section>

    <section class="card" id="factsheet">
        @php
            $facts = old('entries', config('factsheet.entries', []));
            $factsCustom = \App\Models\Setting::isSet('factsheet');
        @endphp
        <div class="card__head">
            <h2 class="card__title">Fact sheet for replies</h2>
            <span class="caption">{{ count(config('factsheet.entries', [])) }} approved answers{{ $factsCustom ? ' · edited' : ' · LaiQ defaults' }} · used by the reply agent in version 3.0</span>
        </div>
        <div class="caption">When businesses reply, Cloyne's AI may only answer with what's here. Everything else goes to a person: {{ Str::lower(implode(', ', config('factsheet.handoff', []))) }}.</div>
        @if ($errors->factsheet->any())
            <div role="alert"><x-pill tone="danger">Not saved</x-pill> {{ $errors->factsheet->first() }}</div>
        @endif
        @can('manage-team')
            <form method="post" action="{{ route('settings.factsheet') }}" data-warn-unsaved class="facts-form">
                @csrf @method('PUT')
                @foreach (array_merge(array_values($facts), [['q' => '', 'a' => ''], ['q' => '', 'a' => '']]) as $i => $fact)
                    <div class="fact-row">
                        <div class="field">
                            <label for="fact-q-{{ $i }}" class="label">{{ $fact['q'] === '' ? 'New question' : 'Question' }}</label>
                            <input id="fact-q-{{ $i }}" name="entries[{{ $i }}][q]" class="input" maxlength="200" value="{{ $fact['q'] }}" placeholder="{{ $fact['q'] === '' ? 'Add a question people ask' : '' }}">
                        </div>
                        <div class="field">
                            <label for="fact-a-{{ $i }}" class="label">Approved answer</label>
                            <textarea id="fact-a-{{ $i }}" name="entries[{{ $i }}][a]" class="input" rows="2" maxlength="1200">{{ $fact['a'] }}</textarea>
                        </div>
                    </div>
                @endforeach
                <div class="btn-row">
                    <button type="submit" class="btn btn--secondary">Save fact sheet</button>
                    @if ($factsCustom)
                        <button type="submit" form="factsheet-reset" class="btn btn--ghost">Reset to LaiQ defaults</button>
                    @endif
                </div>
                <div class="caption">To remove an answer, clear both its boxes. Two empty rows at the end add new ones.</div>
            </form>
            @if ($factsCustom)
                <form method="post" action="{{ route('settings.factsheet.reset') }}" id="factsheet-reset" data-confirm="Reset the fact sheet? Your edits are replaced by LaiQ's default answers." data-confirm-button="Reset">
                    @csrf @method('DELETE')
                </form>
            @endif
        @else
            <dl class="facts">
                @foreach (config('factsheet.entries', []) as $fact)
                    <dt>{{ $fact['q'] }}</dt><dd>{{ $fact['a'] }}</dd>
                @endforeach
            </dl>
            <div class="caption">Only the Owner can change these.</div>
        @endcan
    </section>

    <section class="card" id="updates">
        @php
            $latestVersion = collect(config('versions', []))->sort(fn ($a, $b) => version_compare($b['version'], $a['version']))->first();
            $up = $autoUpdate['status'];
        @endphp
        <div class="card__head">
            <h2 class="card__title">Updates</h2>
            <span class="caption">Running <span class="mono">v{{ $latestVersion['version'] ?? '?' }}</span> · <a href="{{ route('versions') }}" class="results-link">What's new</a></span>
        </div>
        <div class="split connection-row" style="border-bottom: 0">
            <div>
                <strong>Install new versions automatically</strong>
                <div class="caption">The server checks GitHub every 15 minutes and installs a new version by itself, with no Terminal needed.</div>
                @if ($up)
                    <div class="caption" style="margin-top: 2px">
                        {{ $up['message'] }}
                        @if ($up['checked_at']) · checked <x-time :at="$up['checked_at']" />@endif
                        @if ($up['updated_at']) · last installed <x-time :at="$up['updated_at']" />@endif
                    </div>
                @else
                    <div class="caption" style="margin-top: 2px">Starts after the next update by hand (bash ~/cloyne-app/deploy/dreamhost.sh).</div>
                @endif
            </div>
            <div class="btn-row" style="align-items: center">
                @if (! $autoUpdate['on'])
                    <x-pill tone="neutral">Off</x-pill>
                @elseif ($up && $up['state'] === 'failed')
                    <x-pill tone="danger">Stopped</x-pill>
                @elseif ($up && $up['state'] === 'error')
                    <x-pill tone="warning">Can't reach GitHub</x-pill>
                @elseif ($up && in_array($up['state'], ['current', 'updated'], true))
                    <x-pill tone="success">On</x-pill>
                @else
                    <x-pill tone="neutral">Waiting</x-pill>
                @endif
                @can('manage-team')
                    <form method="post" action="{{ route('settings.updates') }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="on" value="{{ $autoUpdate['on'] ? 0 : 1 }}">
                        <button type="submit" class="btn btn--ghost btn--sm">{{ $autoUpdate['on'] ? 'Turn off' : 'Turn on' }}</button>
                    </form>
                @endcan
            </div>
        </div>
    </section>

    <section class="card" id="team">
        <div class="card__head">
            <h2 class="card__title">Team</h2>
            <span class="caption">{{ $team->count() }} {{ Str::plural('person', $team->count()) }} · Owner: everything · Reviewer: searches, audits, deletes · Viewer: look only</span>
        </div>

        @if (session('temp_password'))
            @php $temp = session('temp_password'); @endphp
            <div class="card" style="border-color: var(--signal); background: var(--signal-soft)" role="status">
                <strong>Temporary password for {{ $temp['name'] }}</strong>
                <div class="caption">Share it with {{ $temp['email'] }} privately (a text or in person). It is shown only once. They'll pick their own password the first time they sign in.</div>
                <div class="input-row"><div class="secret" style="flex: 1">{{ $temp['password'] }}</div><button type="button" class="btn btn--secondary" data-copy="{{ $temp['password'] }}" aria-label="Copy temporary password">Copy</button></div>
            </div>
        @endif

        @if ($errors->team->any())
            <div role="alert"><x-pill tone="danger">Not saved</x-pill> {{ $errors->team->first() }}</div>
        @endif

        <div class="table-wrap">
            <table class="table" style="min-width: 640px">
                <thead><tr><th>Person</th><th>Role</th><th>Last sign-in</th><th></th></tr></thead>
                <tbody>
                @foreach ($team as $member)
                    <tr>
                        <td>
                            <div class="table__name">{{ $member->name }} @if ($member->is(auth()->user()))<span class="caption">(you)</span>@endif</div>
                            <div class="caption">{{ $member->email }}</div>
                        </td>
                        <td>
                            @can('manage-team')
                                <form method="post" action="{{ route('team.update', $member) }}" class="btn-row" style="justify-content: flex-start">
                                    @csrf
                                    @method('PUT')
                                    <label for="role-{{ $member->id }}" class="sr-only">Role for {{ $member->name }}</label>
                                    <select id="role-{{ $member->id }}" name="role" class="select" onchange="this.form.submit()">
                                        @foreach (\App\Models\User::ROLES as $key => $role)
                                            <option value="{{ $key }}" @selected($member->role === $key)>{{ $role['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <noscript><button type="submit" class="btn btn--secondary btn--sm">Save</button></noscript>
                                </form>
                            @else
                                {{ $member->roleLabel() }}
                            @endcan
                        </td>
                        <td>
                            @if ($member->must_change_password)
                                <x-pill tone="warning">Waiting</x-pill> <span class="caption">hasn't picked a password</span>
                            @else
                                <span class="caption">@if ($member->last_login_at)<x-time :at="$member->last_login_at" />@else Never @endif</span>
                            @endif
                        </td>
                        <td>
                            @can('manage-team')
                                @unless ($member->is(auth()->user()))
                                    <div class="btn-row" style="align-items: center">
                                        <form method="post" action="{{ route('team.reset', $member) }}"
                                              data-confirm="Make a new temporary password for {{ $member->name }}? Their current password stops working and they're signed out." data-confirm-button="Reset password">
                                            @csrf
                                            <button type="submit" class="btn btn--ghost btn--sm">Reset password</button>
                                        </form>
                                        <form method="post" action="{{ route('team.destroy', $member) }}"
                                              data-confirm="Remove {{ $member->name }} from the team? They won’t be able to sign in." data-confirm-button="Remove">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn--ghost btn--sm">Remove</button>
                                        </form>
                                    </div>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @can('manage-team')
            <form method="post" action="{{ route('team.store') }}" style="border-top: 1px solid var(--border); padding-top: 16px; display: flex; flex-direction: column; gap: 12px">
                @csrf
                <strong>Add a person</strong>
                <div class="stats">
                    <div class="field">
                        <label for="team-name" class="label">Name</label>
                        <input id="team-name" name="name" type="text" class="input" required maxlength="80" value="{{ old('name') }}">
                    </div>
                    <div class="field">
                        <label for="team-email" class="label">Email</label>
                        <input id="team-email" name="email" type="email" class="input" required value="{{ old('email') }}">
                    </div>
                    <div class="field">
                        <label for="team-role" class="label">Role</label>
                        <select id="team-role" name="role" class="select">
                            @foreach (\App\Models\User::ROLES as $key => $role)
                                <option value="{{ $key }}" @selected(old('role', 'reviewer') === $key)>{{ $role['label'] }} – {{ $role['about'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="split" style="flex-wrap: wrap">
                    <span class="caption">Cloyne makes a temporary password for you to share. Nothing is emailed.</span>
                    <button type="submit" class="btn btn--primary">Add person</button>
                </div>
            </form>
        @else
            <div class="caption">Only the Owner can add people or change roles.</div>
        @endcan
    </section>
@endsection
