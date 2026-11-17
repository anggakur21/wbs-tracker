@extends('layouts.app')

@section('title', 'Google Drive · TIF PMO')

@section('css')
    .drive-status{display:flex;flex-wrap:wrap;gap:16px 24px;align-items:center;justify-content:space-between}
    .drive-id{display:flex;gap:14px;align-items:center}
    .drive-logo{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;background:var(--sunken);flex:none}
    .drive-logo svg{width:28px;height:28px}
    .steps{margin:0;padding-left:20px;display:flex;flex-direction:column;gap:8px;font-size:14px;color:var(--ink-2)}
    .steps code{font-family:var(--mono);font-size:13px;background:var(--sunken);padding:1px 6px;border-radius:5px;overflow-wrap:anywhere}
    .sync{display:flex;flex-direction:column;gap:18px}
    .sync-head{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:flex-start;justify-content:space-between}
    .sync-head>div{flex:1 1 360px}
    .sync-figs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .sync-figs>div{display:flex;flex-direction:column;gap:2px;padding:12px 14px;background:var(--hover);border-radius:var(--r)}
    .sync-figs .k{font-size:12.5px;color:var(--muted);font-weight:500}
    .sync-figs b{font-size:24px;font-weight:800;letter-spacing:-.02em}
    .sync-failed{margin:8px 0 0;padding-left:18px;font-size:13px;color:var(--crit)}
    .locked{font-size:13px;color:var(--muted);display:flex;gap:8px;align-items:center}
    .locked svg{width:15px;height:15px}
    .track.tall{height:10px}
    @media (max-width:640px){.sync-figs{grid-template-columns:minmax(0,1fr)}}
    .tree{font-family:var(--mono);font-size:13px;color:var(--ink-2);background:var(--hover);border-radius:var(--r);padding:12px 14px;line-height:1.7;margin:0;white-space:pre;overflow-x:auto}
@endsection

