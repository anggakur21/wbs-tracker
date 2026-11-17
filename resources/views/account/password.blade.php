@extends('layouts.app')

@section('title', 'Change password · TIF PMO')

@section('content')
    <header class="page-head">
        <div class="titles">
            <h1>{{ $forced ? 'Choose your password' : 'Change password' }}</h1>
            <p class="sub">
                @if ($forced)
                    You signed in with a one-time password from the admin. Set your own password to continue.
                @else
                    Signed in as {{ auth()->user()->username }}.
                @endif
            </p>
        </div>
    </header>

    <section class="panel pad" style="max-width:520px">
        <form method="POST" action="{{ route('account.password.update') }}" class="stack" style="gap:16px">
            @csrf @method('PUT')
            <label>{{ $forced ? 'One-time password' : 'Current password' }}
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
                @error('current_password') <span class="err-text">{{ $message }}</span> @enderror
            </label>
            <label>New password
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <span class="hint">At least 8 characters.</span>
                @error('password') <span class="err-text">{{ $message }}</span> @enderror
            </label>
            <label>Repeat new password
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </label>
            <div class="actions">
                <button class="btn primary" type="submit">Save password</button>
                @unless ($forced)
                    <a class="btn" href="{{ route('home') }}">Cancel</a>
                @endunless
            </div>
        </form>
    </section>
@endsection
