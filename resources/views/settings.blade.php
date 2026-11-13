@extends('layouts.app')
@section('title', 'Ajustes')
@section('content')
@php
    $groups = [
        'Precios y pedidos' => ['transport_price_per_km', 'transport_minimum', 'deposit_amount', 'prereservation_days', 'vat_rate', 'withholding_rate', 'base_address'],
        'Datos de la proforma' => ['company_name', 'company_tax_id', 'company_address', 'company_phone', 'company_email', 'company_iban', 'proforma_terms'],
        'Web' => ['hero_title', 'hero_image', 'notify_email', 'google_site_verification'],
    ];
    $wide = ['base_address', 'company_name', 'company_address', 'company_iban', 'proforma_terms', 'hero_title'];
    $listed = array_merge(...array_values($groups));
    $rest = array_values(array_diff(array_keys($definitions), $listed));
    if ($rest) { $groups['Otros'] = $rest; }
@endphp
<div class="head"><h1>Ajustes</h1></div>
<form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf @method('put')
    <div class="grid settings-grid">
        @foreach ($groups as $title => $keys)
            <section class="card">
                <h2 style="margin-top:0">{{ $title }}</h2>
                <div class="settings-fields">
                    @foreach ($keys as $key)
                        @continue(! isset($definitions[$key]))
                        @php($d = $definitions[$key])
                        @php($label = \Illuminate\Support\Str::ucfirst(preg_replace('/^(Proforma|Web): /u', '', $d['label'])))
                        <div @class(['wide' => in_array($key, $wide) || $d['type'] === 'image'])>
                            @if ($d['type'] === 'image')
                                <div class="field">
                                    <label for="f-{{ $key }}">{{ $label }}</label>
                                    @if ($img = \App\Models\Setting::get($key))
                                        <img src="{{ asset('storage/'.$img) }}" alt="" style="max-width:100%;max-height:180px;border-radius:8px;display:block;margin-bottom:8px">
                                        <label class="check"><input type="checkbox" name="remove_{{ $key }}" value="1"> Quitar la foto</label>
                                    @endif
                                    <input id="f-{{ $key }}" type="file" name="{{ $key }}" accept="image/*">
                                    @if ($d['hint'])<div class="hint">{{ $d['hint'] }}</div>@endif
                                    @error($key)<div class="err">{{ $message }}</div>@enderror
                                </div>
                            @else
                                @include('partials.field', [
                                    'name' => $key,
                                    'label' => $label.($d['unit'] ? ' ('.$d['unit'].')' : ''),
                                    'value' => \App\Models\Setting::get($key),
                                    'type' => $d['type'],
                                    'step' => '0.01', 'min' => 0,
                                    'required' => $d['type'] === 'number',
                                    'hint' => $d['hint'],
                                ])
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
    <div class="settings-save">
        <button class="btn">Guardar</button>
        <span class="muted" style="font-size:13px">Los cambios se aplican a los pedidos nuevos y a los que se editen. Los pedidos ya guardados conservan sus importes.</span>
    </div>
</form>
@endsection
