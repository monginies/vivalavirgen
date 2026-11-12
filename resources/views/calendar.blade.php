@extends('layouts.app')
@section('title', 'Calendario')
@section('content')
@php($months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'])
<div class="head">
    <h1>Calendario · {{ ucfirst($months[$month->month - 1]) }} {{ $month->year }}</h1>
    <div class="actions">
        <a class="btn sec sm" href="{{ route('calendar', ['month' => $month->subMonth()->format('Y-m')]) }}">← Anterior</a>
        <a class="btn sec sm" href="{{ route('calendar') }}">Hoy</a>
        <a class="btn sec sm" href="{{ route('calendar', ['month' => $month->addMonth()->format('Y-m')]) }}">Siguiente →</a>
    </div>
</div>
<div class="card table-wrap" style="padding:0">
    <table class="cal">
        <thead><tr>@foreach (['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $d)<th>{{ $d }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($weeks as $week)
            <tr>
            @foreach ($week as $ymd => $day)
                <td @class(['out' => $day['date']->month !== $month->month, 'today' => $day['date']->isToday(), 'weekend' => $day['date']->isWeekend()])>
                    <a class="d" href="{{ route('orders.create', ['start' => $ymd, 'end' => $ymd]) }}" title="Nuevo pedido este día">{{ $day['date']->day }}</a>
                    @foreach ($day['orders'] as $o)
                        <a class="ev {{ $o->status->color() }}" href="{{ route('orders.show', $o) }}" title="{{ $o->number }} · {{ $o->status->label() }} · {{ $o->start_date->format('d/m') }}–{{ $o->end_date->format('d/m') }}">{{ $o->client->name }}</a>
                    @endforeach
                </td>
            @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="muted" style="font-size:13px">
    <span class="badge amber">Pre-reserva</span> <span class="badge green">Pagado</span> <span class="badge blue">Material fuera</span> <span class="badge grey">Cerrado</span>
    · Pulsa un número de día para crear un pedido con esa fecha.
</p>
@endsection
