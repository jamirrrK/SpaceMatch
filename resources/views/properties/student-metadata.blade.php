@if($space->student_friendly)<span class="amenity-chip mb-2">Welcomes students</span>@endif
@if($space->nearby_school)<p class="small text-muted-soft mb-2">Nearby: {{ $space->nearby_school }}@if($space->campus_distance_km !== null) · {{ number_format((float) $space->campus_distance_km, 2) }} km (owner-reported, unverified)@endif</p>@endif
@if($space->furnishing)<p class="small text-muted-soft mb-2">{{ ucfirst(str_replace('_', ' ', $space->furnishing)) }}</p>@endif
@if(request()->routeIs('properties.show'))
@if($space->utilities_notes)<p class="small"><strong>Utilities:</strong> {{ $space->utilities_notes }}</p>@endif
@if($space->transport_notes)<p class="small"><strong>Transport (owner-reported):</strong> {{ $space->transport_notes }}</p>@endif
@endif
