@extends('layouts.app')
@section('title', $client->exists ? 'Editar cliente' : 'Nuevo cliente')
@section('content')
<div class="head"><h1>{{ $client->exists ? 'Editar cliente' : 'Nuevo cliente' }}</h1></div>
<form class="card" method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" style="max-width:760px">
    @csrf @if ($client->exists) @method('put') @endif
    <div class="field">
        <label>Tipo *</label>
        <div class="actions">
            @foreach (\App\Models\Client::TYPES as $value => $label)
                <label class="check"><input type="radio" name="type" value="{{ $value }}" @checked(old('type', $client->type) === $value)> {{ $label }}</label>
            @endforeach
        </div>
        @error('type')<div class="err">{{ $message }}</div>@enderror
    </div>
    <div class="field" style="max-width:360px">
        <label for="f-client_category_id">Tipo de cliente (descuento)</label>
        <select id="f-client_category_id" name="client_category_id">
            <option value="">Sin tipo (precio base)</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('client_category_id', $client->client_category_id) == $cat->id)>{{ $cat->name }} · {{ rtrim(rtrim(number_format($cat->discount_percent, 2, ',', ''), '0'), ',') }} %</option>
            @endforeach
        </select>
    </div>
    <div class="grid g2">
        @include('partials.field', ['name' => 'name', 'label' => 'Nombre / razón social', 'value' => $client->name, 'required' => true])
        @include('partials.field', ['name' => 'trade_name', 'label' => 'Nombre comercial', 'value' => $client->trade_name, 'hint' => 'Si es distinto de la razón social.'])
        @include('partials.field', ['name' => 'tax_id', 'label' => 'NIF / CIF', 'value' => $client->tax_id])
        @include('partials.field', ['name' => 'contact_name', 'label' => 'Persona de contacto', 'value' => $client->contact_name])
        @include('partials.field', ['name' => 'phone', 'label' => 'Teléfono', 'value' => $client->phone, 'type' => 'text', 'attrs' => 'inputmode=tel'])
        @include('partials.field', ['name' => 'email', 'label' => 'Email', 'value' => $client->email, 'type' => 'email'])
        @include('partials.field', ['name' => 'address', 'label' => 'Dirección', 'value' => $client->address])
        @include('partials.field', ['name' => 'postal_code', 'label' => 'Código postal', 'value' => $client->postal_code, 'attrs' => 'inputmode=numeric maxlength=5 autocomplete=off data-cp-city=f-city'])
        @include('partials.field', ['name' => 'city', 'label' => 'Población', 'value' => $client->city])
        @include('partials.field', ['name' => 'province', 'label' => 'Provincia', 'value' => $client->province])
    </div>
    @include('partials.field', ['name' => 'notes', 'label' => 'Notas', 'value' => $client->notes, 'type' => 'textarea'])
    <div class="actions">
        <button class="btn">Guardar</button>
        <a class="btn sec" href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}">Cancelar</a>
    </div>
</form>
@if ($client->exists)
    <form method="post" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('¿Eliminar este cliente?')">
        @csrf @method('delete')<button class="btn danger sm">Eliminar cliente</button>
    </form>
@endif
@endsection
