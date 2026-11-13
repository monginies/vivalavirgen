@extends('layouts.app')
@section('title', 'Cómo dar salida y entrada')
@section('content')
<div style="max-width:760px">
<div class="head">
    <div><h1>Cómo dar salida y entrada</h1><div class="sub">Guía rápida para el equipo de logística</div></div>
    <a class="btn sec" href="{{ route('expeditions.index') }}">Ir a expediciones</a>
</div>
</div>
@include('expeditions._help')
@endsection
