@props(['label', 'value'])
<div {{ $attributes->class(['stat-card']) }}><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div>@if($slot->isNotEmpty())<div class="small text-muted-soft mt-1">{{ $slot }}</div>@endif</div>
