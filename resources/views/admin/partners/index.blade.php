@extends('layouts.app')

@section('title', 'Implementation partners · TIF PMO')

@section('content')
    <header class="page-head">
        <div class="titles">
            <h1>Implementation partners</h1>
            <p class="sub">Add the partner company first, then give its Project Manager, Project Admin and Business Analyst their own sign-in accounts.</p>
        </div>
    </header>

    @error('delete')
        <div class="notice err" role="alert">@include('partials.icon', ['name' => 'alert']) <span>{{ $message }}</span></div>
    @enderror

    <section class="panel">
        @if ($partners->isEmpty())
            <div class="empty">@include('partials.icon', ['name' => 'building'])<b>No partners yet</b><p>Add the first implementation partner with the form below.</p></div>
        @else
            <div class="tablewrap">
                <table>
                    <thead><tr><th>Partner</th><th>Contact</th><th class="r">Accounts</th><th class="r">Projects</th><th><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                    @foreach ($partners as $partner)
                        <tr>
                            <td><a class="cell-title" href="{{ route('admin.partners.show', $partner) }}">{{ $partner->name }}</a></td>
                            <td>
                                {{ $partner->contact_email ?: '–' }}
                                @if ($partner->phone) <div class="cell-sub">{{ $partner->phone }}</div> @endif
                            </td>
                            <td class="num r {{ $partner->users_count ? '' : 'c-warn' }}">{{ $partner->users_count }}</td>
                            <td class="num r">{{ $partner->projects_count }}</td>
                            <td class="r"><a class="btn sm" href="{{ route('admin.partners.show', $partner) }}">Manage @include('partials.icon', ['name' => 'chevron'])</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel pad">
        <div class="panel-head">
            <h2>Add partner</h2>
            <p>The company. Its people's accounts are added on the next screen.</p>
        </div>
        <form method="POST" action="{{ route('admin.partners.store') }}" class="form three">
            @csrf
            <label>Company name
                <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. PT Mitra Solusi">
                @error('name') <span class="err-text">{{ $message }}</span> @enderror
            </label>
            <label><span>Contact email <span class="hint">· optional</span></span>
                <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" maxlength="255">
                @error('contact_email') <span class="err-text">{{ $message }}</span> @enderror
            </label>
            <label><span>Phone <span class="hint">· optional</span></span>
                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="50">
                @error('phone') <span class="err-text">{{ $message }}</span> @enderror
            </label>
            <div class="span"><button class="btn primary" type="submit">@include('partials.icon', ['name' => 'plus']) Add partner</button></div>
        </form>
    </section>
@endsection
