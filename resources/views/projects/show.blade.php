@extends('layouts.app')

@section('title', $project->name.' · TIF PMO')

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
        $plan = $project->planPercent();
        $actual = $project->actualPercent();
        $dev = $project->deviation();
        $pct = fn ($n) => number_format($n, 1).'%';
        $bag = $errors->getBag('deliverable');
    @endphp

    <header class="page-head">
        <div class="titles">
            <nav class="crumbs" aria-label="Breadcrumb">
                <a href="{{ route('projects.index') }}">Projects</a>
                @include('partials.icon', ['name' => 'chevron'])
                <span>{{ $project->contract_number ?: 'Project' }}</span>
            </nav>
            <h1>{{ $project->name }}</h1>
            <div class="meta">
                <span>Contract no. <b>{{ $project->contract_number ?: '–' }}</b></span>
                <span>Value <b>{{ $project->contract_value ? 'Rp '.number_format($project->contract_value, 0, ',', '.') : '–' }}</b></span>
                <span>Partner <b>{{ $project->partner?->name ?? '–' }}</b></span>
            </div>
        </div>
        <div class="actions">
            <span class="pill lg {{ $project->status() }}">{{ $project->statusLabel() }}</span>
            @if ($isAdmin)
                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}"
                      onsubmit="return confirm('Delete this project with all its deliverables, progress and evidence?')">
                    @csrf @method('DELETE')
                    <button class="btn sm danger" type="submit">@include('partials.icon', ['name' => 'trash']) Delete project</button>
                </form>
            @endif
        </div>
    </header>

    @error('delete')
        <div class="notice err" role="alert">@include('partials.icon', ['name' => 'alert']) <span>{{ $message }}</span></div>
    @enderror

    <section class="stats" aria-label="Project status as of {{ today()->format('j M Y') }}">
        <div class="stat">
            <span class="k">Plan to date</span>
            <span class="v num">{{ $pct($plan) }}</span>
            <div class="track"><div class="fill" style="width:{{ min(100, $plan) }}%;background:var(--line-strong)"></div></div>
        </div>
        <div class="stat">
            <span class="k">Actual</span>
            <span class="v num">{{ $pct($actual) }}</span>
            <div class="track"><div class="fill {{ $actual >= 100 ? 'done' : ($actual < $plan ? 'late' : '') }}" style="width:{{ min(100, $actual) }}%"></div></div>
        </div>
        <div class="stat">
            <span class="k">Deviation</span>
            <span class="v num {{ $dev < 0 ? 'c-crit' : ($dev > 0 ? 'c-good' : '') }}">{{ $dev > 0 ? '+' : '' }}{{ $pct($dev) }}</span>
            <span class="note">{{ $dev < 0 ? 'Behind plan' : ($dev > 0 ? 'Ahead of plan' : 'On plan') }}</span>
        </div>
        <div class="stat">
            <span class="k">Lagged deliverables</span>
            <span class="v num {{ $project->laggedDeliverableCount() ? 'c-crit' : '' }}">{{ $project->laggedDeliverableCount() }} <small>/ {{ $project->deliverables->count() }}</small></span>
            <span class="note">as of {{ today()->format('j M Y') }}</span>
        </div>
    </section>

    <div class="section-head">
        <h2>Deliverables</h2>
        <p>Each deliverable runs the full WBS and is tracked separately. Project figures are their average.</p>
    </div>

    <section class="panel tablewrap">
        <table>
            <thead>
            <tr>
                <th>Deliverable</th><th>Status</th><th style="min-width:180px">Progress (actual / plan)</th><th class="r">Deviation</th><th class="r">Overdue steps</th><th class="r">Evidence</th>
                @if ($isAdmin) <th><span class="sr-only">Actions</span></th> @endif
            </tr>
            </thead>
            <tbody>
            @foreach ($project->deliverables as $d)
                @php
                    $dp = $d->planPercent();
                    $da = $d->actualPercent();
                    $dd = $d->deviation();
                    $overdue = $d->activities->filter(fn ($a) => $a->schedule() === 'late')->count();
                @endphp
                <tr>
                    <td><a class="cell-title" href="{{ route('deliverables.show', $d) }}">{{ $d->name }}</a></td>
                    <td><span class="pill {{ $d->status() }}">{{ $d->statusLabel() }}</span></td>
                    <td>
                        <div class="track" role="img" aria-label="Actual {{ round($da) }}%, plan {{ round($dp) }}%" style="margin:4px 0 6px">
                            <div class="fill {{ $da >= 100 ? 'done' : ($da < $dp ? 'late' : '') }}" style="width:{{ min(100, $da) }}%"></div>
                            <div class="mark" style="left:calc({{ min(100, $dp) }}% - 1px)"></div>
                        </div>
                        <span class="num small muted">{{ $pct($da) }} / {{ $pct($dp) }}</span>
                    </td>
                    <td class="num r {{ $dd < 0 ? 'c-crit' : ($dd > 0 ? 'c-good' : '') }}">{{ $dd > 0 ? '+' : '' }}{{ $pct($dd) }}</td>
                    <td class="num r {{ $overdue ? 'c-crit' : '' }}">{{ $overdue }}</td>
                    <td class="num r">{{ $d->evidenceCoverage() }}/{{ $d->activities->count() }}</td>
                    @if ($isAdmin)
                        <td class="r">
                            <form method="POST" action="{{ route('admin.deliverables.destroy', $d) }}"
                                  onsubmit="return confirm('Delete this deliverable with its progress and evidence?')">
                                @csrf @method('DELETE')
                                <button class="linkbtn danger" type="submit">Delete</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <div class="legend">
        <span><i class="l-fill"></i>Actual progress</span>
        <span><i class="l-mark"></i>Plan to date</span>
    </div>

    @if ($isAdmin)
        <section class="panel pad">
            <div class="panel-head">
                <h2>Add deliverable</h2>
            </div>
            <form method="POST" action="{{ route('admin.deliverables.store', $project) }}" class="inline-form">
                @csrf
                <label>Deliverable name
                    <input id="deliverable-name" type="text" name="name" value="{{ $bag->any() ? old('name') : '' }}" required maxlength="255" placeholder="e.g. Reporting module">
                </label>
                <button class="btn primary" type="submit">@include('partials.icon', ['name' => 'plus']) Add deliverable</button>
            </form>
            <p class="hint" style="margin-top:10px">
                It gets the same WBS steps, weights and plan dates as "{{ $project->deliverables->first()?->name }}", with progress at 0%. Plan dates can be adjusted per step afterwards.
            </p>
            @foreach ($bag->all() as $message)
                <div class="err-text" role="alert" style="margin-top:8px">{{ $message }}</div>
            @endforeach
        </section>
    @endif
@endsection
