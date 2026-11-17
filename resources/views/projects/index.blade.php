@extends('layouts.app')

@section('title', 'Projects · TIF PMO')

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
        $laggedProjects = $projects->filter(fn ($p) => $p->status() === 'lagged')->count();
        $deliverableCount = $projects->sum(fn ($p) => $p->deliverables->count());
        $laggedDeliverables = $projects->sum(fn ($p) => $p->laggedDeliverableCount());
        $avgActual = $projects->isEmpty() ? 0 : $projects->avg(fn ($p) => $p->actualPercent());
    @endphp

    <header class="page-head">
        <div class="titles">
            <h1>Projects</h1>
            <p class="sub">
                {{ $isAdmin ? 'All TIF PMO projects' : 'Projects assigned to your company' }}, measured against plan as of {{ today()->format('j M Y') }}.
            </p>
        </div>
        @if ($isAdmin)
            <a class="btn primary" href="{{ route('admin.projects.create') }}">@include('partials.icon', ['name' => 'plus']) New project</a>
        @endif
    </header>

    @if ($projects->isEmpty())
        <div class="panel empty">
            @include('partials.icon', ['name' => 'inbox'])
            <b>No projects yet</b>
            @if ($isAdmin)
                <p>Add an <a href="{{ route('admin.partners.index') }}">implementation partner</a> first, then start a new project.</p>
                <a class="btn primary" href="{{ route('admin.projects.create') }}" style="margin-top:6px">@include('partials.icon', ['name' => 'plus']) New project</a>
            @else
                <p>No project is assigned to your account yet. Contact the admin.</p>
            @endif
        </div>
    @else
        <section class="stats" aria-label="Summary">
            <div class="stat"><span class="k">Projects</span><span class="v num">{{ $projects->count() }}</span></div>
            <div class="stat">
                <span class="k">Lagged projects</span>
                <span class="v num {{ $laggedProjects ? 'c-crit' : '' }}">{{ $laggedProjects }} <small>/ {{ $projects->count() }}</small></span>
            </div>
            <div class="stat">
                <span class="k">Lagged deliverables</span>
                <span class="v num {{ $laggedDeliverables ? 'c-crit' : '' }}">{{ $laggedDeliverables }} <small>/ {{ $deliverableCount }}</small></span>
            </div>
            <div class="stat">
                <span class="k">Average actual progress</span>
                <span class="v num">{{ number_format($avgActual, 1) }}<small>%</small></span>
                <div class="track"><div class="fill" style="width:{{ min(100, $avgActual) }}%"></div></div>
            </div>
        </section>

        <section class="panel tablewrap">
            <table>
                <thead>
                <tr>
                    <th>Project</th><th>Partner</th><th>Status</th><th style="min-width:180px">Progress (actual / plan)</th><th class="r">Deviation</th><th class="r">Deliverables</th><th class="r">Lagged</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($projects as $project)
                    @php
                        $plan = $project->planPercent();
                        $actual = $project->actualPercent();
                        $dev = $project->deviation();
                        $lagged = $project->laggedDeliverableCount();
                    @endphp
                    <tr>
                        <td>
                            <a class="cell-title" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                            <div class="cell-sub">{{ $project->contract_number ?: 'No contract number' }}</div>
                        </td>
                        <td>{{ $project->partner?->name ?? '–' }}</td>
                        <td><span class="pill {{ $project->status() }}">{{ $project->statusLabel() }}</span></td>
                        <td>
                            <div class="track" role="img" aria-label="Actual {{ round($actual) }}%, plan {{ round($plan) }}%" style="margin:4px 0 6px">
                                <div class="fill {{ $actual >= 100 ? 'done' : ($actual < $plan ? 'late' : '') }}" style="width:{{ min(100, $actual) }}%"></div>
                                <div class="mark" style="left:calc({{ min(100, $plan) }}% - 1px)"></div>
                            </div>
                            <span class="num small muted">{{ number_format($actual, 1) }}% / {{ number_format($plan, 1) }}%</span>
                        </td>
                        <td class="num r {{ $dev < 0 ? 'c-crit' : ($dev > 0 ? 'c-good' : '') }}">{{ $dev > 0 ? '+' : '' }}{{ number_format($dev, 1) }}%</td>
                        <td class="num r">{{ $project->deliverables->count() }}</td>
                        <td class="num r {{ $lagged ? 'c-crit' : '' }}">{{ $lagged }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <div class="legend">
            <span><i class="l-fill"></i>Actual progress</span>
            <span><i class="l-mark"></i>Plan to date</span>
            <span>Lagged: weighted actual progress is below the plan up to today. Project figures are the average of its deliverables.</span>
        </div>
    @endif
@endsection
