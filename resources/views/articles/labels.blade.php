@extends('layouts.app')
@section('title', 'Etiquetas · '.$article->name)
@section('content')
<div class="head no-print">
    <div><h1>Etiquetas QR</h1><div class="sub">{{ $article->name }} · {{ $units->count() }} etiquetas</div></div>
    <div class="actions">
        <form method="get" class="actions">
            <select name="status" onchange="this.form.submit()" style="width:auto">
                <option value="">Todas las unidades</option>
                @foreach (\App\Enums\UnitStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </form>
        <a class="btn sec" href="{{ route('articles.show', $article) }}">Volver</a>
        <button class="btn" onclick="window.print()">Imprimir</button>
    </div>
</div>
<div class="labels">
    @forelse ($units as $u)
        <div class="label">
            <div class="q">{!! $u->qrSvg() !!}</div>
            <div class="t"><b>{{ $u->code }}</b>{{ Str::limit($article->name, 40) }}<br><span class="muted">{{ config('app.name') }}</span></div>
        </div>
    @empty
        <p class="empty">No hay unidades.</p>
    @endforelse
</div>
@endsection
