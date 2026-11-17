@extends('layouts.app')

@section('title', 'WBS items · TIF PMO')

@section('css')
    .wbs-edit{display:flex;flex-direction:column}
    .wi{display:grid;grid-template-columns:30px 150px minmax(0,1fr) 84px 150px 168px 64px;gap:10px;align-items:start;padding:12px 18px;border-bottom:1px solid var(--line)}
    .wi-head{font-size:12px;color:var(--muted);background:var(--hover);padding-top:10px;padding-bottom:10px;border-radius:var(--r-lg) var(--r-lg) 0 0;align-items:center}
    .wi:last-child{border-bottom:0}
    .wi input,.wi textarea{min-height:36px;padding:6px 9px;font-size:14px}
    .wi textarea{min-height:58px;font-size:13px}
    .wi .no{font-family:var(--mono);font-size:12.5px;color:var(--muted);padding-top:9px}
    .wi .days{display:grid;grid-template-columns:1fr auto 1fr;gap:6px;align-items:center;font-size:12px;color:var(--muted)}
    .wi .more{grid-column:3/-1}
    .wi .more summary{font-size:13px;color:var(--accent);cursor:pointer;width:max-content}
    .wi .more-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px}
    .wi .tools{display:flex;gap:2px;justify-content:flex-end;padding-top:3px}
    .wi .tools button{width:30px;height:30px;border:0;border-radius:6px;background:none;color:var(--muted);cursor:pointer;display:grid;place-items:center}
    .wi .tools button:hover{background:var(--sunken);color:var(--ink)}
    .wi .tools button.del:hover{background:var(--crit-bg);color:var(--crit)}
    .wi .tools button:disabled{opacity:.3;cursor:default;background:none}
    .wi .tools svg{width:16px;height:16px}
    .wi.optional{background:color-mix(in srgb,var(--sunken) 55%,transparent)}

    .switch{display:flex;align-items:center;gap:9px;cursor:pointer;padding-top:6px;font-size:13px;font-weight:500;color:var(--ink-2);flex-direction:row}
    .switch input{position:absolute;opacity:0;width:1px;height:1px}
    .switch .track-sw{width:34px;height:20px;border-radius:999px;background:var(--line-strong);position:relative;transition:background .15s var(--ease);flex:none}
    .switch .track-sw::after{content:"";position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px #0003;transition:transform .15s var(--ease)}
    .switch input:checked+.track-sw{background:var(--accent)}
    .switch input:checked+.track-sw::after{transform:translateX(14px)}
    .switch input:focus-visible+.track-sw{outline:2px solid var(--accent);outline-offset:2px}

    .wbs-foot{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--line);background:var(--hover);border-radius:0 0 var(--r-lg) var(--r-lg)}
    .total-ok{color:var(--good)} .total-bad{color:var(--warn)}

    @container wbsedit (max-width:900px){
        .wi-head{display:none}
        .wi{grid-template-columns:30px minmax(0,1fr) 84px;grid-template-areas:"no phase phase" "no name name" "no weight weight" "no mand mand" "no days days" "no more more" "no tools tools"}
        .wi .no{grid-area:no} .wi .c-phase{grid-area:phase} .wi .c-name{grid-area:name} .wi .c-weight{grid-area:weight}
        .wi .c-mand{grid-area:mand} .wi .c-days{grid-area:days} .wi .more{grid-area:more} .wi .tools{grid-area:tools;justify-content:flex-start}
        .wi .more-grid{grid-template-columns:1fr}
    }
    .wbs-edit-wrap{container:wbsedit/inline-size}
@endsection

