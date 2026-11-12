@if ($paginator->hasPages())
<ul class="pagination">
    @if (!$paginator->onFirstPage())<li><a href="{{ $paginator->previousPageUrl() }}">‹ Anterior</a></li>@endif
    @foreach ($elements as $element)
        @if (is_string($element))<li><span>…</span></li>@endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                <li @class(['active' => $page == $paginator->currentPage()])>@if ($page == $paginator->currentPage())<span>{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif</li>
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())<li><a href="{{ $paginator->nextPageUrl() }}">Siguiente ›</a></li>@endif
</ul>
@endif
