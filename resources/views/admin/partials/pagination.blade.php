@if ($paginator->hasPages())
    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2" aria-label="Pagination">
        <div class="bf-muted bf-small">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} sur {{ $paginator->total() }}
        </div>
        <ul class="pagination pagination-sm">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Page précédente"><i class="bi bi-chevron-left"></i></a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Page suivante"><i class="bi bi-chevron-right"></i></a></li>
            @else
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @endif
        </ul>
    </nav>
@endif
