@props(['title' => 'Nothing here yet', 'message' => '', 'href' => null, 'action' => null])
<div {{ $attributes->class(['empty-state']) }}><div class="empty-icon" aria-hidden="true"><x-icon name="home" /></div><h3>{{ $title }}</h3><p>{{ $message }}</p>@if($href && $action)<a class="btn btn-primary-custom" href="{{ $href }}">{{ $action }}</a>@endif{{ $slot }}</div>
