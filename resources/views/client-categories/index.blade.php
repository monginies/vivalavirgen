@extends('layouts.app')
@section('title', 'Tipos de cliente')
@section('content')
<div class="head"><div><h1>Tipos de cliente</h1><div class="sub">Cada tipo tiene un descuento sobre el precio de alquiler. Se aplica en la web y al crear pedidos.</div></div>
    <a class="btn" href="{{ route('client-categories.create') }}">+ Nuevo tipo</a></div>
<div class="card table-wrap">
    <table>
        <thead><tr><th>Tipo</th><th class="num">Descuento</th><th class="num">Clientes</th></tr></thead>
        <tbody>
        @forelse ($categories as $c)
            <tr>
                <td><a href="{{ route('client-categories.edit', $c) }}">{{ $c->name }}</a></td>
                <td class="num">{{ rtrim(rtrim(number_format($c->discount_percent, 2, ',', ''), '0'), ',') }} %</td>
                <td class="num">{{ $c->clients_count }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="empty">No hay tipos de cliente.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
