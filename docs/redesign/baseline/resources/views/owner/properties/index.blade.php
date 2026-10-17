@extends('layouts.app')
@section('title', 'My properties | SpaceMatch')
@section('content')
<div class="pb-4"><div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><div class="eyebrow mb-2">Your portfolio</div><h1 class="page-heading mb-1">My properties</h1><p class="small text-muted-soft mb-0">Manage property details, rental spaces, and listing visibility.</p></div><a class="btn btn-primary-custom" href="{{ route('owner.properties.create') }}">+ Add property</a></div><div class="row g-4">@forelse($properties as $property)<div class="col-md-6 col-xl-4"><x-property-card :property="$property" /></div>@empty<div class="col-12"><x-empty-state title="Your first property is a few details away" message="Create a property to start organizing your rental spaces and publishing listings." :href="route('owner.properties.create')" action="Add property" /></div>@endforelse</div><x-pagination :paginator="$properties" /></div>
@endsection
