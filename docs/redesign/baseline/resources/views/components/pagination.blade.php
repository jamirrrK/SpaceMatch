@props(['paginator'])
@if($paginator->total() > 0)
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4"><span class="pagination-summary">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}</span>{{ $paginator->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
@endif
