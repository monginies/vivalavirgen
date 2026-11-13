@extends('layouts.app')
@section('title', $article->name)
@section('content')
<div class="head">
    <div><h1>{{ $article->name }}</h1><div class="sub"><span class="mono">{{ $article->reference }}</span> · {{ $article->category->name }}
        @if ($article->is_bundle) · <span class="badge grey">Combinado</span>@endif
        @if ($article->exclusive) · <span class="badge grey">Exclusivo</span>@endif
        @unless ($article->active) · <span class="badge grey">Inactivo</span>@endunless</div></div>
    <div class="actions">
        @unless ($article->is_bundle)<a class="btn sec" href="{{ route('articles.labels', $article) }}">Imprimir etiquetas QR</a>@endunless
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
            @if ($article->exclusive)
                <dt>Exclusivo para</dt><dd>@forelse ($article->clients as $c)<a href="{{ route('clients.show', $c) }}">{{ $c->name }}</a>@unless ($loop->last), @endunless @empty<span class="muted">ningún cliente (nadie lo puede alquilar)</span>@endforelse</dd>
            @endif
            @if ($article->bundles->isNotEmpty())
                <dt>Forma parte de</dt><dd>@foreach ($article->bundles as $b)<a href="{{ route('articles.show', $b) }}">{{ $b->name }}</a> ({{ $b->pivot->quantity }})@unless ($loop->last), @endunless @endforeach</dd>
            @endif
        </dl>
    </div>
    @if ($article->is_bundle)
    <div class="card">
        <h2>Piezas</h2>
        @php($sum = $article->components->sum(fn ($c) => $c->rental_price * $c->pivot->quantity))
        <table>
            <thead><tr><th>Artículo</th><th class="num">Por combinado</th><th class="num">Libres hoy</th></tr></thead>
            <tbody>
            @forelse ($article->components as $c)
                <tr>
                    <td><span class="mono">{{ $c->reference }}</span> <a href="{{ route('articles.show', $c) }}">{{ $c->name }}</a>@if ($c->trashed()) <span class="badge grey">Eliminado</span>@elseif (! $c->active) <span class="badge grey">Inactivo</span>@endif</td>
                    <td class="num">{{ $c->pivot->quantity }}</td>
                    <td class="num">{{ max(0, $today[$c->id]['available'] ?? 0) }} / {{ $today[$c->id]['capacity'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">Aún no tiene piezas. <a href="{{ route('articles.edit', $article) }}">Añádelas</a> para poder alquilarlo.</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="muted" style="margin-top:12px">Se pueden montar hoy: <b>{{ max(0, $today[$article->id]['available'] ?? 0) }}</b>
            @if ($article->components->isNotEmpty()) · Suma de las piezas sueltas: {{ number_format($sum, 2, ',', '.') }} € (precio del combinado: {{ number_format($article->rental_price, 2, ',', '.') }} €)@endif</p>
    </div>
    @else
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
    @endif
</div>
@include('articles._photos')
@unless ($article->is_bundle)
<div class="card table-wrap">
    <h2>Unidades</h2>
    <table>
        <thead><tr><th>Código</th><th>Estado</th><th class="hide-sm">Alta</th><th class="hide-sm">Notas</th><th></th></tr></thead>
        <tbody>
        @forelse ($article->units as $u)
            <tr>
                <td class="mono"><a href="{{ route('units.show', $u) }}">{{ $u->code }}</a></td>
                <td><span class="badge {{ $u->status->color() }}">{{ $u->status->label() }}</span></td>
                <td class="muted hide-sm">{{ $u->acquired_at?->format('d/m/Y') }}</td>
                <td class="muted hide-sm">{{ Str::limit($u->notes, 60) }}</td>
                <td class="actions" style="justify-content:flex-end"><a class="btn sec sm" href="{{ route('units.qr', $u) }}" target="_blank">QR</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Este artículo aún no tiene unidades.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endunless
@endsection
