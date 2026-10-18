<section class="property-form-section">
<h2 class="panel-title mb-3">Student housing information</h2>
<p class="text-muted-soft small">Only publish facts you can support. Student-friendly does not mean student-only. Campus distance is your reported distance, not a verified route or travel time.</p>
<input type="hidden" name="student_friendly" value="0"><div class="form-check mb-3"><input class="form-check-input" id="student_friendly" name="student_friendly" type="checkbox" value="1" @checked(old('student_friendly', $space->student_friendly))><label class="form-check-label" for="student_friendly">This accommodation welcomes student renters</label></div>
<div class="row g-3">
<div class="col-md-6"><x-form-field name="nearby_school" label="Nearby school and campus" :value="$space->nearby_school" maxlength="150" /></div>
<div class="col-md-6"><x-form-field name="campus_distance_km" label="Owner-reported distance to that campus (km)" type="number" :value="$space->campus_distance_km" min="0" max="1000" step="0.01" /></div>
<div class="col-md-6"><label class="form-label" for="furnishing">Furnishing</label><select class="form-select" name="furnishing" id="furnishing"><option value="">Not disclosed</option>@foreach(['unfurnished', 'semi_furnished', 'furnished'] as $value)<option value="{{ $value }}" @selected(old('furnishing', $space->furnishing) === $value)>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label" for="utilities_notes">Utilities included and any disclosed charges</label><textarea class="form-control" id="utilities_notes" name="utilities_notes" maxlength="2000" rows="3">{{ old('utilities_notes', $space->utilities_notes) }}</textarea></div>
<div class="col-12"><label class="form-label" for="transport_notes">Transport access (owner-reported)</label><textarea class="form-control" id="transport_notes" name="transport_notes" maxlength="2000" rows="3">{{ old('transport_notes', $space->transport_notes) }}</textarea></div>
</div></section>
