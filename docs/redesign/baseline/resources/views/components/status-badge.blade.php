@props(['status'])
@php($tone = match($status) {'active', 'available', 'published', 'confirmed', 'paid' => 'good', 'pending', 'occupied' => 'warn', 'inactive', 'unavailable', 'cancelled', 'failed' => 'bad', default => 'muted'})
<span {{ $attributes->class(['status-badge', 'status-'.$tone]) }}>{{ ucfirst(str_replace('_', ' ', $status ?? 'draft')) }}</span>