@section('content')
    <header class="page-head">
        <div class="titles">
            <h1>Google Drive</h1>
            <p class="sub">Store evidence files in a Google Drive account instead of on the server.</p>
        </div>
    </header>

    @error('drive')
        <div class="notice err" role="alert">@include('partials.icon', ['name' => 'alert']) <span>{{ $message }}</span></div>
    @enderror

    <section class="panel pad">
        <div class="drive-status">
            <div class="drive-id">
                <span class="drive-logo" aria-hidden="true">
                    <svg viewBox="0 0 87.3 78"><path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="M43.65 25 29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3L1.2 48.5A9 9 0 0 0 0 53h27.5z" fill="#00ac47"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.85 11.5z" fill="#ea4335"/><path d="M43.65 25 57.4 1.2C56.05.4 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/><path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z" fill="#2684fc"/><path d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/></svg>
                </span>
                <div>
                    @if ($connected)
                        <span class="pill on_schedule">CONNECTED</span>
                        <p style="margin-top:6px"><b>{{ $connection['name'] ?? '' }}</b> <span class="muted">{{ $connection['email'] ?? '' }}</span></p>
                        <p class="note">
                            Connected {{ \Illuminate\Support\Carbon::parse($connection['connected_at'])->format('j M Y H:i') }}{{ $connectedBy ? ' by '.$connectedBy->name : '' }}.
                            @if (! empty($connection['root_folder_url']))
                                <a href="{{ $connection['root_folder_url'] }}" target="_blank" rel="noopener">Open the evidence folder</a>
                            @endif
                        </p>
                    @else
                        <span class="pill not_started">NOT CONNECTED</span>
                        <p class="note" style="margin-top:6px">Evidence files are stored on the server's private disk.</p>
                    @endif
                </div>
            </div>
            <div class="actions">
                @if ($connected)
                    <form method="POST" action="{{ route('admin.drive.connect') }}">
                        @csrf
                        <button class="btn" type="submit">@include('partials.icon', ['name' => 'refresh']) Reconnect</button>
                    </form>
                    <form method="POST" action="{{ route('admin.drive.disconnect') }}"
                          onsubmit="return confirm('Disconnect Google Drive? New evidence will be stored on the server. Files already in Drive stay there but cannot be downloaded from the app until Drive is reconnected to the same account.')">
                        @csrf @method('DELETE')
                        <button class="btn danger" type="submit">Disconnect</button>
                    </form>
                @elseif ($configured)
                    <form method="POST" action="{{ route('admin.drive.connect') }}">
                        @csrf
                        <button class="btn primary" type="submit">@include('partials.icon', ['name' => 'cloud']) Connect Google Drive</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    @php $total = $counts['google'] + $counts['local']; @endphp
    <section class="panel pad sync" aria-labelledby="h-sync">
        <div class="sync-head">
            <div>
                <h2 id="h-sync">Sync server files to Google Drive</h2>
                <p class="note" style="margin-top:4px">
                    Copies every evidence file that is only on the server into Drive, in the same project, deliverable and step folders.
                    The server copy is kept as a backup.
                </p>
            </div>
            @if ($connected)
                <form method="POST" action="{{ route('admin.drive.sync') }}" id="sync-form">
                    @csrf
                    <button class="btn primary" type="submit" id="sync-btn" @disabled($counts['local'] === 0)>
                        @include('partials.icon', ['name' => 'refresh']) {{ $counts['local'] ? 'Sync '.$counts['local'].' file'.($counts['local'] === 1 ? '' : 's') : 'Everything is synced' }}
                    </button>
                </form>
            @endif
        </div>

        <div class="sync-figs">
            <div><span class="k">In Google Drive</span><b class="num" id="n-drive">{{ $counts['google'] }}</b><span class="note"><span id="n-backed">{{ $counts['backedUp'] }}</span> also kept on the server</span></div>
            <div><span class="k">On the server only</span><b class="num {{ $counts['local'] ? 'c-warn' : '' }}" id="n-local">{{ $counts['local'] }}</b><span class="note">not in Drive yet</span></div>
            <div><span class="k">Total evidence files</span><b class="num" id="n-total">{{ $total }}</b><span class="note"><span id="n-pct">{{ $total ? round($counts['google'] / $total * 100) : 100 }}</span>% in Drive</span></div>
        </div>

        <div id="sync-progress" hidden>
            <div class="track tall"><div class="fill" id="sync-fill" style="width:0%"></div></div>
            <p class="small" id="sync-text" role="status" aria-live="polite" style="margin-top:8px"></p>
            <ul class="sync-failed" id="sync-failed" hidden></ul>
        </div>

        @unless ($connected)
            <p class="locked">@include('partials.icon', ['name' => 'lock']) Connect Google Drive first, then sync the {{ $counts['local'] }} file(s) on the server.</p>
        @endunless
    </section>

    @unless ($configured)
        <section class="panel pad">
            <div class="panel-head"><h2>Set up the Google connection</h2></div>
            <p class="note" style="margin-bottom:14px">This is done once by whoever runs the server. After that, an admin connects a Drive account with one click.</p>
            <ol class="steps">
                <li>In <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>, create a project (or pick one) and enable the <b>Google Drive API</b>.</li>
                <li>Configure the OAuth consent screen. For a Google Workspace domain choose <b>Internal</b>.</li>
                <li>Create an <b>OAuth client ID</b> of type <b>Web application</b> and add this authorised redirect URI:<br><code>{{ $redirectUri }}</code></li>
                <li>Put the client ID and secret in the server's <code>.env</code>: <code>GOOGLE_DRIVE_CLIENT_ID=…</code> and <code>GOOGLE_DRIVE_CLIENT_SECRET=…</code>, then reload this page.</li>
            </ol>
        </section>
    @endunless

    <section class="panel pad">
        <div class="panel-head"><h2>How it works</h2></div>
        <div class="stack" style="gap:14px">
            <p class="note">Once connected, every new evidence upload goes into this folder structure in the connected Drive:</p>
