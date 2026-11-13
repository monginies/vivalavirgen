@extends('layouts.app')
@section('title', 'Artículos')
@section('content')
<div class="head"><h1>Artículos</h1><a class="btn" href="{{ route('articles.create', ['category' => request('category')]) }}">+ Nuevo artículo</a></div>
<form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o referencia…">
    <select name="category" onchange="this.form.submit()">
        <option value="">Todas las categorías</option>
        @foreach ($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <button class="btn sec">Filtrar</button>
</form>
<div class="card table-wrap">
    <table>
        <thead><tr><th></th><th>Referencia</th><th>Nombre</th><th>Categoría</th><th class="num">Precio alquiler</th><th class="num">Disponibles / total</th><th></th></tr></thead>
        <tbody>
        @forelse ($articles as $a)
            <tr>
                <td>@if ($a->photoUrl())<img class="thumb" src="{{ $a->photoUrl() }}" alt="">@else<div class="thumb"></div>@endif</td>
                <td class="mono">{{ $a->reference }}</td>
                <td><a href="{{ route('articles.show', $a) }}">{{ $a->name }}</a>@unless ($a->active) <span class="badge grey">Inactivo</span>@endunless</td>
                <td class="muted">{{ $a->category->name }}</td>
                <td class="num">{{ number_format($a->rental_price, 2, ',', '.') }} €</td>
                <td class="num">@if ($a->is_bundle)<span class="badge grey">Combinado</span> {{ $bundleCapacity[$a->id]['capacity'] ?? 0 }} montables@else{{ $a->available_count }} / {{ $a->units_count }}@endif</td>
                <td class="actions" style="justify-content:flex-end"><a class="btn sec sm" href="{{ route('articles.edit', $a) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No hay artículos que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $articles->links('partials.pagination') }}
@endsection
