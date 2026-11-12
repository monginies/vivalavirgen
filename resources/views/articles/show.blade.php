@extends('layouts.app')
@section('title', $article->name)
@section('content')
<div class="head">
    <div><h1>{{ $article->name }}</h1><div class="sub"><span class="mono">{{ $article->reference }}</span> · {{ $article->category->name }}
        @unless ($article->active) · <span class="badge grey">Inactivo</span>@endunless</div></div>
    <div class="actions">
        <a class="btn sec" href="{{ route('articles.labels', $article) }}">Imprimir etiquetas QR</a>
        <a class="btn" href="{{ route('articles.edit', $article) }}">Editar</a>
    </div>
</div>
<div class="grid g2">
    <div class="card">
        @if ($main = $article->photos->first())<a href="{{ $main->url() }}" target="_blank"><img class="photo" src="{{ $main->url() }}" alt="" style="margin-bottom:14px;max-height:260px"></a>@endif
        <dl class="props">
            <dt>Precio alquiler</dt><dd>{{ number_format($article->rental_price, 2, ',', '.') }} € <span class="muted">sin IVA</span></dd>
            <dt>Reposición</dt><dd>{{ number_format($article->replacement_price, 2, ',', '.') }} € / ud.</dd>
            @if ($article->dimensions)<dt>Medidas</dt><dd>{{ $article->dimensions }}</dd>@endif
            @if ($article->description)<dt>Descripción</dt><dd>{!! nl2br(e($article->description)) !!}</dd>@endif
            @if ($article->notes)<dt>Notas</dt><dd>{!! nl2br(e($article->notes)) !!}</dd>@endif
        </dl>
    </div>
    <div class="card">
        <h2>Stock</h2>
        <table>
            @foreach (\App\Enums\UnitStatus::cases() as $s)
                <tr><td><span class="badge {{ $s->color() }}">{{ $s->label() }}</span></td><td class="num">{{ $statusCounts[$s->value] ?? 0 }}</td></tr>
            @endforeach
            <tr><td><b>Total</b></td><td class="num"><b>{{ $article->units->count() }}</b></td></tr>
        </table>
        <form method="post" action="{{ route('articles.units.store', $article) }}" class="actions" style="margin-top:16px;align-items:flex-end">
            @csrf
            <div style="flex:1;min-width:100px"><label for="f-count">Añadir unidades</label><input id="f-count" type="number" name="count" min="1" max="500" value="1" required></div>
            <div style="flex:1;min-width:140px"><label for="f-acq">Fecha de alta</label><input id="f-acq" type="date" name="acquired_at"></div>
            <button class="btn">Añadir</button>
        </form>
        @error('count')<div class="err">{{ $message }}</div>@enderror
    </div>
</div>
@include('articles._photos')
<div class="card table-wrap">
    <h2>Unidades</h2>
    <table>
        <thead><tr><th>Código</th><th>Estado</th><th>Alta</th><th>Notas</th><th></th></tr></thead>
        <tbody>
        @forelse ($article->units as $u)
            <tr>
                <td class="mono"><a href="{{ route('units.show', $u) }}">{{ $u->code }}</a></td>
                <td><span class="badge {{ $u->status->color() }}">{{ $u->status->label() }}</span></td>
                <td class="muted">{{ $u->acquired_at?->format('d/m/Y') }}</td>
                <td class="muted">{{ Str::limit($u->notes, 60) }}</td>
                <td class="actions" style="justify-content:flex-end"><a class="btn sec sm" href="{{ route('units.qr', $u) }}" target="_blank">QR</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Este artículo aún no tiene unidades.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
