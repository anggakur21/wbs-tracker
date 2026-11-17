@extends('layouts.app')

@section('title', $partner->name.' · Partners')

@section('css')
    .cred{border:1px solid var(--accent);background:var(--accent-soft);border-radius:var(--r-lg);padding:18px 20px;display:flex;flex-direction:column;gap:12px}
    .cred h2{display:flex;align-items:center;gap:8px}
    .cred h2 svg{width:18px;height:18px;color:var(--accent)}
    .cred-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .cred-field{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:10px 12px;display:flex;align-items:center;gap:10px}
    .cred-field .k{font-size:12px;color:var(--muted);display:block}
    .cred-field .v{font-size:16px;font-weight:500;overflow-wrap:anywhere}
    .cred-field .grow{min-width:0}
    .acct-edit{margin-top:12px;padding:14px;background:var(--hover);border-radius:var(--r)}
    .row-actions{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center}
    .row-actions summary{list-style:none}
    .row-actions summary::-webkit-details-marker{display:none}
    .proj-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px}
    @media (max-width:640px){.cred-grid{grid-template-columns:minmax(0,1fr)}}
@endsection

@section('content')
    @php
        $accountBag = $errors->getBag('account');
        $partnerBag = $errors->getBag('partner');
        $cred = session('credentials');
    @endphp

    <header class="page-head">
        <div class="titles">
            <nav class="crumbs" aria-label="Breadcrumb">
                <a href="{{ route('admin.partners.index') }}">Partners</a>
                @include('partials.icon', ['name' => 'chevron'])
                <span>{{ $partner->name }}</span>
            </nav>
            <h1>{{ $partner->name }}</h1>
            <div class="meta">
                <span>Accounts <b>{{ $partner->users->count() }}</b></span>
                <span>Projects <b>{{ $partner->projects->count() }}</b></span>
                @if ($partner->contact_email) <span>Email <b>{{ $partner->contact_email }}</b></span> @endif
                @if ($partner->phone) <span>Phone <b>{{ $partner->phone }}</b></span> @endif
            </div>
        </div>
    </header>

    @error('delete')
        <div class="notice err" role="alert">@include('partials.icon', ['name' => 'alert']) <span>{{ $message }}</span></div>
    @enderror

    @if ($cred)
        <section class="cred" role="status" aria-live="polite">
            <h2>@include('partials.icon', ['name' => 'key']) {{ $cred['new'] ? 'Account created for '.$cred['name'] : 'Password reset for '.$cred['name'] }}</h2>
            <p class="small">Give these sign-in details to {{ $cred['name'] }}. The password is shown only this once and must be changed at the first sign-in.</p>
            <div class="cred-grid">
                <div class="cred-field"><span class="grow"><span class="k">Username</span><span class="v num" id="cred-user">{{ $cred['username'] }}</span></span></div>
                <div class="cred-field">
                    <span class="grow"><span class="k">One-time password</span><span class="v num" id="cred-pass">{{ $cred['password'] }}</span></span>
                    <button class="btn sm" type="button" data-copy="cred-pass">@include('partials.icon', ['name' => 'copy']) Copy</button>
                </div>
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="pad" style="padding-bottom:0">
            <div class="panel-head">
                <h2>Accounts</h2>
                <p>Project Managers, Project Admins and Business Analysts can update progress and evidence. Only TIF admins can change the baseline.</p>
            </div>
        </div>
        @if ($partner->users->isEmpty())
            <div class="empty" style="padding-top:28px">@include('partials.icon', ['name' => 'users'])<b>No accounts yet</b><p>Add the people who will update the WBS for {{ $partner->name }}.</p></div>
        @else
            <div class="tablewrap">
                <table>
                    <thead><tr><th>Name</th><th>Role</th><th>Username</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    @foreach ($partner->users as $account)
                        @php $bag = $errors->getBag('account'.$account->id); @endphp
                        <tr>
                            <td>
                                <span class="cell-title">{{ $account->name }}</span>
                                @if ($account->email) <div class="cell-sub">{{ $account->email }}</div> @endif
                            </td>
                            <td><span class="tag" style="white-space:nowrap">{{ $account->roleLabel() }}</span></td>
                            <td class="num" style="white-space:nowrap">{{ $account->username }}</td>
                            <td>
                                @if ($account->must_change_password)
                                    <span class="chip behind">Awaiting first sign-in</span>
                                @else
                                    <span class="chip done">Active</span>
                                @endif
                            </td>
                            <td style="min-width:190px">
                                <div class="row-actions">
                                    <details @if ($bag->any()) open @endif style="flex-basis:100%">
                                        <summary class="linkbtn">Edit details</summary>
                                        <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="form acct-edit">
                                            @csrf @method('PUT')
                                            <label>Full name
                                                <input id="name-{{ $account->id }}" type="text" name="name" value="{{ $bag->any() ? old('name') : $account->name }}" required maxlength="255">
                                            </label>
                                            <label>Role
                                                <select id="role-{{ $account->id }}" name="partner_role" required>
                                                    @foreach (\App\Models\User::PARTNER_ROLES as $value => $label)
                                                        <option value="{{ $value }}" @selected(($bag->any() ? old('partner_role') : $account->partner_role) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>Username
                                                <input id="username-{{ $account->id }}" type="text" name="username" value="{{ $bag->any() ? old('username') : $account->username }}" required minlength="3" maxlength="50" autocomplete="off">
                                            </label>
                                            <label><span>Email <span class="hint">· needed for revision emails</span></span>
                                                <input id="email-{{ $account->id }}" type="email" name="email" value="{{ $bag->any() ? old('email') : $account->email }}" maxlength="255">
                                            </label>
                                            @foreach ($bag->all() as $message)
                                                <div class="err-text span" role="alert">{{ $message }}</div>
                                            @endforeach
                                            <div class="span"><button class="btn sm primary" type="submit">Save</button></div>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('admin.accounts.reset', $account) }}"
                                          onsubmit="return confirm('Reset the password of {{ $account->username }}? Their current password stops working and they get a new one-time password.')">
                                        @csrf
                                        <button class="linkbtn" type="submit">Reset password</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}"
                                          onsubmit="return confirm('Delete the account {{ $account->username }}? Their past updates and evidence stay.')">
                                        @csrf @method('DELETE')
                                        <button class="linkbtn danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="pad" style="border-top:1px solid var(--line)">
            <h3>Add account</h3>
            <form method="POST" action="{{ route('admin.accounts.store', $partner) }}" class="form">
                @csrf
                <label>Full name
                    <input id="new-name" type="text" name="name" value="{{ $accountBag->any() ? old('name') : '' }}" required maxlength="255">
                </label>
                <label>Role
                    <select id="new-role" name="partner_role" required>
                        <option value="">Choose a role</option>
                        @foreach (\App\Models\User::PARTNER_ROLES as $value => $label)
                            <option value="{{ $value }}" @selected($accountBag->any() && old('partner_role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Username
                    <input id="new-username" type="text" name="username" value="{{ $accountBag->any() ? old('username') : '' }}" required minlength="3" maxlength="50" autocomplete="off" placeholder="e.g. rina.hartono">
                </label>
                <label><span>Email <span class="hint">· needed for revision emails</span></span>
                    <input id="new-email" type="email" name="email" value="{{ $accountBag->any() ? old('email') : '' }}" maxlength="255">
                </label>
                @foreach ($accountBag->all() as $message)
                    <div class="err-text span" role="alert">{{ $message }}</div>
                @endforeach
                <div class="span actions">
                    <button class="btn primary" type="submit">@include('partials.icon', ['name' => 'plus']) Add account</button>
                    <span class="hint">A one-time password is generated and shown once.</span>
                </div>
            </form>
        </div>
    </section>

    <section class="panel pad">
        <div class="panel-head"><h2>Projects</h2></div>
        @if ($partner->projects->isEmpty())
            <p class="note">No projects assigned to {{ $partner->name }} yet. Choose this partner when you create a project.</p>
        @else
            <ul class="proj-list">
                @foreach ($partner->projects as $project)
                    <li><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a> <span class="note">{{ $project->contract_number }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="panel pad">
        <div class="panel-head"><h2>Partner details</h2></div>
        <form method="POST" action="{{ route('admin.partners.update', $partner) }}" class="form three">
            @csrf @method('PUT')
            <label>Company name
                <input id="partner-name" type="text" name="name" value="{{ $partnerBag->any() ? old('name') : $partner->name }}" required maxlength="255">
            </label>
            <label>Contact email
                <input id="partner-email" type="email" name="contact_email" value="{{ $partnerBag->any() ? old('contact_email') : $partner->contact_email }}" maxlength="255">
            </label>
            <label>Phone
                <input id="partner-phone" type="text" name="phone" value="{{ $partnerBag->any() ? old('phone') : $partner->phone }}" maxlength="50">
            </label>
            @foreach ($partnerBag->all() as $message)
                <div class="err-text span" role="alert">{{ $message }}</div>
            @endforeach
            <div class="span actions" style="justify-content:space-between">
                <button class="btn" type="submit">Save details</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.partners.destroy', $partner) }}" style="margin-top:16px;padding-top:16px;border-top:1px dashed var(--line-strong)"
              onsubmit="return confirm('Delete {{ $partner->name }} and all of its accounts?')">
            @csrf @method('DELETE')
            <button class="btn sm danger" type="submit" @disabled($partner->projects->isNotEmpty())>@include('partials.icon', ['name' => 'trash']) Delete partner</button>
            @if ($partner->projects->isNotEmpty())
                <span class="hint" style="margin-left:8px">A partner with projects can't be deleted.</span>
            @endif
        </form>
    </section>
@endsection

@section('js')
    <script>
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = document.getElementById(btn.dataset.copy).textContent.trim();
                navigator.clipboard.writeText(text).then(function () {
                    var label = btn.lastChild; btn.lastChild.textContent = ' Copied';
                    setTimeout(function () { label.textContent = ' Copy'; }, 1600);
                });
            });
        });
    </script>
@endsection
