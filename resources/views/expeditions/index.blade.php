@extends('layouts.app')
@section('title', 'Expediciones')
@section('content')
@php($S = \App\Enums\OrderStatus::class)
<div class="head"><h1>Expediciones</h1><button type="button" class="btn sec" data-help-toggle="help-box" aria-expanded="false" data-open="Cómo dar salida y entrada" data-close="Cerrar explicación">Cómo dar salida y entrada</button></div>
<div id="help-box" hidden>@include('expeditions._help')</div>
<form class="filters" method="get">
    <select name="status" onchange="this.form.submit()">
        <option value="">Todas</option>
        <option value="paid" @selected(request('status') === 'paid')>Pendientes de salir</option>
        <option value="delivered" @selected(request('status') === 'delivered')>Fuera (pendientes de volver)</option>
        <option value="returned" @selected(request('status') === 'returned')>Devueltas</option>
    </select>
</form>
<div class="card table-wrap">
    <table>
        <thead><tr><th>Pedido</th><th>Entrega</th><th>Recogida</th><th class="hide-sm">Lugar</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($orders as $o)
            <tr>
                <td class="grow"><a class="mono" href="{{ route('expeditions.show', $o) }}">{{ $o->number }}</a><br><span class="muted" style="font-size:13px">{{ $o->client->name }}</span></td>
                <td data-label="Entrega">{{ $o->start_date->isoFormat('ddd D/MM') }}@if ($o->delivery_time)<br><span class="muted" style="font-size:13px">{{ $o->delivery_time }}</span>@endif</td>
                <td data-label="Recogida">{{ $o->end_date->isoFormat('ddd D/MM') }}@if ($o->pickup_time)<br><span class="muted" style="font-size:13px">{{ $o->pickup_time }}</span>@endif</td>
                <td class="hide-sm">{{ $o->venue?->name ?? ($o->destination_city ?: '—') }}</td>
                <td>
                    @if ($o->status === $S::Paid)<span class="badge amber">Pendiente de salir</span>
                    @elseif ($o->status === $S::Delivered)<span class="badge blue">Fuera · {{ $o->units_count }} ud.</span>
                    @else<span class="badge green">Devuelta</span>@endif
                </td>
                <td class="actions" style="justify-content:flex-end">
                    @if ($o->status === $S::Paid)<a class="btn sm" href="{{ route('scan.show', [$o, 'salida']) }}">Dar salida</a>
                    @elseif ($o->status === $S::Delivered)<a class="btn sm" href="{{ route('scan.show', [$o, 'entrada']) }}">Dar entrada</a>@endif
                    <a class="btn sec sm" href="{{ route('orders.expedition', $o) }}" target="_blank">Hojas PDF</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No hay expediciones.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<script>
document.querySelectorAll('[data-help-toggle]').forEach(b => b.addEventListener('click', () => {
    const box = document.getElementById(b.dataset.helpToggle);
    box.hidden = !box.hidden;
    b.setAttribute('aria-expanded', String(!box.hidden));
    b.textContent = box.hidden ? b.dataset.open : b.dataset.close;
}));
</script>
@endsection
