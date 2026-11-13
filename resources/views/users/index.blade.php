@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<div class="head"><h1>Usuarios</h1><a class="btn" href="{{ route('users.create') }}">+ Nuevo usuario</a></div>
<div class="card table-wrap">
    <table>
        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td class="grow">{{ $u->name }}</td>
                <td class="muted" style="flex-basis:100%;order:5">{{ $u->email }}</td>
                <td>{!! $u->is_superadmin ? '<span class="badge blue">Superusuario</span>' : ($u->isLogistics() ? '<span class="badge amber">Logística</span>' : '<span class="badge grey">Oficina</span>') !!}</td>
                <td>{!! $u->active ? '<span class="badge green">Activo</span>' : '<span class="badge grey">Desactivado</span>' !!}</td>
                <td class="actions" style="justify-content:flex-end"><a class="btn sec sm" href="{{ route('users.edit', $u) }}">Editar</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
