@extends('layouts.app')

@section('title', 'New project · TIF PMO')

@section('css')
    .modes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .modes label{flex-direction:row;align-items:flex-start;gap:12px;border:1px solid var(--line-strong);border-radius:var(--r-lg);padding:16px;cursor:pointer;color:var(--ink);font-size:14px;font-weight:400;transition:border-color .15s var(--ease),background .15s var(--ease)}
    .modes label:hover{border-color:var(--muted)}
    .modes label:has(input:checked){border-color:var(--accent);background:var(--accent-soft);box-shadow:0 0 0 1px var(--accent)}
    .modes label b{display:block;font-weight:600;margin-bottom:2px}

    /* Deliverable tabs */
    .tabs{display:flex;align-items:flex-end;gap:4px;border-bottom:1px solid var(--line);overflow-x:auto;scrollbar-width:thin}
    .tab{display:flex;align-items:center;gap:8px;max-width:260px;padding:10px 14px;border:1px solid transparent;border-bottom:0;border-radius:var(--r) var(--r) 0 0;
        background:none;color:var(--muted);font-weight:500;font-size:14px;cursor:pointer;white-space:nowrap;position:relative;top:1px}
    .tab:hover{color:var(--ink);background:var(--hover)}
    .tab[aria-selected=true]{color:var(--ink);background:var(--surface);border-color:var(--line);box-shadow:inset 0 2px 0 var(--brand)}
    .tab .t-name{overflow:hidden;text-overflow:ellipsis}
    .tab .t-w{font-family:var(--mono);font-size:11.5px;padding:1px 6px;border-radius:5px;background:var(--sunken);color:var(--muted)}
    .tab .t-w.bad{background:var(--warn-bg);color:var(--warn)}
    .tab.has-error{color:var(--crit)}
    .tab-add{color:var(--accent)}
    .tab-add svg{width:15px;height:15px}
    .tabpanel{padding-top:18px;display:flex;flex-direction:column;gap:14px}
    .tabpanel[hidden]{display:none}
    .dhead{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
    .dhead label{flex:1 1 280px}
    .tools-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:12px 14px;background:var(--hover);border-radius:var(--r)}
    .tools-row label{flex:0 1 200px}
    .tools-row .grow{flex:1}

    .plan{border:1px solid var(--line);border-radius:var(--r-lg)}
    .plan td{padding:8px 12px}
    .plan td input{min-width:132px;min-height:36px;padding:6px 9px;font-size:14px}
    .plan td.w input{min-width:76px;max-width:92px}
    .plan td input[type=checkbox]{min-width:0;min-height:0;width:16px;height:16px;padding:0;margin:0}
    .plan .phase td{background:var(--sunken);font-weight:600;padding:9px 12px}
    .plan tbody tr.phase:hover{background:var(--sunken)}
    .plan tr.excluded td:not(.inc){opacity:.42}
    .plan tr.excluded input[type=number],.plan tr.excluded input[type=date]{background:var(--sunken)}
    .plan td.inc{width:118px}
    .inc label{flex-direction:row;align-items:center;gap:7px;font-size:12.5px;cursor:pointer;color:var(--ink-2)}
    .req{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--muted)}
    .req svg{width:14px;height:14px;color:var(--good)}

    .submit-bar{position:sticky;bottom:0;z-index:5;display:flex;gap:10px;justify-content:flex-end;padding:14px 0;background:linear-gradient(transparent,var(--bg) 30%)}
    @media (max-width:640px){.modes{grid-template-columns:minmax(0,1fr)}}
@endsection

