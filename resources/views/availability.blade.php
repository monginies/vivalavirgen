@extends('layouts.app')
@section('title', 'Disponibilidad')
@section('content')
<div class="head"><h1>Disponibilidad</h1>
    <a class="btn" href="{{ route('orders.create', ['start' => $from->toDateString(), 'end' => $to->toDateString()]) }}">+ Pedido para estas fechas</a>
</div>
<form class="filters" method="get">
    <label class="check">Desde <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
    <label class="check">Hasta <input type="date" name="to" value="{{ $to->toDateString() }}"></label>
    <select name="category">
        <option value="">Todas las categorías</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Artículo…">
    <button class="btn sec">Consultar</button>
</form>
@error('to')<div class="flash ko">{{ $message }}</div>@enderror
<div class="card table-wrap">
    <table>
        <thead><tr><th class="hide-sm">Referencia</th><th>Artículo</th><th class="hide-sm">Categoría</th><th class="num">Utilizables</th><th class="num hide-sm">Reservadas</th><th class="num">Libres</th></tr></thead>
        <tbody>
        @forelse ($articles as $a)
            @php($av = $availability[$a->id])
            <tr>
                <td class="mono hide-sm">{{ $a->reference }}</td>
                <td class="grow"><a href="{{ route('articles.show', $a) }}">{{ $a->name }}</a></td>
                <td class="muted hide-sm">{{ $a->category->name }}</td>
                <td class="num" data-label="Total">{{ $av['capacity'] }}</td>
                <td class="num hide-sm">{{ $av['reserved'] ?: '—' }}</td>
                <td class="num" data-label="Libres">
                    @if ($av['available'] < 0)<span class="badge red">{{ $av['available'] }}</span>
                    @elseif ($av['available'] === 0)<span class="badge grey">0</span>
                    @else<b>{{ $av['available'] }}</b>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No hay artículos activos que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="muted" style="font-size:13px">«Utilizables» no cuenta las unidades de baja ni en reparación. «Reservadas» es el máximo reservado en un mismo día del intervalo; incluye pre-reservas vigentes y material que aún no ha vuelto.</p>
@endsection
