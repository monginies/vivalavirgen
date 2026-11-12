@extends('layouts.portal')
@section('title', 'Crear cuenta')
@section('content')
<div class="card pt-narrow" style="max-width:760px">
    <h1>Crear cuenta</h1>
    <p class="muted">Con tu cuenta podrás ver tus precios, preparar pedidos y seguir su estado. Revisamos cada alta antes de activarla.</p>
    <form method="post" action="{{ route('portal.register') }}">
        @csrf
        <div class="field">
            <label>Eres *</label>
            <div class="actions">
                <label class="check"><input type="radio" name="type" value="individual" @checked(old('type', 'individual') === 'individual')> Particular</label>
                <label class="check"><input type="radio" name="type" value="company" @checked(old('type') === 'company')> Empresa / profesional</label>
            </div>
            <label class="check" style="margin-top:8px"><input type="checkbox" name="wedding_planner" value="1" @checked(old('wedding_planner'))> Soy wedding planner u organizador de eventos</label>
            @error('type')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="grid g2">
            @include('partials.field', ['name' => 'name', 'label' => 'Nombre y apellidos / razón social', 'required' => true, 'attrs' => 'autocomplete=organization'])
            @include('partials.field', ['name' => 'tax_id', 'label' => 'NIF / CIF', 'required' => true, 'hint' => 'Lo necesitamos para la factura.'])
            @include('partials.field', ['name' => 'contact_name', 'label' => 'Persona de contacto', 'required' => true, 'attrs' => 'autocomplete=name'])
            @include('partials.field', ['name' => 'phone', 'label' => 'Teléfono', 'required' => true, 'attrs' => 'inputmode=tel autocomplete=tel'])
        </div>
        @include('partials.field', ['name' => 'address', 'label' => 'Dirección (fiscal)', 'required' => true, 'attrs' => 'autocomplete=street-address'])
        <div class="grid" style="grid-template-columns:130px 1fr 1fr;gap:10px">
            @include('partials.field', ['name' => 'postal_code', 'label' => 'C. postal', 'required' => true, 'attrs' => 'inputmode=numeric maxlength=5 autocomplete=postal-code data-cp-city=f-city data-cp-province=f-province'])
            @include('partials.field', ['name' => 'city', 'label' => 'Población', 'required' => true])
            @include('partials.field', ['name' => 'province', 'label' => 'Provincia'])
        </div>
        <div class="grid g2">
            @include('partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => 'autocomplete=email', 'hint' => 'Será tu usuario. Te enviaremos un enlace para confirmarlo.'])
            <div></div>
            <div class="field">
                <label for="f-password">Contraseña *</label>
                <input id="f-password" type="password" name="password" required minlength="8" autocomplete="new-password">
                <div class="hint">Mínimo 8 caracteres.</div>
                @error('password')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="f-password_confirmation">Repite la contraseña *</label>
                <input id="f-password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>
        <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="field">
            <label class="check" style="align-items:flex-start"><input type="checkbox" name="privacy" value="1" @checked(old('privacy')) required>
                <span style="font-weight:400">He leído y acepto la <a href="{{ route('portal.privacy') }}" target="_blank">política de privacidad</a>.</span></label>
            @error('privacy')<div class="err">{{ $message }}</div>@enderror
        </div>
        <button class="btn">Crear cuenta</button>
    </form>
    <p style="margin-top:16px;font-size:14px">¿Ya tienes cuenta? <a href="{{ route('portal.login') }}">Entra</a></p>
</div>
@endsection
