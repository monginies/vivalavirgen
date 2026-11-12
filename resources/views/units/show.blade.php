@extends('layouts.app')
@section('title', $unit->code)
@section('content')
<div class="head">
    <div><h1 class="mono" style="font-size:24px">{{ $unit->code }}</h1>
        <div class="sub"><a href="{{ route('articles.show', $unit->article) }}">{{ $unit->article->name }}</a> · {{ $unit->article->category->name }}</div></div>
    <span class="badge {{ $unit->status->color() }}" style="font-size:14px;padding:5px 14px">{{ $unit->status->label() }}</span>
</div>
<div class="grid g2">
    <div class="card" style="text-align:center">
        <div class="qr" style="margin:0 auto">{!! $unit->qrSvg() !!}</div>
        <div class="mono muted" style="word-break:break-all;margin:8px 0">{{ $unit->qrUrl() }}</div>
        <a class="btn sec sm" href="{{ route('units.qr', $unit) }}" download="{{ $unit->code }}.svg">Descargar SVG</a>
    </div>
    <form class="card" method="post" action="{{ route('units.update', $unit) }}">
        @csrf @method('patch')
        <h2>Estado y notas</h2>
        <div class="field">
            <label for="f-status">Estado</label>
            <select id="f-status" name="status">
                @foreach (\App\Enums\UnitStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(old('status', $unit->status->value) === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <div class="hint">En fases siguientes el estado «Alquilada» lo gestionarán los pedidos automáticamente.</div>
        </div>
        @include('partials.field', ['name' => 'acquired_at', 'label' => 'Fecha de alta', 'value' => $unit->acquired_at?->format('Y-m-d'), 'type' => 'date'])
        @include('partials.field', ['name' => 'notes', 'label' => 'Notas (desperfectos, reparaciones…)', 'value' => $unit->notes, 'type' => 'textarea'])
        <button class="btn">Guardar</button>
    </form>
</div>
<form method="post" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('¿Eliminar esta unidad? Su QR dejará de funcionar.')">
    @csrf @method('delete')<button class="btn danger sm">Eliminar unidad</button>
</form>
@endsection
