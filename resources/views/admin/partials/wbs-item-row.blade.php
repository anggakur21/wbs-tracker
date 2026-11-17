{{-- One editable WBS item. $i = row key, $row = item values (array). --}}
@php $mandatory = filter_var($row['is_mandatory'] ?? true, FILTER_VALIDATE_BOOLEAN); @endphp
<div class="wi">
    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <span class="no"></span>
    <span class="c-phase"><input data-phase type="text" name="items[{{ $i }}][phase]" value="{{ $row['phase'] ?? '' }}" list="phase-list" required maxlength="255" aria-label="Phase" placeholder="Phase"></span>
    <span class="c-name"><input data-name type="text" name="items[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" required maxlength="255" aria-label="Step name" placeholder="Step name"></span>
    <span class="c-weight"><input data-weight type="number" name="items[{{ $i }}][weight]" value="{{ $row['weight'] ?? 0 }}" min="0" max="100" step="0.01" required aria-label="Weight"></span>
    <label class="switch c-mand">
        <input type="hidden" name="items[{{ $i }}][is_mandatory]" value="0">
        <input data-mandatory type="checkbox" name="items[{{ $i }}][is_mandatory]" value="1" @checked($mandatory)>
        <span class="track-sw" aria-hidden="true"></span>
        <span data-mlabel>{{ $mandatory ? 'Mandatory' : 'Optional' }}</span>
    </label>
    <span class="days c-days">
        <input type="number" name="items[{{ $i }}][offset_start]" value="{{ $row['offset_start'] ?? 0 }}" min="0" max="3650" required aria-label="Start day">
        <span>to</span>
        <input type="number" name="items[{{ $i }}][offset_finish]" value="{{ $row['offset_finish'] ?? 0 }}" min="0" max="3650" required aria-label="Finish day">
    </span>
    <span class="tools">
        <button type="button" data-up title="Move up" aria-label="Move up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg></button>
        <button type="button" data-down title="Move down" aria-label="Move down"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <button type="button" data-del class="del" title="Remove step" aria-label="Remove step">@include('partials.icon', ['name' => 'trash'])</button>
    </span>
    <details class="more">
        <summary>Circulation &amp; required evidence</summary>
        <div class="more-grid">
            <label>Circulation <textarea name="items[{{ $i }}][circulation]" maxlength="2000">{{ $row['circulation'] ?? '' }}</textarea></label>
            <label>Required evidence <textarea name="items[{{ $i }}][evidence_required]" maxlength="2000">{{ $row['evidence_required'] ?? '' }}</textarea></label>
        </div>
    </details>
</div>
