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
            <div><strong>Instantly</strong><div class="caption">Sending and inbox warm-up</div></div>
            <x-pill tone="neutral">Step 5</x-pill>
        </div>
        <div class="split connection-row" style="border-bottom: 0">
            <div><strong>LaiQ Digital CRM</strong><div class="caption">Booked calls go here; deal stages come back</div></div>
            <x-pill tone="neutral">Step 7</x-pill>
        </div>
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
    <section class="card">
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
