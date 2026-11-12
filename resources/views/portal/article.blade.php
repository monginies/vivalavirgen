@extends('layouts.portal')
@section('title', $article->name)
@section('content')
<p style="margin:0 0 14px"><a href="{{ route('portal.home', ['categoria' => $article->category->code]) }}">← {{ $article->category->name }}</a></p>
<div class="pt-detail">
    <div class="pt-gallery">
        @php($photos = $article->photos)
        @if ($photos->isNotEmpty())
            <img id="pt-main" src="{{ $photos->first()->url() }}" alt="{{ $article->name }}">
            @if ($photos->count() > 1)
                <div class="pt-thumbs">
                    @foreach ($photos as $p)
                        <button type="button" data-src="{{ $p->url() }}" @class(['on' => $loop->first])><img src="{{ $p->thumbUrl() }}" alt=""></button>
                    @endforeach
                </div>
            @endif
        @else
            <span class="pt-noimg" style="aspect-ratio:4/3;border-radius:14px"></span>
        @endif
    </div>
    <div>
        <h1>{{ $article->name }}</h1>
        @if ($article->dimensions)<p class="muted">Medidas: {{ $article->dimensions }}</p>@endif
        <div style="margin:14px 0">@include('portal._price', ['article' => $article])</div>
        @if ($availability !== null)
            <p><span class="badge {{ $availability ? 'green' : 'grey' }}">{{ $availability }} libres</span> <span class="muted" style="font-size:13px">para la fecha de tu pedido</span></p>
        @endif
        <form method="post" action="{{ route('portal.cart.add', $article) }}" class="pt-add" style="max-width:320px">
            @csrf
            <input type="number" name="quantity" value="1" min="1" max="10000" aria-label="Cantidad">
            <button class="btn">Añadir al pedido</button>
        </form>
        @if ($inCart)<p class="muted" style="font-size:14px">Ya tienes {{ $inCart }} en tu pedido. <a href="{{ route('portal.cart') }}">Ver pedido</a></p>@endif
        @if ($article->description)<div class="pt-desc">{!! nl2br(e($article->description)) !!}</div>@endif
    </div>
</div>
@push('scripts')
<script>
document.querySelectorAll('.pt-thumbs button').forEach(b => b.addEventListener('click', () => {
    document.getElementById('pt-main').src = b.dataset.src;
    document.querySelectorAll('.pt-thumbs button').forEach(x => x.classList.toggle('on', x === b));
}));
</script>
@endpush
@endsection
