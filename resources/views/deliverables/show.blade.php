@extends('layouts.app')

@section('title', $deliverable->name.' · '.$deliverable->project->name)

@section('css')
    .phases{display:flex;flex-direction:column;gap:14px}
    .phase-bar{display:grid;grid-template-columns:140px minmax(0,1fr) 84px;gap:12px;align-items:center;font-size:13.5px}
    .phase-bar .num{text-align:right;color:var(--muted);font-size:12.5px}
    .weight-warn{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:var(--warn);background:var(--warn-bg);border-radius:var(--r);padding:10px 12px}
    .weight-warn svg{width:16px;height:16px;flex:none;margin-top:2px}

    .wbs{overflow:hidden;container:wbs/inline-size}
    .phase-head{padding:14px 18px;background:var(--hover);border-bottom:1px solid var(--line);display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;justify-content:space-between}
    .phase-head .ph-title{display:flex;align-items:center;gap:10px}
    .phase-no{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;background:var(--accent-soft);color:var(--accent);font-weight:600;font-size:13px}
    .cols,.act>summary{display:grid;grid-template-columns:46px minmax(0,1fr) 56px 196px 132px 112px 60px;gap:12px;align-items:center;padding:12px 18px}
    .cols{font-size:12px;color:var(--muted);border-bottom:1px solid var(--line);padding-top:9px;padding-bottom:9px}
    .act{border-bottom:1px solid var(--line)}
    .act:last-child{border-bottom:0}
    .act>summary{cursor:pointer;list-style:none;position:relative;transition:background .12s var(--ease)}
    .act>summary::-webkit-details-marker{display:none}
    .act>summary:hover{background:var(--hover)}
    .act[open]>summary{background:var(--accent-soft)}
    .act[open]>summary::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--accent)}
    .act .name{font-weight:500;overflow-wrap:anywhere}
    .prog{display:grid;grid-template-columns:minmax(0,1fr) 40px;gap:10px;align-items:center}
    .ev-count{display:inline-flex;align-items:center;gap:5px}
    .ev-count svg{width:14px;height:14px}

    .detail{padding:22px 18px 24px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:28px;border-top:1px solid var(--line);background:var(--surface)}
    .detail .full{grid-column:1/-1;border-top:1px dashed var(--line-strong);padding-top:20px}
    .kv{display:grid;grid-template-columns:130px minmax(0,1fr);gap:6px 12px;font-size:13px;margin:0 0 16px;padding:12px 14px;background:var(--hover);border-radius:var(--r)}
    .kv dt{color:var(--muted)} .kv dd{margin:0;white-space:pre-line;overflow-wrap:anywhere;color:var(--ink-2)}
    .evlist{list-style:none;margin:0 0 16px;padding:0;display:flex;flex-direction:column;gap:8px}
    .evlist li{display:flex;gap:10px;align-items:center;border:1px solid var(--line);border-radius:var(--r);padding:8px 10px 8px 12px;transition:border-color .15s var(--ease)}
    .evlist li:hover{border-color:var(--line-strong)}
    .evlist .ficon{width:32px;height:32px;flex:none;border-radius:7px;display:grid;place-items:center;background:var(--sunken);color:var(--muted)}
    .evlist .ficon svg{width:16px;height:16px}
    .evlist .fmeta{flex:1;min-width:0;line-height:1.35}
    .evlist a{font-weight:500;overflow-wrap:anywhere;font-size:14px}
    .no-ev{font-size:13px;color:var(--muted);border:1px dashed var(--line-strong);border-radius:var(--r);padding:14px;text-align:center;margin:0 0 16px}

    /* Status workflow */
    .flow{grid-column:1/-1;display:flex;flex-direction:column;gap:14px;padding:16px 18px;border:1px solid var(--line);border-radius:var(--r-lg);background:var(--hover)}
    .flow-top{display:flex;flex-wrap:wrap;gap:12px 20px;align-items:center;justify-content:space-between}
    .steps{display:flex;align-items:center;gap:0;list-style:none;margin:0;padding:0;font-size:12.5px;font-weight:600;color:var(--muted)}
    .steps li{display:flex;align-items:center;gap:7px}
    .steps li+li::before{content:"";width:28px;height:2px;background:var(--line-strong);margin:0 8px}
    .steps .n{width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-size:11px;border:2px solid var(--line-strong);background:var(--surface)}
    .steps .past{color:var(--ink-2)} .steps .past .n{background:var(--good-bar);border-color:var(--good-bar);color:#fff}
    .steps .now{color:var(--ink)} .steps .now .n{border-color:var(--ink);background:var(--ink);color:var(--surface)}
    .steps li.past+li::before,.steps li.now::before{background:var(--good-bar)}
    .flow-msg{font-size:13.5px;color:var(--ink-2)}
    .flow-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start}
    .returned{display:flex;gap:10px;align-items:flex-start;background:var(--crit-bg);color:var(--crit);border-radius:var(--r);padding:12px 14px;font-size:13.5px}
    .returned svg{width:18px;height:18px;flex:none;margin-top:1px}
    .returned b{display:block;margin-bottom:2px}
    .returned p{color:var(--ink-2);white-space:pre-line}
    .submit-box,.review-box{display:flex;flex-direction:column;gap:12px;padding:14px;border:1px solid var(--line);border-radius:var(--r);background:var(--surface)}
    .review-grid{display:grid;grid-template-columns:auto minmax(0,1fr);gap:12px;align-items:start}
    .history{list-style:none;margin:0;padding:0;font-size:12.5px;color:var(--muted);display:flex;flex-direction:column;gap:4px}
    .history b{color:var(--ink-2);font-weight:600}
    .locked{font-size:13px;color:var(--muted);display:flex;gap:8px;align-items:center}
    .locked svg{width:15px;height:15px}

    /* Switch on the panel's own width, not the window's: the sidebar takes part of the window. */
    @container wbs (max-width:820px){
        .cols{display:none}
        .act>summary{grid-template-columns:40px minmax(0,1fr) auto;grid-template-areas:"no name st" "no dt dt" "no pr ev";row-gap:6px}
        .c-no{grid-area:no;align-self:start} .act .name{grid-area:name} .c-st{grid-area:st} .c-dt{grid-area:dt} .c-pr{grid-area:pr} .c-ev{grid-area:ev} .c-wt{display:none}
        .detail{grid-template-columns:minmax(0,1fr)}
    }
    @media (max-width:480px){ .phase-bar{grid-template-columns:118px minmax(0,1fr) 70px;gap:10px} .phase-bar>span:first-child{white-space:nowrap;overflow:hidden;text-overflow:ellipsis} }
