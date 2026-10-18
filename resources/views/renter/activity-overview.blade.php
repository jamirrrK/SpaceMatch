<div class="row g-4 mb-4">
@foreach([
    ['title' => 'Recent inquiries', 'records' => $recentInquiries, 'route' => 'renter.inquiries.index'],
    ['title' => 'Upcoming viewings', 'records' => $upcomingViewings, 'route' => 'renter.viewings.index'],
    ['title' => 'Rental requests', 'records' => $recentRequests, 'route' => 'renter.requests.index'],
    ['title' => 'Maintenance updates', 'records' => $recentMaintenance, 'route' => 'maintenance.index'],
    ['title' => 'Payment obligations', 'records' => $recentPayments, 'route' => 'payments.index']
] as $section)
<section class="col-md-6 col-xl-4"><div class="panel p-4 h-100"><h2 class="panel-title">{{ $section['title'] }}</h2><ul class="list-unstyled mb-3">@forelse($section['records'] as $record)<li class="py-2 border-bottom"><strong>{{ $record->space?->space_name ?? $record->booking?->space?->space_name ?? 'Rental' }}</strong><div class="small">{{ ucfirst(str_replace('_', ' ', $record->status ?? $record->display_status ?? 'Recorded')) }}</div>@if($section['route'] === 'payments.index')<div class="small">Balance ₱{{ number_format($record->outstanding_balance, 2) }} · Due {{ $record->due_date?->format('M j, Y') ?? 'not specified' }}</div>@endif</li>@empty<li class="small text-muted-soft py-2">No {{ strtolower($section['title']) }} yet. Your activity will appear here.</li>@endforelse</ul><a class="small-link" href="{{ route($section['route']) }}">View {{ strtolower($section['title']) }}</a></div></section>
@endforeach
</div>
@if($availableSpaces->isEmpty())<section class="panel p-4 mb-4"><h2 class="panel-title">No matching recommendations yet</h2><p class="text-muted-soft">No published listing currently matches your saved priorities. Adjust your preferences or explore all available spaces.</p><a class="small-link" href="{{ route('properties.index') }}">Explore all rentals</a></section>@endif
