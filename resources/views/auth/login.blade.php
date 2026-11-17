@extends('layouts.guest')

@section('title', 'Sign in · TIF PMO')

@section('css')
    .auth{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);min-height:100vh}

    /* Left: who we are and what the app does, shown with its own UI */
    .auth-intro{position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;gap:40px;
        padding:44px 56px;background:var(--surface);color:var(--ink);border-right:1px solid var(--line)}
    .auth-intro>*{position:relative;z-index:1}
    .auth-logo{width:210px;height:auto;display:block}
    .auth-intro h1{color:var(--ink);font-size:42px;line-height:1.08;letter-spacing:-.035em;font-weight:800;max-width:15ch}
    .auth-intro h1 em{font-style:normal;background:var(--brand-gradient);-webkit-background-clip:text;background-clip:text;color:transparent}
    .auth-intro .lead{color:var(--muted);font-size:16px;max-width:44ch;margin-top:16px}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:14px}
    .eyebrow::before{content:"";width:18px;height:3px;border-radius:2px;background:var(--brand-gradient)}
    .auth-foot{font-size:13px;color:var(--muted)}

    /* The logo's three slanted shapes, large and faint in the corner */
    .shapes{position:absolute;right:-60px;bottom:-70px;width:420px;height:420px;z-index:0;opacity:.09;pointer-events:none}

    .preview{max-width:440px;background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:20px;
        box-shadow:0 24px 50px -20px rgb(17 18 20 / .22);display:flex;flex-direction:column;gap:14px;
        animation:rise .7s var(--ease) both .1s}
    .preview-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
    .preview-top b{display:block;color:var(--ink);font-weight:700;font-size:15px}
    .preview-top span{font-size:12px;color:var(--muted)}
    .preview .pill{font-size:11px}
    .preview .pill.lagged{background:var(--crit-bg);color:var(--crit)}
    .prow{display:grid;grid-template-columns:118px minmax(0,1fr) auto;gap:12px;align-items:center;font-size:13px;color:var(--ink-2)}
    .prow>span:first-child{white-space:nowrap}
    .prow .num{text-align:right;font-size:12px;color:var(--muted);white-space:nowrap;min-width:68px}
    .preview .track{background:var(--sunken)}
    .preview .track .mark{background:var(--ink)}
    .preview .track .fill{animation:grow 1.1s var(--ease) both .45s;transform-origin:left}
    .preview .track .fill.done{background:#1f9d5c}
    .preview .track .fill.late{background:#dc4a3d}
    .preview-legend{display:flex;gap:16px;font-size:11.5px;color:var(--muted);border-top:1px solid var(--line);padding-top:12px}
    .preview-legend i{display:inline-block;vertical-align:middle;margin-right:6px}
    @keyframes rise{from{opacity:0;transform:translateY(12px)}}
    @keyframes grow{from{transform:scaleX(0)}}
    @media (prefers-reduced-motion:reduce){.preview,.preview .track .fill{animation:none}}

    /* Right: the form */
    .auth-form{display:flex;align-items:center;justify-content:center;padding:48px 24px;position:relative}
    .auth-form::before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#c21f2f,#e8202a 40%,#2b3990 70%,#4fb3e3)}
    .auth-card{width:100%;max-width:380px;display:flex;flex-direction:column;gap:28px}
    .auth-card h2{font-size:28px;font-weight:800;letter-spacing:-.03em}
    .auth-card .sub{color:var(--muted);margin-top:6px}
    .pw{position:relative}
    .pw input{padding-right:44px}
    .pw button{position:absolute;right:4px;top:50%;transform:translateY(-50%);width:34px;height:34px;display:grid;place-items:center;
        border:0;border-radius:6px;background:none;color:var(--muted);cursor:pointer}
    .pw button:hover{color:var(--ink);background:var(--sunken)}
    .pw button[aria-pressed=true]{color:var(--accent)}
    .pw button svg{width:18px;height:18px}
    .auth-help{font-size:13px;color:var(--muted);display:flex;gap:8px;align-items:flex-start;border-top:1px solid var(--line);padding-top:20px}
    .auth-help svg{width:16px;height:16px;flex:none;margin-top:2px}
    .mobile-brand{display:none}
    .auth-theme{position:absolute;top:18px;right:18px;color:var(--muted);border:1px solid var(--line);background:var(--surface)}
    .auth-theme:hover{color:var(--ink);background:var(--hover)}

    @media (max-width:960px){
        .auth{grid-template-columns:minmax(0,1fr)}
        .auth-intro{display:none}
        .mobile-brand{display:flex;flex-direction:column;align-items:flex-start;gap:8px}
        .mobile-brand img{width:160px;height:auto}
        .mobile-brand span{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
    }
@endsection

@section('content')
<div class="auth">
    <section class="auth-intro" aria-label="About TIF PMO">
        <svg class="shapes" viewBox="0 0 100 100" aria-hidden="true">
            <path d="M33 8h19c2.4 0 3.6 1.4 2.9 3.6L32 93c-.6 2-2.6 2.4-3.8.7l-9-12.6a3 3 0 0 1-.4-2.6L30 10.2C30.5 8.8 31.6 8 33 8Z" fill="#e31e26"/>
            <path d="M56.5 23.5c1-2.7 4-2.7 5 0L74 70c.7 2.2-.5 4-2.8 4H52.6c-1.3 0-2.4-.8-2.8-2l-5-14c-.3-.7-.3-1.5 0-2.2Z" fill="#2b3990"/>
            <path d="M68 2h17c2.2 0 3.3 1.5 2.6 3.5L70.4 57c-.8 2.4-3.4 2.4-4.2 0l-5.9-17.1c-.2-.7-.2-1.4 0-2L65 4c.5-1.3 1.6-2 3-2Z" fill="#9a9ca0"/>
        </svg>

        @include('partials.logo', ['class' => 'auth-logo', 'width' => 210, 'alt' => 'Infranexia – PT Telkom Infrastruktur Indonesia'])

        <div>
            <span class="eyebrow">TIF PMO</span>
            <h1>Every deliverable <em>measured against its plan</em></h1>
            <p class="lead">TIF admins set the baseline. Implementation partners report progress and upload evidence for each WBS step. Anything behind schedule shows up the same day.</p>
        </div>

        <div class="preview" aria-hidden="true">
            <div class="preview-top">
                <div><b>Billing module</b><span>Deliverable · as of today</span></div>
                <span class="pill lagged">LAGGED</span>
            </div>
            <div class="prow"><span>1. Preparing</span><div class="track"><div class="fill done" style="width:100%"></div><div class="mark" style="left:calc(100% - 1px)"></div></div><span class="num">100 / 100%</span></div>
            <div class="prow"><span>2. Planning</span><div class="track"><div class="fill done" style="width:100%"></div><div class="mark" style="left:calc(100% - 1px)"></div></div><span class="num">100 / 100%</span></div>
            <div class="prow"><span>3. Implementation</span><div class="track"><div class="fill late" style="width:48%"></div><div class="mark" style="left:63%"></div></div><span class="num">48 / 63%</span></div>
            <div class="prow"><span>4. Closing</span><div class="track"><div class="fill" style="width:0"></div><div class="mark" style="left:0"></div></div><span class="num">0 / 0%</span></div>
            <div class="preview-legend">
                <span><i style="width:14px;height:6px;border-radius:3px;background:var(--blue-gradient)"></i>Actual</span>
                <span><i style="width:2px;height:12px;background:var(--ink)"></i>Plan to date</span>
            </div>
        </div>

        <p class="auth-foot">PT Telkom Infrastruktur Indonesia · Project Management Office</p>
    </section>

    <main class="auth-form">
        <button class="icon-btn theme-toggle auth-theme" type="button" data-theme-toggle title="Switch theme" aria-label="Switch theme">
            <span class="when-light">@include('partials.icon', ['name' => 'moon'])</span>
            <span class="when-dark">@include('partials.icon', ['name' => 'sun'])</span>
        </button>
        <div class="auth-card">
            <span class="mobile-brand">
                @include('partials.logo', ['width' => 160])
                <span>TIF PMO · WBS</span>
            </span>

            <div>
                <h2>Sign in</h2>
                <p class="sub">Use your TIF admin or implementation partner account.</p>
            </div>

            <form method="POST" action="{{ route('login.attempt') }}" class="stack" style="gap:18px">
                @csrf

                @error('username')
                    <div class="notice err" role="alert">@include('partials.icon', ['name' => 'alert']) <span>{{ $message }}</span></div>
                @enderror

                <label>Username
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                           autocomplete="username" autocapitalize="none" spellcheck="false"
                           @error('username') aria-invalid="true" @enderror>
                </label>

                <label>Password
                    <span class="pw">
                        <input id="password" type="password" name="password" required autocomplete="current-password">
                        <button type="button" id="toggle-password" aria-label="Show password" aria-pressed="false">@include('partials.icon', ['name' => 'eye'])</button>
                    </span>
                </label>

                <label class="check">
                    <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Keep me signed in on this device
                </label>

                <button class="btn primary block" type="submit" style="min-height:46px;font-size:15px">Sign in</button>
            </form>

            <p class="auth-help">@include('partials.icon', ['name' => 'lock']) <span>Accounts are created by the TIF PMO admin. If you forgot your password, ask the admin to reset it.</span></p>
        </div>
    </main>
</div>
@endsection

@section('js')
    <script>
        (function () {
            var btn = document.getElementById('toggle-password'), input = document.getElementById('password');
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show);
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        })();
    </script>
@endsection
