@props(['values' => [], 'owner' => false])
<fieldset data-location-selectors data-endpoint="{{ route('locations.index') }}" class="row g-3 my-2">
    <legend class="form-label">Philippine locations <small class="text-muted-soft">(PSGC identifiers)</small></legend>
    @foreach(['region' => 'Region', 'province' => 'Province / special area', 'city' => 'City / municipality', 'barangay' => 'Barangay'] as $kind => $label)
        <div class="{{ $owner ? 'col-md-6' : 'col-12' }}"><label class="form-label small" for="{{ $kind }}_code">{{ $label }}</label><select class="form-select" id="{{ $kind }}_code" name="{{ $kind }}_code" data-location-kind="{{ $kind }}" data-selected="{{ old($kind.'_code', data_get($values, $kind.'_code')) }}"><option value="">Any / not selected</option></select></div>
    @endforeach
    <p class="form-text" data-location-status role="status">Independent cities and NCR may have no province. Select the region, then the city directly. Legacy free-text locations remain searchable.</p>
</fieldset>
