@extends('layouts.app')
@section('title', 'Clientes')
@section('content')
<div class="head"><h1>Clientes</h1><a class="btn" href="{{ route('clients.create') }}">+ Nuevo cliente</a></div>
<form class="filters" method="get">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre, nombre comercial, NIF/CIF, email, teléfono…">
    <label class="check"><input type="checkbox" name="pendientes" value="1" @checked(request('pendientes')) onchange="this.form.submit()"> Solo pendientes de aprobar</label>
    <button class="btn sec">Buscar</button>
</form>
<div class="card table-wrap">
    <table>
        <thead><tr><th>Nombre</th><th>Tipo</th><th>Web</th><th>NIF/CIF</th><th>Contacto</th><th>Ciudad</th></tr></thead>
        <tbody>
        @forelse ($clients as $c)
            <tr>
                <td><a href="{{ route('clients.show', $c) }}">{{ $c->name }}</a>@if ($c->trade_name)<div class="muted" style="font-size:13px">{{ $c->trade_name }}</div>@endif</td>
                <td class="muted">{{ $c->typeLabel() }}@if ($c->category)<div style="margin-top:3px">@include('partials.tier', ['category' => $c->category])</div>@endif</td>
                <td>@if ($c->hasPortalAccess())@php([$wl, $wc] = $c->portalStatus())<span class="badge {{ $wc }}">{{ $wl }}</span>@endif</td>
                <td class="mono">{{ $c->tax_id }}</td>
                <td>{{ $c->contact_name }}@if ($c->phone)<div class="muted">{{ $c->phone }}</div>@endif</td>
                <td class="muted">{{ $c->city }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No hay clientes que coincidan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $clients->links('partials.pagination') }}
@endsection
