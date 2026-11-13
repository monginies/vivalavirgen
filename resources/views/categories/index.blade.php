@extends('layouts.app')
@section('title', 'Categorías')
@section('content')
<div class="head"><h1>Categorías</h1><a class="btn" href="{{ route('categories.create') }}">+ Nueva categoría</a></div>
<div class="card table-wrap">
    <table>
        <thead><tr><th>Código</th><th>Nombre</th><th class="hide-sm">Descripción</th><th class="num">Artículos</th><th></th></tr></thead>
        <tbody>
        @forelse ($categories as $c)
            <tr>
                <td class="mono">{{ $c->code }}</td>
                <td class="grow"><a href="{{ route('articles.index', ['category' => $c->id]) }}">{{ $c->name }}</a>@unless ($c->visible) <span class="badge grey">Oculta en la web</span>@endunless</td>
                <td class="muted hide-sm">{{ Str::limit($c->description, 80) }}</td>
                <td class="num" data-label="Artículos">{{ $c->articles_count }}</td>
                <td class="actions" style="justify-content:flex-end"><a class="btn sec sm" href="{{ route('categories.edit', $c) }}">Editar</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No hay categorías todavía.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