<pre class="tree">TIF PMO – Evidence/
└─ Project name/
   └─ Deliverable name/
      └─ 03 Kickoff &amp; Design Review Meeting/
         └─ MoM-kickoff.pdf</pre>
            <ul class="steps">
                <li>The app only gets access to files it creates itself (Google's <code>drive.file</code> permission). It can't see anything else in that Drive.</li>
                <li>Partners download evidence through the app as before, so they don't need access to the Drive. Deleting evidence in the app moves the file to the Drive's trash.</li>
                <li>Files uploaded before connecting stay on the server until you press <b>Sync</b>. If Drive is unreachable during an upload, the file is kept on the server and the partner is told; the next sync copies it.</li>
                <li>Synced files keep their server copy. If Drive can't be reached, downloads fall back to it. Deleting evidence removes both copies.</li>
                <li>For a very large backlog, the server can run the same sync from the command line: <code>php artisan drive:sync</code>.</li>
            </ul>
        </div>
    </section>
@endsection

@section('js')
    <script>
        // Runs the sync in batches so the page never times out, with a progress bar.
        (function () {
            var form = document.getElementById('sync-form');
            if (!form) return;
            var btn = document.getElementById('sync-btn'), box = document.getElementById('sync-progress'),
                fill = document.getElementById('sync-fill'), text = document.getElementById('sync-text'),
                failedList = document.getElementById('sync-failed'),
                token = form.querySelector('input[name=_token]').value;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var start = parseInt(document.getElementById('n-local').textContent, 10) || 0,
                    synced = 0, failed = [], skip = [],
                    backed0 = parseInt(document.getElementById('n-backed').textContent, 10) || 0;
                btn.disabled = true; box.hidden = false;
                text.textContent = 'Starting…';

                function step() {
                    fetch(form.action, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                        body: JSON.stringify({skip: skip})
                    }).then(function (r) {
                        if (!r.ok) return r.json().then(function (j) { throw new Error(j.message || ('Error ' + r.status)); });
                        return r.json();
                    }).then(function (res) {
                        synced += res.synced;
                        failed = failed.concat(res.failed);
                        skip = failed.map(function (f) { return f.id; });
                        var done = synced + failed.length, total = Math.max(start, done + res.remaining);
                        fill.style.width = (total ? Math.round(done / total * 100) : 100) + '%';
                        document.getElementById('n-drive').textContent = res.total_in_drive;
                        document.getElementById('n-backed').textContent = backed0 + synced;
                        var all = parseInt(document.getElementById('n-total').textContent, 10) || 0;
                        document.getElementById('n-pct').textContent = all ? Math.round(res.total_in_drive / all * 100) : 100;
                        document.getElementById('n-local').textContent = res.remaining + failed.length;
                        text.textContent = 'Copied ' + synced + ' of ' + total + ' file(s) to Google Drive' + (res.remaining ? '…' : '.');
                        if (failed.length) {
                            failedList.hidden = false;
                            failedList.innerHTML = failed.map(function (f) {
                                var li = document.createElement('li'); li.textContent = f.name + ': ' + f.reason; return li.outerHTML;
                            }).join('');
                        }
                        if (res.remaining > 0 && (res.synced + res.failed.length) > 0) return step();
                        text.textContent = failed.length
                            ? 'Done. ' + synced + ' file(s) copied; ' + failed.length + ' could not be copied (listed below).'
                            : 'Done. ' + synced + ' file(s) copied. Every server file is now in Google Drive.';
                        fill.classList.toggle('done', !failed.length);
                        btn.disabled = false;
                        btn.lastChild.textContent = failed.length ? ' Retry' : ' Everything is synced';
                        if (!failed.length) btn.disabled = true;
                    }).catch(function (err) {
                        text.textContent = 'The sync stopped: ' + err.message + '. Press the button to continue.';
                        fill.classList.add('late');
                        btn.disabled = false;
                    });
                }
                step();
            });
        })();
    </script>
@endsection

