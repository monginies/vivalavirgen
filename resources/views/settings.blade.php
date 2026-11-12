@extends('layouts.app')
@section('title', 'Ajustes')
@section('content')
<div class="head"><h1>Ajustes</h1></div>
<form class="card" method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" style="max-width:620px">
    @csrf @method('put')
    @foreach ($definitions as $key => $d)
        @if ($d['type'] === 'image')
            <div class="field">
                <label for="f-{{ $key }}">{{ $d['label'] }}</label>
                @if ($img = \App\Models\Setting::get($key))
                    <img src="{{ asset('storage/'.$img) }}" alt="" style="max-width:100%;max-height:180px;border-radius:8px;display:block;margin-bottom:8px">
                    <label class="check"><input type="checkbox" name="remove_{{ $key }}" value="1"> Quitar la foto</label>
                @endif
                <input id="f-{{ $key }}" type="file" name="{{ $key }}" accept="image/*">
                @if ($d['hint'])<div class="hint">{{ $d['hint'] }}</div>@endif
                @error($key)<div class="err">{{ $message }}</div>@enderror
            </div>
            @continue
        @endif
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
