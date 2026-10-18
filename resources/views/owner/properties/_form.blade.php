<p class="small text-muted-soft mb-4">Fields marked <span class="required-mark">*</span> are required.</p>
<section class="property-form-section"><h2 class="panel-title mb-3">Property information</h2><div class="row g-3"><div class="col-md-8"><x-form-field name="property_name" label="Property name" :value="$property->property_name" maxlength="255" :required="true" /></div><div class="col-md-4"><label class="form-label" for="property_type">Property type <span class="required-mark">*</span></label><select class="form-select @error('property_type') is-invalid @enderror" id="property_type" name="property_type" required><option value="">Choose a type</option>@foreach(config('property.types', []) as $value => $label)<option value="{{ $value }}" @selected(old('property_type', $property->property_type) === $value)>{{ $label }}</option>@endforeach</select>@error('property_type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5" maxlength="10000" placeholder="Tell renters about the property and its surroundings.">{{ old('description', $property->description) }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div></section>
<section class="property-form-section">
    <h2 class="panel-title mb-3">Location</h2>
    <div class="row g-3">
        <div class="col-12"><x-form-field name="address" label="Street address" :value="$property->address" maxlength="255" :required="true" autocomplete="street-address" /></div>
        <div class="col-md-4">
            <label class="form-label" for="province">Province / region <span class="required-mark">*</span></label>
            <select class="form-select @error('province') is-invalid @enderror" id="province" name="province" required data-current="{{ old('province', $property->province) }}">
                <option value="">Loading provinces…</option>
            </select>
            @error('province')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="city">City / municipality <span class="required-mark">*</span></label>
            <select class="form-select @error('city') is-invalid @enderror" id="city" name="city" required disabled data-current="{{ old('city', $property->city) }}">
                <option value="">Select a province / region first</option>
            </select>
            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="barangay">Barangay</label>
            <select class="form-select @error('barangay') is-invalid @enderror" id="barangay" name="barangay" disabled data-current="{{ old('barangay', $property->barangay) }}">
                <option value="">Select a city / municipality first</option>
            </select>
            @error('barangay')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4"><x-form-field name="postal_code" label="Postal code" :value="$property->postal_code" maxlength="20" autocomplete="postal-code" /></div>
        <div class="col-12"><p id="philippine-location-status" class="small text-muted-soft mb-0" role="status" aria-live="polite">Location choices are loading.</p></div>
    </div>
</section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const provinceSelect = document.getElementById('province');
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
    const status = document.getElementById('philippine-location-status');
    if (!provinceSelect || !citySelect || !barangaySelect) return;

    const api = 'https://psgc.cloud/api/v2';
    const ncrValue = 'National Capital Region';
    let regions = [];

    const normalized = (value) => (value || '').trim().toLocaleLowerCase().replace(/\s*\([^)]*\)/g, '').replace(/\s+/g, ' ');
    const isNcrName = (value) => ['national capital region', 'ncr', 'metro manila'].includes(normalized(value));
    const setOptions = (select, items, placeholder, currentValue = '') => {
        select.replaceChildren(new Option(placeholder, ''));
        items.forEach((item) => {
            const option = new Option(item.name, item.name);
            option.dataset.code = item.code;
            select.add(option);
        });
        const matching = [...select.options].find((option) => normalized(option.value) === normalized(currentValue));
        if (currentValue && !matching) select.add(new Option(currentValue, currentValue));
        select.value = matching ? matching.value : (currentValue || '');
    };
    const getJson = async (path) => {
        const response = await fetch(`${api}${path}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Location list request failed');
        const data = await response.json();
        if (!Array.isArray(data)) throw new Error('Unexpected location list response');
        return data;
    };
    const currentProvince = provinceSelect.dataset.current;
    const currentCity = citySelect.dataset.current;
    const currentBarangay = barangaySelect.dataset.current;

    const loadBarangays = async (cityCode, selected = '') => {
        barangaySelect.disabled = true;
        setOptions(barangaySelect, [], 'Loading barangays…');
        if (!cityCode) {
            setOptions(barangaySelect, [], 'Select a city / municipality first', selected);
            return;
        }
        try {
            const places = await getJson(`/cities-municipalities/${encodeURIComponent(cityCode)}/barangays`);
            setOptions(barangaySelect, places, 'Choose a barangay', selected);
            barangaySelect.disabled = false;
            status.textContent = 'Choose the barangay, then enter the street address above.';
        } catch (error) {
            setOptions(barangaySelect, [], 'Barangays could not be loaded', selected);
            status.textContent = 'Barangay choices could not be loaded. Check your connection and reload this page.';
        }
    };

    const loadCities = async (provinceOption, selected = '', selectedBarangay = '') => {
        citySelect.disabled = true;
        barangaySelect.disabled = true;
        setOptions(citySelect, [], 'Loading cities / municipalities…');
        setOptions(barangaySelect, [], 'Select a city / municipality first', selectedBarangay);
        if (!provinceOption || !provinceOption.value) {
            setOptions(citySelect, [], 'Select a province / region first', selected);
            return;
        }
        try {
            const path = provinceOption.dataset.ncr === 'true'
                ? `/regions/${encodeURIComponent(provinceOption.dataset.code)}/cities-municipalities`
                : `/provinces/${encodeURIComponent(provinceOption.dataset.code)}/cities-municipalities`;
            const places = await getJson(path);
            setOptions(citySelect, places, 'Choose a city / municipality', selected);
            citySelect.disabled = false;
            status.textContent = 'Select a city or municipality to load its barangays.';
            const selectedCity = [...citySelect.options].find((option) => option.value && normalized(option.value) === normalized(selected));
            if (selectedCity?.dataset.code) await loadBarangays(selectedCity.dataset.code, selectedBarangay);
        } catch (error) {
            setOptions(citySelect, [], 'Cities / municipalities could not be loaded', selected);
            status.textContent = 'City and municipality choices could not be loaded. Check your connection and reload this page.';
        }
    };

    provinceSelect.addEventListener('change', () => {
        const option = provinceSelect.selectedOptions[0];
        const oldCity = '';
        loadCities(option, oldCity, '');
    });
    citySelect.addEventListener('change', () => {
        const option = citySelect.selectedOptions[0];
        loadBarangays(option?.dataset.code || '', '');
    });

    Promise.all([getJson('/provinces'), getJson('/regions')]).then(([provinces, loadedRegions]) => {
        regions = loadedRegions;
        const ncr = regions.find((region) => isNcrName(region.name));
        const choices = provinces.map((province) => ({ name: province.name, code: province.code, ncr: false }));
        if (ncr) choices.push({ name: ncrValue, code: ncr.code, ncr: true });
        choices.sort((a, b) => a.name.localeCompare(b.name, 'en'));
        setOptions(provinceSelect, choices, 'Choose a province / region', isNcrName(currentProvince) ? ncrValue : currentProvince);
        choices.forEach((choice) => {
            const option = [...provinceSelect.options].find((item) => item.value === choice.name);
            if (option) option.dataset.ncr = choice.ncr ? 'true' : 'false';
        });
        provinceSelect.disabled = false;
        status.textContent = 'Select a province or region to load its cities and municipalities.';
        if (provinceSelect.value) loadCities(provinceSelect.selectedOptions[0], currentCity, currentBarangay);
    }).catch(() => {
        setOptions(provinceSelect, [], 'Province list could not be loaded', currentProvince);
        provinceSelect.disabled = false;
        status.textContent = 'Location choices could not be loaded. Check your connection and reload this page.';
    });
});
</script>
@endpush
<section class="property-form-section"><h2 class="panel-title mb-1">Map coordinates <span class="text-muted-soft fw-normal small">(optional)</span></h2><p class="small text-muted-soft mb-3">Add the exact coordinates if you have them.</p><div class="row g-3"><div class="col-md-6"><x-form-field name="latitude" label="Latitude" type="number" :value="$property->latitude" step="0.0000001" min="-90" max="90" /></div><div class="col-md-6"><x-form-field name="longitude" label="Longitude" type="number" :value="$property->longitude" step="0.0000001" min="-180" max="180" /></div></div></section>
