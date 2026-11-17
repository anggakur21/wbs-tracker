{{--
    One deliverable tab on the New project form.
    $d = deliverable key, $dname = its name, $template = WBS items, $useOld = refill from the last submit.
--}}
@php
    $old = fn ($key, $default = null) => $useOld ? old($key, $default) : $default;
    $hasError = $useOld && collect($errors->keys())->contains(fn ($k) => str_starts_with($k, "plans.$d.") || $k === "plans.$d" || $k === "deliverables.$d");
    $submitted = $useOld && old('plans') !== null;
@endphp
<div class="tabpanel" role="tabpanel" id="panel-{{ $d }}" aria-labelledby="tab-{{ $d }}" data-key="{{ $d }}" @if ($hasError) data-has-error @endif>
    <div class="dhead">
        <label>Deliverable name
            <input data-dname id="deliverable-{{ $d }}" type="text" name="deliverables[{{ $d }}]" value="{{ $dname }}" required maxlength="255" placeholder="e.g. Billing module">
        </label>
        <button class="btn danger" type="button" data-remove>@include('partials.icon', ['name' => 'trash']) Remove deliverable</button>
    </div>

    <fieldset class="wbs-plan stack" style="gap:14px">
        <div class="tools-row">
            <label>Start date
                <input type="date" data-autofill-start aria-label="Start date for this deliverable">
            </label>
            <button class="btn" type="button" data-autofill>Fill dates from start</button>
            <button class="btn" type="button" data-copy-first>Copy plan from first deliverable</button>
            <span class="grow"></span>
            <span class="small">Total weight <b class="num" data-total>0</b></span>
        </div>

        <div class="tablewrap">
            <table class="plan">
                <thead>
                <tr><th>Include</th><th>No</th><th>Step</th><th>Weight</th><th>Plan start</th><th>Plan finish</th></tr>
                </thead>
                <tbody>
                @php $phase = null; $phaseNo = 0; $n = 0; @endphp
                @foreach ($template as $item)
                    @if ($item->phase !== $phase)
                        @php $phase = $item->phase; $phaseNo++; $n = 0; @endphp
                        <tr class="phase"><td></td><td class="num">{{ $phaseNo }}</td><td colspan="4">{{ $phase }}</td></tr>
                    @endif
                    @php
                        $n++;
                        $base = "plans.$d.{$item->id}";
                        $included = $item->is_mandatory || ($submitted ? (bool) old("$base.include") : true);
                    @endphp
                    <tr data-item="{{ $item->id }}">
                        <td class="inc">
                            @if ($item->is_mandatory)
                                <span class="req" title="Mandatory step: always included">@include('partials.icon', ['name' => 'check']) Mandatory</span>
                            @else
                                <label><input type="checkbox" data-include name="plans[{{ $d }}][{{ $item->id }}][include]" value="1" @checked($included)> Optional</label>
                            @endif
                        </td>
                        <td class="num muted">{{ $phaseNo }}.{{ $n }}</td>
                        <td>{{ $item->name }}<div class="cell-sub">Evidence: {{ $item->evidence_required ?: '–' }}</div></td>
                        <td class="w">
                            <input data-weight type="number" name="plans[{{ $d }}][{{ $item->id }}][weight]" min="0" max="100" step="0.01" required
                                   aria-label="Weight of {{ $item->name }}" value="{{ $old("$base.weight", $item->weight) }}">
                        </td>
                        <td>
                            <input data-start type="date" name="plans[{{ $d }}][{{ $item->id }}][plan_start]" data-offset="{{ $item->offset_start }}" required
                                   aria-label="Plan start of {{ $item->name }}" value="{{ $old("$base.plan_start") }}">
                        </td>
                        <td>
                            <input data-finish type="date" name="plans[{{ $d }}][{{ $item->id }}][plan_finish]" data-offset="{{ $item->offset_finish }}" required
                                   aria-label="Plan finish of {{ $item->name }}" value="{{ $old("$base.plan_finish") }}">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </fieldset>
</div>
