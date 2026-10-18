@extends('layouts.app')
@section('title', 'Housing preferences | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <div class="eyebrow mb-2">Find a closer fit</div>
        <h1 class="page-heading mb-1">Housing preferences</h1>
        <p class="text-muted-soft mb-0">Save your priorities, then use them to search matching listings. Availability still requires owner confirmation.</p>
    </div>
    <form class="panel p-4" method="POST" action="{{ route('renter.preferences.update') }}">
        @csrf
        @method('PUT')
        @include('renter.preferences.student-preferences')
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="school_or_campus">School or campus</label>
                <input class="form-control" id="school_or_campus" name="school_or_campus" maxlength="150" value="{{ old('school_or_campus', $preferences?->school_or_campus) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="move_in_date">Desired move-in date</label>
                <input class="form-control" type="date" id="move_in_date" name="move_in_date" min="{{ now()->toDateString() }}" value="{{ old('move_in_date', $preferences?->move_in_date?->toDateString()) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="preferred_location">Preferred area or city</label>
                <input class="form-control" id="preferred_location" name="preferred_location" maxlength="150" value="{{ old('preferred_location', $preferences?->preferred_location) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="space_type">Space type</label>
                <select class="form-select" id="space_type" name="space_type">
                    <option value="">No preference</option>
                    @foreach(config('property.types', []) as $type => $label)
                        <option value="{{ $type }}" @selected(old('space_type', $preferences?->space_type) === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <fieldset class="col-md-6">
                <legend class="form-label">Monthly budget (PHP)</legend>
                <div class="row g-2">
                    <div class="col-6"><label class="visually-hidden" for="budget_min">Minimum monthly budget</label><input class="form-control" type="number" id="budget_min" name="budget_min" min="0" step="0.01" value="{{ old('budget_min', $preferences?->budget_min) }}" placeholder="Minimum"></div>
                    <div class="col-6"><label class="visually-hidden" for="budget_max">Maximum monthly budget</label><input class="form-control" type="number" id="budget_max" name="budget_max" min="0" step="0.01" value="{{ old('budget_max', $preferences?->budget_max) }}" placeholder="Maximum"></div>
                </div>
            </fieldset>
            <div class="col-md-6">
                <label class="form-label" for="capacity">People to accommodate</label>
                <input class="form-control" type="number" id="capacity" name="capacity" min="1" max="100" value="{{ old('capacity', $preferences?->capacity) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="amenities_notes">Amenities or other needs</label>
                <textarea class="form-control" id="amenities_notes" name="amenities_notes" rows="3" maxlength="1000" aria-describedby="amenities-help">{{ old('amenities_notes', $preferences?->amenities_notes) }}</textarea>
                <div class="form-text" id="amenities-help">For example: Wi-Fi, study area, or a quiet environment. This is a note for your search, not a listing filter.</div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-primary-custom" type="submit">Save preferences</button>
            <a class="btn btn-outline-dark" href="{{ route('properties.index', \App\Services\StudentDiscovery::filters($preferences, auth()->user()->is_student)) }}">Search with my preferences</a>
        </div>
    </form>
</div>
@endsection
