@props(['spaces'])
@if($spaces->isNotEmpty())
    <div class="detail-price mb-4"><div class="eyebrow mb-2">Monthly rent from</div><div class="listing-price fs-3">&#8369;{{ number_format($spaces->min('price'), 2) }}<small> / month</small></div></div>
@endif