@section('content')
    @php
        $rows = old('items')
            ? array_values(old('items'))
            : $items->map(fn ($i) => $i->only(['id', 'phase', 'name', 'weight', 'is_mandatory', 'offset_start', 'offset_finish', 'circulation', 'evidence_required']))->all();
        $phases = $items->pluck('phase')->unique()->values();
    @endphp

    <header class="page-head">
        <div class="titles">
            <h1>WBS items</h1>
            <p class="sub">The standard steps every new deliverable starts from. <b>Mandatory</b> steps are always included. <b>Optional</b> steps can be left out per deliverable when you create a project.</p>
        </div>
    </header>

    @if ($errors->any())
        <div class="notice err" role="alert">
            @include('partials.icon', ['name' => 'alert'])
            <ul>@foreach (array_unique($errors->all()) as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.wbs-items.update') }}" id="wbs-form" class="wbs-edit-wrap">
        @csrf @method('PUT')
        <datalist id="phase-list">@foreach ($phases as $phase)<option value="{{ $phase }}">@endforeach</datalist>

        <section class="panel wbs-edit">
            <div class="wi wi-head" aria-hidden="true">
                <span>No</span><span>Phase</span><span>Step</span><span>Weight</span><span>Required</span><span>Plan (days after start)</span><span></span>
            </div>

            <div id="wbs-rows">
                @foreach ($rows as $i => $row)
                    @include('admin.partials.wbs-item-row', ['i' => $i, 'row' => $row])
                @endforeach
            </div>

            <div class="wbs-foot">
                <button class="btn" type="button" id="add-item">@include('partials.icon', ['name' => 'plus']) Add step</button>
                <span class="small"><span id="count-m">0</span> mandatory · <span id="count-o">0</span> optional · total weight <b class="num" id="total">0</b></span>
            </div>
        </section>

        <p class="note" style="margin-top:14px">Changes apply to projects created after saving. Existing deliverables keep the steps they were created with; change those on the deliverable's page.</p>

        <div class="submit-bar" style="position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;padding:14px 0;background:linear-gradient(transparent,var(--bg) 30%)">
            <a class="btn" href="{{ route('admin.wbs-items.index') }}">Discard changes</a>
            <button class="btn primary" type="submit">Save WBS items</button>
        </div>
    </form>

    <template id="row-template">
        @include('admin.partials.wbs-item-row', ['i' => '__I__', 'row' => ['id' => null, 'phase' => '', 'name' => '', 'weight' => 0, 'is_mandatory' => true, 'offset_start' => 0, 'offset_finish' => 0, 'circulation' => '', 'evidence_required' => '']])
    </template>
@endsection

@section('js')
    <script>
        (function () {
            var list = document.getElementById('wbs-rows'), tpl = document.getElementById('row-template'), counter = list.children.length;

            function refresh() {
                var rows = list.querySelectorAll('.wi'), total = 0, m = 0, o = 0;
                rows.forEach(function (row, idx) {
                    row.querySelector('.no').textContent = idx + 1;
                    row.querySelector('[data-up]').disabled = idx === 0;
                    row.querySelector('[data-down]').disabled = idx === rows.length - 1;
                    row.querySelector('[data-del]').disabled = rows.length === 1;
                    total += parseFloat(row.querySelector('[data-weight]').value) || 0;
                    var mandatory = row.querySelector('[data-mandatory]').checked;
                    mandatory ? m++ : o++;
                    row.classList.toggle('optional', !mandatory);
                    row.querySelector('[data-mlabel]').textContent = mandatory ? 'Mandatory' : 'Optional';
                });
                var t = document.getElementById('total');
                t.textContent = Math.round(total * 100) / 100;
                t.className = 'num ' + (Math.abs(total - 100) < 0.01 ? 'total-ok' : 'total-bad');
                document.getElementById('count-m').textContent = m;
                document.getElementById('count-o').textContent = o;
            }

            document.getElementById('add-item').addEventListener('click', function () {
                var html = tpl.innerHTML.replace(/__I__/g, 'n' + (counter++));
                list.insertAdjacentHTML('beforeend', html);
                var row = list.lastElementChild, last = list.children[list.children.length - 2];
                if (last) row.querySelector('[data-phase]').value = last.querySelector('[data-phase]').value;
                refresh();
                row.querySelector('[data-name]').focus();
            });

            list.addEventListener('click', function (e) {
                var btn = e.target.closest('button'); if (!btn) return;
                var row = btn.closest('.wi');
                if (btn.hasAttribute('data-up') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
                if (btn.hasAttribute('data-down') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
                if (btn.hasAttribute('data-del') && list.children.length > 1) row.remove();
                refresh();
            });
            list.addEventListener('input', refresh);
            list.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endsection
