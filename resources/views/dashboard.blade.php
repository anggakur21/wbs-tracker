@extends('layouts.app')

@section('title', 'Dashboard · TIF PMO')

@section('css')
    .dash-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;align-items:start}
    .dash-grid .wide{grid-column:1/-1}
    @container dash (max-width:860px){ .dash-grid{grid-template-columns:minmax(0,1fr)} }
    .dash{container:dash/inline-size;display:flex;flex-direction:column;gap:20px}

    .card-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:6px 12px;padding:16px 20px;border-bottom:1px solid var(--line)}
    .card-head h2{display:flex;align-items:center;gap:8px}
    .count{font-size:12px;font-weight:700;padding:1px 8px;border-radius:999px;background:var(--sunken);color:var(--ink-2)}
    .count.crit{background:var(--crit-bg);color:var(--crit)}
    .count.warn{background:var(--warn-bg);color:var(--warn)}
    .card-foot{padding:10px 20px;border-top:1px solid var(--line);font-size:13px;color:var(--muted)}

    /* Rows that link to a WBS step */
    .rows{list-style:none;margin:0;padding:0}
    .rows li+li{border-top:1px solid var(--line)}
    .row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:4px 14px;align-items:center;padding:12px 20px;color:inherit;transition:background .12s var(--ease)}
    a.row:hover{background:var(--hover);text-decoration:none;color:inherit}
    .row .t{font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .row .s{font-size:12.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .row .side-val{text-align:right;white-space:nowrap}
    .row .side-val b{display:block;font-size:13px;font-weight:700}
    .row .side-val span{font-size:12px;color:var(--muted)}
    .all-clear{display:flex;align-items:center;gap:10px;padding:22px 20px;color:var(--muted);font-size:14px}
    .all-clear svg{width:20px;height:20px;color:var(--good);flex:none}

    /* Overall progress */
    .overall{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:28px;padding:20px}
    @container dash (max-width:860px){ .overall{grid-template-columns:minmax(0,1fr)} }
    .big{display:flex;align-items:baseline;gap:10px;margin-bottom:12px}
    .big>b{font-size:40px;line-height:1;font-weight:800;letter-spacing:-.03em}
    .big span{color:var(--muted);font-size:14px}
    .track.tall{height:12px}
    .seg{display:flex;height:12px;border-radius:999px;overflow:hidden;background:var(--sunken);gap:2px}
    .seg i{display:block;height:100%}
    .seg .lagged{background:var(--crit-bar)} .seg .on_schedule{background:var(--good-bar)}
    .seg .completed{background:var(--accent)} .seg .not_started{background:var(--line-strong)}
    .seg-legend{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 16px;margin-top:14px;font-size:13px}
    .seg-legend span{display:flex;align-items:center;gap:8px;color:var(--ink-2)}
    .seg-legend i{width:10px;height:10px;border-radius:3px;flex:none}
    .seg-legend b{margin-left:auto;font-weight:700}

    /* Projects */
    .proj{display:grid;grid-template-columns:minmax(0,1.3fr) 128px minmax(0,1fr) 70px;gap:14px;align-items:center;padding:14px 20px;color:inherit}
    a.proj:hover{background:var(--hover);text-decoration:none;color:inherit}
    .proj .dev{text-align:right;font-weight:700}
    @container dash (max-width:700px){
        .proj{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"name pill" "bar dev"}
        .proj>:nth-child(1){grid-area:name} .proj>:nth-child(2){grid-area:pill} .proj>:nth-child(3){grid-area:bar} .proj>:nth-child(4){grid-area:dev}
    }

    .feed .who{font-weight:600;color:var(--ink)}
    .todo{list-style:none;margin:0;padding:0}
    .todo li{display:flex;gap:12px;align-items:flex-start;padding:12px 20px}
    .todo li+li{border-top:1px solid var(--line)}
    .todo svg{width:18px;height:18px;flex:none;margin-top:2px;color:var(--warn)}
    .todo .ok svg{color:var(--good)}
    .todo p{font-size:14px} .todo .s{font-size:12.5px;color:var(--muted)}
@endsection

@section('content')
    @php
        $isAdmin = $user->isAdmin();
        $hour = now()->hour;
        $greeting = $hour < 11 ? 'Good morning' : ($hour < 15 ? 'Good afternoon' : ($hour < 19 ? 'Good evening' : 'Hello'));
        $firstName = \Illuminate\Support\Str::before($user->name, ' ');
        $stepUrl = fn ($s) => route('deliverables.show', $s['deliverable']).'#act-'.$s['activity']->id;
        $where = fn ($s) => $s['project']->name.' · '.$s['deliverable']->name;
        $dev = $stats['actual'] - $stats['plan'];
        $days = fn ($n, $word) => $n.' '.$word.($n === 1 ? '' : 's');
    @endphp

    <div class="dash">
        <header class="page-head">
            <div class="titles">
                <h1>{{ $greeting }}, {{ $firstName }}</h1>
                <p class="sub">
                    {{ $today->format('l, j F Y') }} ·
                    {{ $isAdmin ? 'all TIF PMO projects' : 'projects of '.($user->partner?->name ?? 'your company') }}
                </p>
            </div>
            <div class="actions">
                <a class="btn" href="{{ route('projects.index') }}">@include('partials.icon', ['name' => 'folder']) All projects</a>
                @if ($isAdmin)
                    <a class="btn primary" href="{{ route('admin.projects.create') }}">@include('partials.icon', ['name' => 'plus']) New project</a>
                @endif
            </div>
        </header>

        @if ($projects->isEmpty())
            <div class="panel empty">
                @include('partials.icon', ['name' => 'inbox'])
                <b>No projects yet</b>
                @if ($isAdmin)
                    <p>Add an <a href="{{ route('admin.partners.index') }}">implementation partner</a>, then create the first project.</p>
                    <a class="btn primary" href="{{ route('admin.projects.create') }}" style="margin-top:6px">@include('partials.icon', ['name' => 'plus']) New project</a>
                @else
                    <p>No project is assigned to your company yet. Contact the TIF PMO admin.</p>
                @endif
            </div>
        @else
            <section class="stats" aria-label="Summary">
                <a class="stat" href="{{ route('projects.index') }}" style="color:inherit;text-decoration:none">
                    <span class="k">Projects</span>
                    <span class="v num">{{ $stats['projects'] }}</span>
                    <span class="note">{{ $days($stats['deliverables'], 'deliverable') }}</span>
                </a>
                <div class="stat">
                    <span class="k">Lagged deliverables</span>
                    <span class="v num {{ $stats['lagged'] ? 'c-crit' : '' }}">{{ $stats['lagged'] }} <small>/ {{ $stats['deliverables'] }}</small></span>
                    <span class="note">actual progress below plan</span>
                </div>
                <div class="stat">
                    <span class="k">Overdue steps</span>
                    <span class="v num {{ $stats['overdue'] ? 'c-crit' : '' }}">{{ $stats['overdue'] }}</span>
                    <span class="note">{{ $stats['behind'] }} more running behind</span>
                </div>
                @if ($isAdmin)
                    <a class="stat" href="#h-review" style="color:inherit;text-decoration:none">
                        <span class="k">Waiting for your review</span>
                        <span class="v num {{ $stats['in_review'] ? 'c-warn' : '' }}">{{ $stats['in_review'] }}</span>
                        <span class="note">steps submitted with evidence</span>
                    </a>
                @else
                    <a class="stat" href="#h-returned" style="color:inherit;text-decoration:none">
                        <span class="k">Returned for revision</span>
                        <span class="v num {{ $stats['returned'] ? 'c-crit' : '' }}">{{ $stats['returned'] }}</span>
                        <span class="note">{{ $stats['in_review'] }} waiting for TIF review</span>
                    </a>
                @endif
            </section>

            <section class="panel" aria-label="Overall progress">
                <div class="overall">
                    <div>
                        <h3>Overall progress</h3>
                        <div class="big">
                            <b class="num">{{ number_format($stats['actual'], 1) }}%</b>
                            <span>actual · plan to date {{ number_format($stats['plan'], 1) }}% ·
                                <b class="{{ $dev < 0 ? 'c-crit' : ($dev > 0 ? 'c-good' : '') }}">{{ $dev > 0 ? '+' : '' }}{{ number_format($dev, 1) }}%</b></span>
                        </div>
                        <div class="track tall" role="img" aria-label="Actual {{ round($stats['actual']) }}%, plan {{ round($stats['plan']) }}%">
                            <div class="fill {{ $dev < 0 ? 'late' : '' }}" style="width:{{ min(100, $stats['actual']) }}%"></div>
                            <div class="mark" style="left:calc({{ min(100, $stats['plan']) }}% - 1px)"></div>
                        </div>
                        <div class="legend" style="margin-top:10px">
                            <span><i class="l-fill"></i>Actual</span><span><i class="l-mark"></i>Plan to date</span>
                            <span>Average across {{ $days($stats['projects'], 'project') }}</span>
                        </div>
                    </div>
                    <div>
                        <h3>Deliverables by status</h3>
                        <div class="seg" role="img" aria-label="{{ $stats['lagged'] }} lagged, {{ $stats['on_schedule'] }} on schedule, {{ $stats['completed'] }} completed, {{ $stats['not_started'] }} not started">
                            @foreach (['lagged', 'on_schedule', 'completed', 'not_started'] as $key)
                                @if ($stats[$key]) <i class="{{ $key }}" style="flex:{{ $stats[$key] }}"></i> @endif
                            @endforeach
                        </div>
                        <div class="seg-legend">
                            <span><i style="background:var(--crit-bar)"></i>Lagged <b class="num">{{ $stats['lagged'] }}</b></span>
                            <span><i style="background:var(--good-bar)"></i>On schedule <b class="num">{{ $stats['on_schedule'] }}</b></span>
                            <span><i style="background:var(--accent)"></i>Completed <b class="num">{{ $stats['completed'] }}</b></span>
                            <span><i style="background:var(--line-strong)"></i>Not started <b class="num">{{ $stats['not_started'] }}</b></span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="dash-grid">
                {{-- Review queue (admins) / returned steps (partners) --}}
                @if ($isAdmin)
                    <section class="panel wide" aria-labelledby="h-review">
                        <div class="card-head">
                            <h2 id="h-review">Waiting for your review <span class="count {{ $stats['in_review'] ? 'warn' : '' }}">{{ $stats['in_review'] }}</span></h2>
                            <span class="note">Oldest submission first · accept or return each one</span>
                        </div>
                        @if ($reviewQueue->isEmpty())
                            <p class="all-clear">@include('partials.icon', ['name' => 'check']) Nothing is waiting for review.</p>
                        @else
                            <ul class="rows">
                                @foreach ($reviewQueue as $s)
                                    @php $r = $s['activity']->latestReview(); @endphp
                                    <li><a class="row" href="{{ $stepUrl($s) }}">
                                        <span style="min-width:0">
                                            <span class="t" style="display:block">{{ $s['activity']->name }}</span>
                                            <span class="s" style="display:block">{{ $where($s) }} · {{ $s['activity']->evidences->count() }} file(s){{ $r?->submitter ? ' · by '.$r->submitter->name : '' }}</span>
                                        </span>
                                        <span class="side-val"><b>Review</b><span>{{ $r?->submitted_at?->diffForHumans() }}</span></span>
                                    </a></li>
                                @endforeach
                            </ul>
                            @if ($stats['in_review'] > $reviewQueue->count())
                                <p class="card-foot">Showing the {{ $reviewQueue->count() }} oldest of {{ $stats['in_review'] }}.</p>
                            @endif
                        @endif
                    </section>
                @elseif ($returned->isNotEmpty())
                    <section class="panel wide" aria-labelledby="h-returned" style="border-color:color-mix(in srgb,var(--crit) 35%,var(--line))">
                        <div class="card-head">
                            <h2 id="h-returned">Returned for revision <span class="count crit">{{ $stats['returned'] }}</span></h2>
                            <span class="note">A TIF admin asked for changes</span>
                        </div>
                        <ul class="rows">
                            @foreach ($returned as $s)
                                @php $r = $s['activity']->returnedReview(); @endphp
                                <li><a class="row" href="{{ $stepUrl($s) }}">
                                    <span style="min-width:0">
                                        <span class="t" style="display:block">{{ $s['activity']->name }}</span>
                                        <span class="s" style="display:block">{{ $where($s) }}</span>
                                        <span class="s" style="display:block;color:var(--crit);white-space:normal">“{{ \Illuminate\Support\Str::limit($r->reason, 160) }}” · {{ $r->reviewer?->name }}</span>
                                    </span>
                                    <span class="side-val"><b class="c-crit">Revise</b><span>{{ $r->reviewed_at->diffForHumans() }}</span></span>
                                </a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Overdue --}}
                <section class="panel" aria-labelledby="h-overdue">
                    <div class="card-head">
                        <h2 id="h-overdue">Overdue steps <span class="count {{ $stats['overdue'] ? 'crit' : '' }}">{{ $stats['overdue'] }}</span></h2>
                        <span class="note">Past plan finish, not at 100%</span>
                    </div>
                    @if ($overdue->isEmpty())
                        <p class="all-clear">@include('partials.icon', ['name' => 'check']) Nothing is overdue.</p>
                    @else
                        <ul class="rows">
                            @foreach ($overdue as $s)
                                @php $late = (int) $s['activity']->plan_finish->diffInDays($today); @endphp
                                <li><a class="row" href="{{ $stepUrl($s) }}">
                                    <span style="min-width:0">
                                        <span class="t" style="display:block">{{ $s['activity']->name }}</span>
                                        <span class="s" style="display:block">{{ $where($s) }}</span>
                                    </span>
                                    <span class="side-val"><b class="c-crit">{{ $days($late, 'day') }} late</b><span class="num">{{ $s['activity']->progress }}% done</span></span>
                                </a></li>
                            @endforeach
                        </ul>
                        @if ($stats['overdue'] > $overdue->count())
                            <p class="card-foot">Showing the {{ $overdue->count() }} oldest of {{ $stats['overdue'] }}. Open a project to see the rest.</p>
                        @endif
                    @endif
                </section>

                {{-- Upcoming --}}
                <section class="panel" aria-labelledby="h-upcoming">
                    <div class="card-head">
                        <h2 id="h-upcoming">Due in the next {{ $upcomingDays }} days <span class="count">{{ $upcomingCount }}</span></h2>
                        <span class="note">Plan finish coming up</span>
                    </div>
                    @if ($upcoming->isEmpty())
                        <p class="all-clear">@include('partials.icon', ['name' => 'check']) No steps are due in the next {{ $upcomingDays }} days.</p>
                    @else
                        <ul class="rows">
                            @foreach ($upcoming as $s)
                                @php $left = (int) $today->diffInDays($s['activity']->plan_finish); @endphp
                                <li><a class="row" href="{{ $stepUrl($s) }}">
                                    <span style="min-width:0">
                                        <span class="t" style="display:block">{{ $s['activity']->name }}</span>
                                        <span class="s" style="display:block">{{ $where($s) }}</span>
                                    </span>
                                    <span class="side-val">
                                        <b class="{{ $s['status'] === 'behind' ? 'c-warn' : '' }}">{{ $left === 0 ? 'Due today' : 'in '.$days($left, 'day') }}</b>
                                        <span class="num">{{ $s['activity']->progress }}% · {{ $s['activity']->plan_finish->format('j M') }}</span>
                                    </span>
                                </a></li>
                            @endforeach
                        </ul>
                        @if ($upcomingCount > $upcoming->count())
                            <p class="card-foot">Showing the next {{ $upcoming->count() }} of {{ $upcomingCount }}.</p>
                        @endif
                    @endif
                </section>

                {{-- Projects --}}
                <section class="panel wide" aria-labelledby="h-projects">
                    <div class="card-head">
                        <h2 id="h-projects">Projects <span class="count">{{ $stats['projects'] }}</span></h2>
                        <span class="note">Furthest behind plan first · <a href="{{ route('projects.index') }}">Full list</a></span>
                    </div>
                    <ul class="rows">
                        @foreach ($projects as $project)
                            @php $pp = $project->planPercent(); $pa = $project->actualPercent(); $pd = $project->deviation(); @endphp
                            <li><a class="proj" href="{{ route('projects.show', $project) }}">
                                <span style="min-width:0">
                                    <span class="t" style="display:block;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $project->name }}</span>
                                    <span class="s" style="display:block;font-size:12.5px;color:var(--muted)">
                                        {{ $isAdmin ? ($project->partner?->name ?? '–').' · ' : '' }}{{ $days($project->deliverables->count(), 'deliverable') }}{{ $project->laggedDeliverableCount() ? ', '.$project->laggedDeliverableCount().' lagged' : '' }}
                                    </span>
                                </span>
                                <span><span class="pill {{ $project->status() }}">{{ $project->statusLabel() }}</span></span>
                                <span>
                                    <span class="track" role="img" aria-label="Actual {{ round($pa) }}%, plan {{ round($pp) }}%" style="display:block;margin-bottom:5px">
                                        <span class="fill {{ $pa >= 100 ? 'done' : ($pa < $pp ? 'late' : '') }}" style="display:block;width:{{ min(100, $pa) }}%"></span>
                                        <span class="mark" style="display:block;left:calc({{ min(100, $pp) }}% - 1px)"></span>
                                    </span>
                                    <span class="num small muted">{{ number_format($pa, 1) }}% / {{ number_format($pp, 1) }}%</span>
                                </span>
                                <span class="dev num {{ $pd < 0 ? 'c-crit' : ($pd > 0 ? 'c-good' : '') }}">{{ $pd > 0 ? '+' : '' }}{{ number_format($pd, 1) }}%</span>
                            </a></li>
                        @endforeach
                    </ul>
                </section>

                {{-- Evidence missing --}}
                <section class="panel" aria-labelledby="h-evidence">
                    <div class="card-head">
                        <h2 id="h-evidence">Evidence missing <span class="count {{ $stats['missing_evidence'] ? 'warn' : '' }}">{{ $stats['missing_evidence'] }}</span></h2>
                        <span class="note">Finished steps with no file</span>
                    </div>
                    @if ($missingEvidence->isEmpty())
                        <p class="all-clear">@include('partials.icon', ['name' => 'check']) Every finished step has evidence.</p>
                    @else
                        <ul class="rows">
                            @foreach ($missingEvidence as $s)
                                <li><a class="row" href="{{ $stepUrl($s) }}">
                                    <span style="min-width:0">
                                        <span class="t" style="display:block">{{ $s['activity']->name }}</span>
                                        <span class="s" style="display:block">{{ $where($s) }} · needs {{ $s['activity']->evidence_required ?: 'evidence' }}</span>
                                    </span>
                                    <span class="side-val"><span class="tag">{{ $isAdmin ? 'Review' : 'Upload' }}</span></span>
                                </a></li>
                            @endforeach
                        </ul>
                        @if ($stats['missing_evidence'] > $missingEvidence->count())
                            <p class="card-foot">Showing {{ $missingEvidence->count() }} of {{ $stats['missing_evidence'] }}.</p>
                        @endif
                    @endif
                </section>

                {{-- Recent activity --}}
                <section class="panel feed" aria-labelledby="h-recent">
                    <div class="card-head">
                        <h2 id="h-recent">Recent activity</h2>
                        <span class="note">Latest updates and uploads</span>
                    </div>
                    @if ($recent->isEmpty())
                        <p class="all-clear" style="color:var(--muted)">No updates yet.</p>
                    @else
                        <ul class="rows">
                            @foreach ($recent as $r)
                                <li><a class="row" href="{{ $stepUrl($r) }}">
                                    <span style="min-width:0">
                                        <span class="s" style="display:block;color:var(--ink-2);font-size:13.5px">
                                            <span class="who">{{ $r['who']?->name ?? 'Deleted user' }}</span> {{ $r['text'] }}
                                        </span>
                                        <span class="s" style="display:block">{{ $r['activity']->name }} · {{ $r['deliverable']->name }}</span>
                                    </span>
                                    <span class="side-val"><span title="{{ $r['at']->format('j M Y H:i') }}">{{ $r['at']->diffForHumans(null, true, true) }} ago</span></span>
                                </a></li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- Admin housekeeping --}}
                @if ($admin)
                    @php
                        $noAccounts = $admin['partnersWithoutAccounts'];
                        $waiting = $admin['awaitingFirstSignIn'];
                        $allGood = $noAccounts->isEmpty() && $waiting->isEmpty() && $admin['driveConnected'];
                    @endphp
                    <section class="panel wide" aria-labelledby="h-admin">
                        <div class="card-head">
                            <h2 id="h-admin">Admin to-do</h2>
                            <span class="note">{{ $days($admin['partners'], 'partner') }} · <a href="{{ route('admin.partners.index') }}">Manage partners</a></span>
                        </div>
                        <ul class="todo">
                            @if ($allGood)
                                <li class="ok">@include('partials.icon', ['name' => 'check']) <p>Everything is set up: every partner has accounts, everyone has signed in, and Google Drive is connected.</p></li>
                            @endif
                            @if ($noAccounts->isNotEmpty())
                                <li>@include('partials.icon', ['name' => 'alert'])
                                    <div><p>{{ $days($noAccounts->count(), 'partner') }} {{ $noAccounts->count() === 1 ? 'has' : 'have' }} no sign-in account, so nobody can update their WBS.</p>
                                        <p class="s">@foreach ($noAccounts as $p)<a href="{{ route('admin.partners.show', $p) }}">{{ $p->name }}</a>{{ $loop->last ? '' : ', ' }}@endforeach</p></div>
                                </li>
                            @endif
                            @if ($waiting->isNotEmpty())
                                <li>@include('partials.icon', ['name' => 'key'])
                                    <div><p>{{ $days($waiting->count(), 'account') }} {{ $waiting->count() === 1 ? 'has' : 'have' }} not signed in yet with the one-time password.</p>
                                        <p class="s">@foreach ($waiting as $u)<a href="{{ route('admin.partners.show', $u->partner_id) }}">{{ $u->name }}</a> ({{ $u->partner?->name }}){{ $loop->last ? '' : ', ' }}@endforeach</p></div>
                                </li>
                            @endif
                            @unless ($admin['driveConnected'])
                                <li>@include('partials.icon', ['name' => 'cloud'])
                                    <div><p>Google Drive is not connected. Evidence files are stored on the server.</p>
                                        <p class="s"><a href="{{ route('admin.drive.show') }}">{{ $admin['driveConfigured'] ? 'Connect Google Drive' : 'Set up Google Drive' }}</a></p></div>
                                </li>
                            @endunless
                        </ul>
                    </section>
                @endif
            </div>
        @endif
    </div>
@endsection
