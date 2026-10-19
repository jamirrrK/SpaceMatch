@props(['average' => null, 'count' => 0])
<div class="rental-rating small my-2" aria-label="{{ $count ? number_format((float)$average, 1).' out of 5 from '.$count.' reviews' : 'No reviews yet' }}">
    @if($count)<span aria-hidden="true" class="rating-stars">@for($star=1;$star<=5;$star++){{ $star <= round((float)$average) ? '★' : '☆' }}@endfor</span> <strong>{{ number_format((float)$average, 1) }}/5</strong> <span>({{ $count }} {{ \Illuminate\Support\Str::plural('review', $count) }})</span>@else<span>No reviews yet</span>@endif
</div>