@endsection

@section('content')
    @php
        $project = $deliverable->project;
        $today = today();
        $acts = $deliverable->activities;
        $plan = $deliverable->planPercent();
        $actual = $deliverable->actualPercent();
        $dev = $deliverable->deviation();
        $overdue = $acts->filter(fn ($a) => $a->schedule() === 'late')->count();
        $me = auth()->user();
        $canStart = $me->canStartAndSubmit();
        $canReview = $me->canReview();
        $total = $deliverable->totalWeight();
        $isAdmin = auth()->user()->isAdmin();
        $canUpdate = auth()->user()->canUpdateWbs();
        $canBaseline = auth()->user()->canEditBaseline();
        $pct = fn ($n) => number_format($n, 1).'%';
        $fmt = fn ($d) => $d ? $d->format('j M Y') : '–';
    @endphp

    <header class="page-head">
        <div class="titles">
            <nav class="crumbs" aria-label="Breadcrumb">
                <a href="{{ route('projects.index') }}">Projects</a>
                @include('partials.icon', ['name' => 'chevron'])
                <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
            </nav>
            <h1>{{ $deliverable->name }}</h1>
            <div class="meta">
                <span>Contract no. <b>{{ $project->contract_number ?: '–' }}</b></span>
                <span>Partner <b>{{ $project->partner?->name ?? '–' }}</b></span>
                <span>As of <b>{{ $fmt($today) }}</b></span>
            </div>
        </div>
        <span class="pill lg {{ $deliverable->status() }}">{{ $deliverable->statusLabel() }}</span>
    </header>

    <section class="stats" aria-label="Deliverable status">
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
            <span class="note">{{ $overdue }} overdue {{ $overdue === 1 ? 'step' : 'steps' }}</span>
        </div>
        <div class="stat">
            <span class="k">Steps with evidence</span>
            <span class="v num">{{ $deliverable->evidenceCoverage() }} <small>/ {{ $acts->count() }}</small></span>
            <div class="track"><div class="fill done" style="width:{{ $acts->count() ? round($deliverable->evidenceCoverage() / $acts->count() * 100) : 0 }}%"></div></div>
        </div>
    </section>

    <section class="panel pad">
        <div class="panel-head">
            <h2>Progress by phase</h2>
            <div class="legend">
                <span><i class="l-fill"></i>Actual</span>
                <span><i class="l-mark"></i>Plan to date</span>
            </div>
        </div>
        <div class="phases">
            @foreach ($deliverable->phases() as $phaseName => $items)
                @php
                    $pp = \App\Models\Deliverable::rollupPlan($items, $today);
                    $pa = \App\Models\Deliverable::rollupActual($items);
                @endphp
                <div class="phase-bar">
                    <span>{{ $loop->iteration }}. {{ $phaseName }}</span>
                    <div class="track" role="img" aria-label="Actual {{ round($pa) }}%, plan {{ round($pp) }}%">
                        <div class="fill {{ $pa >= 100 ? 'done' : ($pa < $pp ? 'late' : '') }}" style="width:{{ min(100, $pa) }}%"></div>
                        <div class="mark" style="left:calc({{ min(100, $pp) }}% - 1px)"></div>
                    </div>
                    <span class="num">{{ round($pa) }} / {{ round($pp) }}%</span>
                </div>
            @endforeach
        </div>
        @if (abs($total - 100) > 0.01)
            <p class="weight-warn" style="margin-top:16px">@include('partials.icon', ['name' => 'alert']) <span>Total weight is {{ $total }}. It should be 100; percentages are calculated proportionally.</span></p>
        @endif
    </section>

    <div class="section-head">
        <h2>Work Breakdown Structure</h2>
        <p>Steps go Planned → In Progress → In Review → Done. {{ $canStart ? 'You start each step and submit it with its evidence.' : ($canReview ? 'You review submitted steps.' : 'Your Project Manager starts and submits steps.') }}</p>
    </div>

    @foreach ($deliverable->phases() as $phaseName => $items)
        @php $phaseNo = $loop->iteration; @endphp
        <section class="panel wbs">
            <div class="phase-head">
                <div class="ph-title"><span class="phase-no">{{ $phaseNo }}</span><h2>{{ $phaseName }}</h2></div>
                <span class="tag">Weight {{ $items->sum('weight') }}</span>
            </div>
            <div class="cols" aria-hidden="true">
                <span>No</span><span>Activity</span><span>Weight</span><span>Plan</span><span>Progress</span><span>Status</span><span>Evidence</span>
            </div>

            @foreach ($items as $a)
                @php
                    $sch = $a->schedule();
                    $bagS = $errors->getBag('status'.$a->id);
                    $returned = $a->returnedReview();
                    $bagA = $errors->getBag('activity'.$a->id);
                    $bagE = $errors->getBag('evidence'.$a->id);
                    $bagP = $errors->getBag('plan'.$a->id);
                    $hasErr = $bagA->any() || $bagE->any() || $bagP->any() || $bagS->any();
                @endphp
                <details class="act" id="act-{{ $a->id }}" @if ($hasErr) open @endif>
                    <summary>
                        <span class="num muted small c-no">{{ $phaseNo }}.{{ $loop->iteration }}</span>
                        <span class="name">{{ $a->name }}@unless ($a->is_mandatory) <span class="tag" style="margin-left:6px">Optional</span>@endunless</span>
                        <span class="num small c-wt">{{ $a->weight }}</span>
                        <span class="small muted c-dt">{{ $fmt($a->plan_start) }} – {{ $fmt($a->plan_finish) }}</span>
                        <span class="prog c-pr">
                            <span class="track"><span class="fill {{ $a->isDone() ? 'done' : ($sch === 'late' ? 'late' : '') }}" style="width:{{ $a->progress }}%"></span></span>
                            <span class="num small">{{ $a->progress }}%</span>
                        </span>
                        <span class="c-st">
                            <span class="st {{ $a->status }}">{{ $a->statusLabel() }}</span>
                            @if ($returned)
                                <span class="flag late">Returned</span>
                            @elseif ($sch === 'late')
                                <span class="flag late">Overdue</span>
                            @elseif ($sch === 'behind' || $a->shouldHaveStarted())
                                <span class="flag behind">{{ $a->shouldHaveStarted() ? 'Not started' : 'Behind plan' }}</span>
                            @endif
                        </span>
                        <span class="num small c-ev ev-count {{ $a->evidences->isEmpty() && ($a->isInReview() || $a->isDone()) ? 'c-crit' : ($a->evidences->isEmpty() ? 'muted' : '') }}">@include('partials.icon', ['name' => 'file']){{ $a->evidences->count() }}</span>
                    </summary>

                    <div class="detail">
                        @php
                            $order = array_keys(\App\Models\Activity::STATUSES);
                            $pos = array_search($a->status, $order, true);
                            $latest = $a->latestReview();
                        @endphp
                        <div class="flow" aria-label="Status of this step">
                            <div class="flow-top">
                                <ol class="steps">
                                    @foreach (\App\Models\Activity::STATUSES as $key => $label)
                                        @php $i = $loop->index; @endphp
                                        <li class="{{ $i < $pos || $a->isDone() ? 'past' : ($i === $pos ? 'now' : '') }}" @if ($i === $pos) aria-current="step" @endif>
                                            <span class="n">{{ $i < $pos || $a->isDone() ? '✓' : $i + 1 }}</span>{{ $label }}
                                        </li>
                                    @endforeach
                                </ol>
                                <span class="small muted">Actual {{ $fmt($a->actual_start) }} – {{ $a->actual_finish ? $fmt($a->actual_finish) : '…' }}</span>
                            </div>

                            @if ($returned)
                                <div class="returned" role="alert">
                                    @include('partials.icon', ['name' => 'undo'])
                                    <div><b>Returned for revision by {{ $returned->reviewer?->name ?? 'a TIF admin' }}, {{ $returned->reviewed_at->format('j M Y H:i') }}</b>
                                        <p>{{ $returned->reason }}</p></div>
                                </div>
                            @endif

                            @if ($a->isPlanned())
                                <p class="flow-msg">
                                    Planned for {{ $fmt($a->plan_start) }} – {{ $fmt($a->plan_finish) }}.
                                    @if ($a->shouldHaveStarted()) <b class="c-warn">The plan start has passed.</b> @endif
                                </p>
                                @if ($canStart)
                                    <form method="POST" action="{{ route('activities.start', $a) }}" class="flow-actions">
                                        @csrf
                                        <button class="btn primary" type="submit">@include('partials.icon', ['name' => 'play']) Start step</button>
                                        <span class="hint" style="align-self:center">Sets the actual start to today and moves the step to In Progress.</span>
                                    </form>
                                @else
                                    <p class="locked">@include('partials.icon', ['name' => 'lock']) Waiting for the partner's Project Manager to start this step.</p>
                                @endif

                            @elseif ($a->isInProgress())
                                @if ($canStart)
                                    <form method="POST" action="{{ route('activities.submit', $a) }}" enctype="multipart/form-data" class="submit-box">
                                        @csrf
                                        <h3 style="margin:0">Finished? Submit for review</h3>
                                        <p class="hint" style="margin-top:-6px">
                                            Evidence is required: <b>{{ $a->evidence_required ?: 'the step\'s document' }}</b>.
                                            {{ $a->evidences->isEmpty() ? 'No file is attached yet, so add it here.' : $a->evidences->count().' file(s) already attached; you can add more.' }}
                                        </p>
                                        <label>Evidence files
                                            <input id="submit-files-{{ $a->id }}" type="file" name="files[]" multiple @if ($a->evidences->isEmpty()) required @endif
                                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
                                        </label>
                                        <label><span>Note for the reviewer <span class="hint">· optional</span></span>
                                            <textarea id="submit-note-{{ $a->id }}" name="submission_note" maxlength="2000" placeholder="{{ $returned ? 'What did you change?' : 'Anything the reviewer should know' }}">{{ $bagS->any() ? old('submission_note') : '' }}</textarea>
                                        </label>
                                        <div class="flow-actions">
                                            <button class="btn primary" type="submit">@include('partials.icon', ['name' => 'send']) Submit for review</button>
                                            <span class="hint" style="align-self:center">Sets progress to 100% and the actual finish to today. The TIF admins are notified.</span>
                                        </div>
                                    </form>
                                @else
                                    <p class="flow-msg">In progress. The partner's Project Manager submits it for review when it is finished.</p>
                                @endif

                            @elseif ($a->isInReview())
                                <p class="flow-msg">
                                    Submitted by <b>{{ $latest?->submitter?->name ?? 'the Project Manager' }}</b>{{ $latest ? ', '.$latest->submitted_at->format('j M Y H:i') : '' }}.
                                    {{ $canReview ? 'Check the evidence, then accept it or return it.' : 'Waiting for a TIF admin to review it.' }}
                                </p>
                                @if ($latest?->submission_note)
                                    <p class="flow-msg" style="white-space:pre-line;border-left:3px solid var(--line-strong);padding-left:10px">{{ $latest->submission_note }}</p>
                                @endif
                                @if ($canReview)
                                    <div class="review-box">
                                        <div class="review-grid">
                                            <form method="POST" action="{{ route('activities.accept', $a) }}">
                                                @csrf
                                                <button class="btn primary" type="submit">@include('partials.icon', ['name' => 'check']) Accept · mark Done</button>
                                            </form>
                                            <form method="POST" action="{{ route('activities.reject', $a) }}" class="stack-sm">
                                                @csrf
                                                <label>Or return it for revision: reason
                                                    <textarea id="reason-{{ $a->id }}" name="reason" required minlength="5" maxlength="2000" placeholder="What needs to change in the document?">{{ $bagS->any() ? old('reason') : '' }}</textarea>
                                                </label>
                                                <div><button class="btn danger" type="submit">@include('partials.icon', ['name' => 'undo']) Return to In Progress</button></div>
                                            </form>
                                        </div>
                                    </div>
                                @endif

                            @else
                                <p class="flow-msg">
                                    Accepted by <b>{{ $latest?->reviewer?->name ?? 'a TIF admin' }}</b>{{ $latest?->reviewed_at ? ', '.$latest->reviewed_at->format('j M Y H:i') : '' }}. This step is done.
                                </p>
                                @if ($canReview)
                                    <details>
                                        <summary class="linkbtn" style="display:inline-block;cursor:pointer">Reopen this step</summary>
                                        <form method="POST" action="{{ route('activities.reject', $a) }}" class="stack-sm" style="margin-top:10px">
                                            @csrf
                                            <label>Reason
                                                <textarea id="reopen-{{ $a->id }}" name="reason" required minlength="5" maxlength="2000" placeholder="Why does this step need more work?">{{ $bagS->any() ? old('reason') : '' }}</textarea>
                                            </label>
                                            <div><button class="btn danger" type="submit">@include('partials.icon', ['name' => 'undo']) Reopen to In Progress</button></div>
                                        </form>
                                    </details>
                                @endif
                            @endif

                            @foreach ($bagS->all() as $message)
                                <div class="err-text" role="alert">{{ $message }}</div>
                            @endforeach

                            @if ($a->reviews->isNotEmpty())
                                <details>
                                    <summary class="small muted" style="cursor:pointer">Review history ({{ $a->reviews->count() }})</summary>
                                    <ul class="history" style="margin-top:8px">
                                        @foreach ($a->reviews as $r)
                                            <li>
                                                <b>{{ $r->submitted_at->format('j M Y H:i') }}</b> submitted by {{ $r->submitter?->name ?? '–' }}
                                                @if ($r->decision === 'accepted') · <span class="c-good">accepted</span> by {{ $r->reviewer?->name ?? '–' }}, {{ $r->reviewed_at?->format('j M Y H:i') }}
                                                @elseif ($r->decision === 'rejected') · <span class="c-crit">returned</span> by {{ $r->reviewer?->name ?? '–' }}, {{ $r->reviewed_at?->format('j M Y H:i') }}: “{{ $r->reason }}”
                                                @else · waiting for review @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>

                        <div>
                            <h3>Progress</h3>
                            <dl class="kv">
                                <dt>Circulation</dt><dd>{{ $a->circulation ?: '–' }}</dd>
                                <dt>Required evidence</dt><dd>{{ $a->evidence_required ?: '–' }}</dd>
                                @if ($a->updater)
                                    <dt>Last update</dt><dd>{{ $a->updater->name }} ({{ $a->updater->roleLabel() }}), {{ $a->updated_at->format('j M Y H:i') }}</dd>
                                @endif
                            </dl>
                            @if ($canUpdate && $a->isEditable())
                            <form method="POST" action="{{ route('activities.update', $a) }}" class="form">
                                @csrf @method('PUT')
                                <label class="span">Progress (%)
                                    <input id="progress-{{ $a->id }}" type="number" name="progress" min="0" max="99" step="1" required
                                           value="{{ $bagA->any() ? old('progress', $a->progress) : min(99, $a->progress) }}">
                                    <span class="hint">0–99%. The step reaches 100% when it is submitted for review.</span>
                                </label>
                                <label class="span">Notes / issues
                                    <textarea id="note-{{ $a->id }}" name="note" maxlength="2000">{{ $bagA->any() ? old('note') : $a->note }}</textarea>
                                </label>
                                @foreach ($bagA->all() as $message)
                                    <div class="err-text span" role="alert">{{ $message }}</div>
                                @endforeach
                                <div class="span"><button class="btn" type="submit">Save progress</button></div>
                            </form>
                            @else
                                <p class="locked">@include('partials.icon', ['name' => 'lock'])
                                    {{ $a->isPlanned() ? 'Progress can be updated once the step is started.' : ($a->isInReview() ? 'Locked while the step is in review.' : ($a->isDone() ? 'The step is done.' : 'Your role can view this step only.')) }}
                                </p>
                                @if ($a->note) <p class="small" style="margin-top:10px;white-space:pre-line"><span class="muted">Notes:</span> {{ $a->note }}</p> @endif
                                @foreach ($bagA->all() as $message)
                                    <div class="err-text" role="alert">{{ $message }}</div>
                                @endforeach
                            @endif
                        </div>

                        <div>
                            <h3>Evidence ({{ $a->evidences->count() }})</h3>
                            @if ($a->evidences->isEmpty())
                                <p class="no-ev">No evidence uploaded for this activity yet.</p>
                            @else
                                <ul class="evlist">
                                    @foreach ($a->evidences as $ev)
                                        <li>
                                            <span class="ficon">@include('partials.icon', ['name' => 'file'])</span>
                                            <span class="fmeta">
                                                <a href="{{ route('evidences.download', $ev) }}">{{ $ev->original_name }}</a>
                                                <span class="small muted" style="display:block">@if ($ev->onDrive())<span class="tag" title="Stored in Google Drive">Drive</span> @endif{{ $ev->sizeLabel() }} · {{ $ev->uploader?->name ?? 'Deleted user' }}, {{ $ev->created_at->format('j M Y') }}</span>
                                            </span>
                                            @if ($canUpdate && $a->isEditable())
                                            <form method="POST" action="{{ route('evidences.destroy', $ev) }}"
                                                  onsubmit="return confirm('Delete this evidence file?')">
                                                @csrf @method('DELETE')
                                                <button class="btn sm danger" type="submit" aria-label="Delete {{ $ev->original_name }}">@include('partials.icon', ['name' => 'trash'])</button>
                                            </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($canUpdate && $a->isEditable())
                            <form method="POST" action="{{ route('evidences.store', $a) }}" enctype="multipart/form-data" class="stack-sm">
                                @csrf
                                <label>Upload evidence files
                                    <input id="files-{{ $a->id }}" type="file" name="files[]" multiple required
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip">
                                    <span class="hint">PDF, Word, Excel, PowerPoint, JPG, PNG, ZIP. Up to 20 MB per file.</span>
                                </label>
                                @foreach ($bagE->all() as $message)
                                    <div class="err-text" role="alert">{{ $message }}</div>
                                @endforeach
                                <div><button class="btn" type="submit">@include('partials.icon', ['name' => 'upload']) Upload</button></div>
                            </form>
                            @else
                                @foreach ($bagE->all() as $message)
                                    <div class="err-text" role="alert">{{ $message }}</div>
                                @endforeach
                            @endif
                        </div>

                        @if ($canBaseline)
                            <div class="full">
                                <h3>Baseline <span class="tag" style="margin-left:6px">TIF admin only</span></h3>
                                <form method="POST" action="{{ route('admin.activities.plan', $a) }}" class="form three">
                                    @csrf @method('PUT')
                                    <label>Weight
                                        <input id="weight-{{ $a->id }}" type="number" name="weight" min="0" max="100" step="0.01" required value="{{ $a->weight }}">
                                    </label>
                                    <label>Plan start
                                        <input id="plan-start-{{ $a->id }}" type="date" name="plan_start" required value="{{ $a->plan_start->toDateString() }}">
                                    </label>
                                    <label>Plan finish
                                        <input id="plan-finish-{{ $a->id }}" type="date" name="plan_finish" required value="{{ $a->plan_finish->toDateString() }}">
                                    </label>
                                    @foreach ($bagP->all() as $message)
                                        <div class="err-text span" role="alert">{{ $message }}</div>
                                    @endforeach
                                    <div class="span"><button class="btn" type="submit">Save baseline</button></div>
                                </form>
                            </div>
                        @endif
                    </div>
                </details>
            @endforeach
        </section>
    @endforeach
@endsection

@section('js')
    <script>
        // Re-open the activity that was just saved (URL ends with #act-ID).
        (function () {
            var el = location.hash && document.getElementById(location.hash.slice(1));
            if (el && el.tagName === 'DETAILS') { el.open = true; el.scrollIntoView({block: 'center'}); }
        })();
    </script>
@endsection
