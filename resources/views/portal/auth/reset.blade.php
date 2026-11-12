@extends('layouts.portal')
@section('title', 'Nueva contraseña')
@section('content')
<div class="card pt-narrow">
    <h1>Nueva contraseña</h1>
    <form method="post" action="{{ route('portal.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @include('partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $email, 'required' => true, 'attrs' => 'autocomplete=email'])
        <div class="field">
            <label for="f-password">Contraseña nueva *</label>
            <input id="f-password" type="password" name="password" required minlength="8" autocomplete="new-password">
            @error('password')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label for="f-password_confirmation">Repítela *</label>
            <input id="f-password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="btn">Guardar contraseña</button>
    </form>
</div>
@endsection
