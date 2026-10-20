@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1 class="page-title">Settings</h1>

    <section class="card">
        <h2 class="card__title">Connections</h2>
        <div class="caption">Keys live in the <span class="mono">.env</span> file in your Cloyne folder, never in this app or on GitHub.</div>

        <div class="split" style="padding: 8px 0; border-bottom: 1px solid var(--border)">
            <div><strong>Apify</strong><div class="caption">Lead Finder: Google Maps searches · <span class="mono">APIFY_TOKEN</span></div></div>
            @if ($apifyConnected)
                <x-pill tone="success">Connected</x-pill>
            @else
                <x-pill tone="warning">Not connected</x-pill>
            @endif
        </div>
        <div class="split" style="padding: 8px 0; border-bottom: 1px solid var(--border)">
            <div><strong>Claude API</strong><div class="caption">AI audit, scoring and email drafts · <span class="mono">ANTHROPIC_API_KEY</span></div></div>
            @if ($claudeConnected)
                <x-pill tone="success">Connected</x-pill>
            @else
                <x-pill tone="warning">Not connected</x-pill>
            @endif
        </div>
        <div class="split" style="padding: 8px 0; border-bottom: 1px solid var(--border)">
            <div><strong>Instantly</strong><div class="caption">Sending and inbox warm-up</div></div>
            <x-pill tone="neutral">Step 5</x-pill>
        </div>
        <div class="split" style="padding: 8px 0">
            <div><strong>LaiQ Digital CRM</strong><div class="caption">Booked calls go here; deal stages come back</div></div>
            <x-pill tone="neutral">Step 7</x-pill>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <h2 class="card__title">LaiQ packages</h2>
            <span class="caption">Edit in <span class="mono">config/cloyne.php</span> · on sale: <span class="mono">CLOYNE_SELLING</span> in <span class="mono">.env</span></span>
        </div>
        <div class="grid-3">
            @foreach (\App\Models\Business::packages() as $key => $pkg)
                <div class="campaign-card">
                    <div class="split">
                        <strong>{{ $pkg['name'] }}</strong>
                        @if (is_null($pkg['order']))
                            <x-pill tone="neutral">Quote only</x-pill>
                        @elseif (in_array($key, \App\Models\Business::sellingNow(), true))
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
                <div class="secret">{{ $temp['password'] }}</div>
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
                                <span class="caption">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</span>
                            @endif
                        </td>
                        <td>
                            @can('manage-team')
                                @unless ($member->is(auth()->user()))
                                    <div class="btn-row" style="align-items: center">
                                        <form method="post" action="{{ route('team.reset', $member) }}"
                                              onsubmit="return confirm('Make a new temporary password for {{ addslashes($member->name) }}? Their current password stops working.')">
                                            @csrf
                                            <button type="submit" class="btn btn--ghost btn--sm">Reset password</button>
                                        </form>
                                        <form method="post" action="{{ route('team.destroy', $member) }}"
                                              onsubmit="return confirm('Remove {{ addslashes($member->name) }}? They won’t be able to sign in.')">
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
