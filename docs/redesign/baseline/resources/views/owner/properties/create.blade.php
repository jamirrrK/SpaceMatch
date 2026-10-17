@extends('layouts.app')
@section('title', 'Add property | SpaceMatch')
@section('content')
<div class="max-content pb-4"><a class="small-link" href="{{ route('owner.properties.index') }}">← My properties</a><div class="mt-4 mb-4"><div class="eyebrow mb-2">Grow your portfolio</div><h1 class="page-heading mb-1">Add a property</h1><p class="text-muted-soft small mb-0">Start with the basics. You can add rental spaces and photos next.</p></div><form class="panel p-4 p-lg-5" action="{{ route('owner.properties.store') }}" method="POST">@csrf @include('owner.properties._form')<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-dark" href="{{ route('owner.properties.index') }}">Cancel</a><button class="btn btn-primary-custom" type="submit">Create property</button></div></form></div>
@endsection
