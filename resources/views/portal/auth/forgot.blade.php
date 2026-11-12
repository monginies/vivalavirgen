@extends('layouts.portal')
@section('title', 'Recuperar contraseña')
@section('content')
<div class="card pt-narrow">
    <h1>Recuperar contraseña</h1>
    <p class="muted">Escribe tu email y te enviaremos un enlace para elegir una contraseña nueva.</p>
    <form method="post" action="{{ route('portal.password.email') }}">
        @csrf
        @include('partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'attrs' => 'autofocus autocomplete=email'])
        <button class="btn">Enviar enlace</button>
    </form>
</div>
@endsection
