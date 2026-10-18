@props(['status'])
@php($tone = match($status) {'active', 'available', 'published', 'confirmed', 'paid', 'completed' => 'good', 'pending', 'occupied', 'partially_paid', 'overdue' => 'warn', 'inactive', 'unavailable', 'cancelled', 'terminated', 'rejected', 'failed' => 'bad', default => 'muted'})
<span {{ $attributes->class(['status-badge', 'status-'.$tone]) }}>{{ ucfirst(str_replace('_', ' ', $status ?? 'draft')) }}</span>