@section('content')
    @php
        $deliverables = old('deliverables', [0 => '']);
        $mandatoryCount = $template->where('is_mandatory', true)->count();
        $optionalCount = $template->count() - $mandatoryCount;
    @endphp

    <header class="page-head">
        <div class="titles">
            <nav class="crumbs" aria-label="Breadcrumb">
                <a href="{{ route('projects.index') }}">Projects</a>
                @include('partials.icon', ['name' => 'chevron'])
                <span>New</span>
            </nav>
            <h1>New project</h1>
            <p class="sub">Set the contract details, then the deliverables. Each deliverable gets its own WBS tab.</p>
        </div>
    </header>

    @if ($partners->isEmpty())
        <div class="notice err">@include('partials.icon', ['name' => 'alert']) <span>There is no implementation partner yet. <a href="{{ route('admin.partners.index') }}">Add a partner</a> first.</span></div>
    @endif

    @if ($errors->any())
        <div class="notice err" role="alert">
            @include('partials.icon', ['name' => 'alert'])
            <ul>
                @foreach (array_slice(array_unique($errors->all()), 0, 12) as $message)
                    <li>{{ $message }}</li>
                @endforeach
                @if (count(array_unique($errors->all())) > 12) <li>…and {{ count(array_unique($errors->all())) - 12 }} more.</li> @endif
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.projects.store') }}" enctype="multipart/form-data" class="stack" id="project-form">
        @csrf

        <section class="panel pad">
            <div class="panel-head"><h2>Project details</h2></div>
            <div class="form">
                <label>Project name
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255">
                </label>
                <label>Contract number
                    <input id="contract_number" type="text" name="contract_number" value="{{ old('contract_number') }}" maxlength="255">
                </label>
                <label>Contract value (Rp, digits only)
                    <input id="contract_value" type="number" name="contract_value" value="{{ old('contract_value') }}" min="0" step="1" placeholder="1250000000">
                </label>
                <label>Implementation partner
                    <select id="partner_id" name="partner_id" required>
                        <option value="">Choose a partner</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" @selected(old('partner_id') == $partner->id)>
                                {{ $partner->name }} ({{ $partner->users_count }} {{ $partner->users_count === 1 ? 'account' : 'accounts' }})
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <section class="panel pad stack" style="gap:18px">
            <div class="panel-head" style="margin:0">
                <h2>Deliverables &amp; WBS</h2>
                <p>
                    {{ $template->count() }} standard steps: {{ $mandatoryCount }} mandatory, {{ $optionalCount }} optional.
                    <a href="{{ route('admin.wbs-items.index') }}">Configure WBS items</a>
                </p>
            </div>

            <div class="modes" role="radiogroup" aria-label="How to set the plan">
                <label>
                    <input id="mode-manual-radio" type="radio" name="plan_mode" value="manual" @checked(old('plan_mode', 'manual') === 'manual')>
                    <span><b>Plan each deliverable here</b><span class="small muted">Set dates and weights per deliverable tab, and choose which optional steps apply.</span></span>
                </label>
                <label>
                    <input id="mode-excel-radio" type="radio" name="plan_mode" value="excel" @checked(old('plan_mode') === 'excel')>
                    <span><b>Upload a WBS file (Excel)</b><span class="small muted">One file's steps, weights and dates are used for every deliverable.</span></span>
                </label>
            </div>

            <fieldset id="mode-excel">
                <label>WBS file (.xlsx / .xls, up to 5 MB)
                    <input id="wbs_file" type="file" name="wbs_file" accept=".xlsx,.xls">
                </label>
                <p class="hint" style="margin-top:10px">
                    The first sheet needs a header row
                    <span class="num">No | Nama Activity | Bobot | Sirkulir | Tg. Plan Start | Tg. Plan Finish | Evidence</span>.
                    Rows numbered 1, 2, 3 are phases; rows numbered 1.1, 1.2 are steps. Plan Start and Plan Finish must be date cells.
                </p>
            </fieldset>

            <div>
                <div class="tabs" role="tablist" aria-label="Deliverables" id="tabs">
                    @foreach ($deliverables as $d => $dname)
                        <button type="button" class="tab" role="tab" id="tab-{{ $d }}" aria-controls="panel-{{ $d }}" data-key="{{ $d }}">
                            <span class="t-name">{{ $dname ?: 'Deliverable '.($loop->iteration) }}</span><span class="t-w"></span>
                        </button>
                    @endforeach
                    <button type="button" class="tab tab-add" id="add-deliverable">@include('partials.icon', ['name' => 'plus']) Add deliverable</button>
                </div>

                <div id="panels">
                    @foreach ($deliverables as $d => $dname)
                        @include('admin.projects.partials.deliverable-tab', ['d' => $d, 'dname' => $dname, 'template' => $template, 'useOld' => true])
                    @endforeach
                </div>
            </div>
        </section>

        <div class="submit-bar">
            <a class="btn" href="{{ route('projects.index') }}">Cancel</a>
            <button class="btn primary" type="submit">Create project</button>
        </div>
    </form>

    <template id="tab-template">
        @include('admin.projects.partials.deliverable-tab', ['d' => '__D__', 'dname' => '', 'template' => $template, 'useOld' => false])
    </template>
