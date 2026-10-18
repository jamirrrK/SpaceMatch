@if(auth()->user()->role === 'renter')
<section class="panel p-4 mb-4" aria-labelledby="report-issue-title"><h2 class="panel-title" id="report-issue-title">Report a maintenance issue</h2>
@if($activeBookings->isNotEmpty())
<form method="POST" action="{{ route('maintenance.store') }}" enctype="multipart/form-data">@csrf
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="booking_id">Active rental</label><select class="form-select" name="booking_id" id="booking_id" required>@foreach($activeBookings as $booking)<option value="{{ $booking->id }}">{{ $booking->space->space_name }} · {{ $booking->reference }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label" for="evidence">Evidence (optional)</label><input class="form-control" id="evidence" name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf"><div class="form-text">JPG, PNG or PDF, up to 4 MB. Accessible only to your rental participants and administrators.</div></div><div class="col-12"><label class="form-label" for="description">Describe the issue</label><textarea class="form-control" id="description" name="description" minlength="10" maxlength="2000" rows="3" required>{{ old('description') }}</textarea></div></div><button class="btn btn-primary-custom mt-3">Submit maintenance request</button></form>
@else<p class="text-muted-soft mb-0">Maintenance reporting becomes available when your rental is marked active by the owner. Contact the owner through your inquiry for move-in concerns.</p>@endif
</section>
@endif
