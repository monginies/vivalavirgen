@extends('layouts.app')
@section('title', 'Ajustes')
@section('content')
<div class="head"><h1>Ajustes</h1></div>
<form class="card" method="post" action="{{ route('settings.update') }}" style="max-width:620px">
    @csrf @method('put')
    @foreach ($definitions as $key => $d)
        @include('partials.field', [
            'name' => $key,
            'label' => $d['label'].($d['unit'] ? ' ('.$d['unit'].')' : ''),
            'value' => \App\Models\Setting::get($key),
            'type' => $d['type'],
            'step' => '0.01', 'min' => 0,
            'required' => $d['type'] === 'number',
            'hint' => $d['hint'],
        ])
    @endforeach
    <div class="actions"><button class="btn">Guardar</button></div>
</form>
<p class="muted" style="font-size:13px">Los cambios se aplican a los pedidos nuevos y a los que se editen. Los pedidos ya guardados conservan sus importes.</p>
@endsection