@endsection

@section('js')
    <script>
        (function () {
            var form = document.getElementById('project-form'), tabs = document.getElementById('tabs'),
                panels = document.getElementById('panels'), addBtn = document.getElementById('add-deliverable'),
                tpl = document.getElementById('tab-template'), counter = 0;

            panels.querySelectorAll('.tabpanel').forEach(function (p) { var k = parseInt(p.dataset.key, 10); if (!isNaN(k)) counter = Math.max(counter, k + 1); });

            function tabFor(key) { return document.getElementById('tab-' + key); }
            function panelFor(key) { return document.getElementById('panel-' + key); }
            function allPanels() { return panels.querySelectorAll('.tabpanel'); }

            function select(key) {
                allPanels().forEach(function (p) {
                    var on = p.dataset.key === String(key);
                    p.hidden = !on;
                    tabFor(p.dataset.key).setAttribute('aria-selected', on);
                    tabFor(p.dataset.key).tabIndex = on ? 0 : -1;
                });
            }

            // Optional steps: unticked rows are greyed out and not submitted.
            function syncRow(row) {
                var box = row.querySelector('[data-include]');
                if (!box) return;
                var on = box.checked;
                row.classList.toggle('excluded', !on);
                row.querySelectorAll('input[type=number],input[type=date]').forEach(function (i) { i.disabled = !on; });
            }

            function refresh() {
                var panelsList = allPanels(), manual = document.querySelector('input[name="plan_mode"]:checked').value === 'manual';
                panelsList.forEach(function (p, idx) {
                    var total = 0;
                    p.querySelectorAll('tr[data-item]').forEach(function (row) {
                        if (!row.classList.contains('excluded')) total += parseFloat(row.querySelector('[data-weight]').value) || 0;
                    });
                    total = Math.round(total * 100) / 100;
                    var tab = tabFor(p.dataset.key), name = p.querySelector('[data-dname]').value.trim();
                    tab.querySelector('.t-name').textContent = name || ('Deliverable ' + (idx + 1));
                    var w = tab.querySelector('.t-w');
                    w.textContent = manual ? total + '%' : '';
                    w.hidden = !manual;
                    w.classList.toggle('bad', Math.abs(total - 100) > 0.01);
                    p.querySelector('[data-total]').textContent = total;
                    p.querySelector('[data-remove]').disabled = panelsList.length === 1;
                    var copy = p.querySelector('[data-copy-first]');
                    if (copy) copy.hidden = idx === 0;
                });
            }

            function copyValues(from, to) {
                var src = {};
                from.querySelectorAll('tr[data-item]').forEach(function (r) { src[r.dataset.item] = r; });
                to.querySelectorAll('tr[data-item]').forEach(function (r) {
                    var s = src[r.dataset.item]; if (!s) return;
                    ['[data-weight]', '[data-start]', '[data-finish]'].forEach(function (sel) { r.querySelector(sel).value = s.querySelector(sel).value; });
                    var a = r.querySelector('[data-include]'), b = s.querySelector('[data-include]');
                    if (a && b) a.checked = b.checked;
                    syncRow(r);
                });
            }

            addBtn.addEventListener('click', function () {
                var key = String(counter++), first = allPanels()[0];
                tabs.insertBefore(Object.assign(document.createElement('button'), {
                    type: 'button', className: 'tab', id: 'tab-' + key,
                    innerHTML: '<span class="t-name"></span><span class="t-w"></span>'
                }), addBtn);
                var tab = tabFor(key);
                tab.setAttribute('role', 'tab'); tab.setAttribute('aria-controls', 'panel-' + key); tab.dataset.key = key;

                panels.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__D__/g, key));
                var panel = panelFor(key);
                if (first) copyValues(first, panel);   // start from the first deliverable's plan
                panel.querySelectorAll('tr[data-item]').forEach(syncRow);
                select(key); refresh();
                panel.querySelector('[data-dname]').focus();
            });

            tabs.addEventListener('click', function (e) {
                var tab = e.target.closest('.tab[data-key]'); if (tab) select(tab.dataset.key);
            });
            tabs.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                var list = Array.from(tabs.querySelectorAll('.tab[data-key]')), i = list.indexOf(document.activeElement);
                if (i < 0) return;
                var next = list[(i + (e.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length];
                select(next.dataset.key); next.focus();
            });

            panels.addEventListener('click', function (e) {
                var btn = e.target.closest('button'); if (!btn) return;
                var panel = btn.closest('.tabpanel');
                if (btn.hasAttribute('data-remove') && allPanels().length > 1) {
                    var key = panel.dataset.key, list = Array.from(allPanels()), idx = list.indexOf(panel);
                    tabFor(key).remove(); panel.remove();
                    select(allPanels()[Math.max(0, idx - 1)].dataset.key); refresh();
                }
                if (btn.hasAttribute('data-copy-first')) { copyValues(allPanels()[0], panel); refresh(); }
                if (btn.hasAttribute('data-autofill')) {
                    var startInput = panel.querySelector('[data-autofill-start]'), start = startInput.value;
                    if (!start) { startInput.focus(); return; }
                    var p = start.split('-').map(Number), base = Date.UTC(p[0], p[1] - 1, p[2]);
                    panel.querySelectorAll('input[data-offset]').forEach(function (input) {
                        input.value = new Date(base + Number(input.dataset.offset) * 86400000).toISOString().slice(0, 10);
                    });
                }
            });
            panels.addEventListener('change', function (e) {
                if (e.target.matches('[data-include]')) syncRow(e.target.closest('tr'));
                refresh();
            });
            panels.addEventListener('input', refresh);

            // A required field inside a hidden tab: open that tab so the browser can show the message.
            form.addEventListener('invalid', function (e) {
                var panel = e.target.closest('.tabpanel');
                if (panel && panel.hidden) select(panel.dataset.key);
            }, true);

            // Plan mode: show only the fields of the chosen mode; hidden fields are not submitted.
            var excel = document.getElementById('mode-excel');
            function syncMode() {
                var manual = document.querySelector('input[name="plan_mode"]:checked').value === 'manual';
                excel.hidden = excel.disabled = manual;
                allPanels().forEach(function (p) { var f = p.querySelector('.wbs-plan'); f.hidden = f.disabled = !manual; });
                document.getElementById('wbs_file').required = !manual;
                refresh();
            }
            document.querySelectorAll('input[name="plan_mode"]').forEach(function (r) { r.addEventListener('change', syncMode); });

            panels.querySelectorAll('tr[data-item]').forEach(syncRow);
            var errTab = panels.querySelector('.tabpanel[data-has-error]');
            select((errTab || allPanels()[0]).dataset.key);
            if (errTab) tabFor(errTab.dataset.key).classList.add('has-error');
            syncMode();
        })();
    </script>
@endsection
