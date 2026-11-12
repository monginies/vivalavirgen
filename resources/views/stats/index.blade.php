@extends('layouts.app')
@section('title', 'Estadísticas de la web')
@section('content')
@php($pct = fn ($n, $total) => $total ? round($n * 100 / $total) : 0)
<div class="head"><h1>Estadísticas de la web</h1>
    <div class="actions seg">
        @foreach (\App\Http\Controllers\StatsController::RANGES as $d => $label)
            <a @class(['btn', 'sec' => $d !== $days]) href="{{ route('stats', ['dias' => $d]) }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
<p class="muted" style="margin-top:-6px">Desde el {{ $from->format('d/m/Y') }}. Solo cuenta la web de clientes; no incluye las visitas del equipo ni de robots. Una visita es una persona navegando (sesión).</p>

<div class="grid g4" style="margin-bottom:20px">
    <div class="card" style="margin:0"><div class="stat">{{ number_format($summary['visitors'], 0, ',', '.') }}</div>
        <div class="stat-label">Visitas
            @if ($summary['prevVisitors'])
                @php($diff = $pct($summary['visitors'] - $summary['prevVisitors'], $summary['prevVisitors']))
                <span class="{{ $diff >= 0 ? 'up' : 'down' }}">{{ $diff >= 0 ? '+' : '' }}{{ $diff }} % vs. periodo anterior</span>
            @endif
        </div></div>
    <div class="card" style="margin:0"><div class="stat">{{ number_format($summary['pageviews'], 0, ',', '.') }}</div><div class="stat-label">Páginas vistas · {{ str_replace('.', ',', $summary['perVisit']) }} por visita</div></div>
    <a class="card" style="text-decoration:none;color:inherit;margin:0" href="{{ route('clients.index', ['pendientes' => 1]) }}"><div class="stat">{{ $summary['registers'] }}</div><div class="stat-label">Registros nuevos</div></a>
    <a class="card" style="text-decoration:none;color:inherit;margin:0" href="{{ route('orders.index') }}"><div class="stat">{{ $summary['orders'] }}</div><div class="stat-label">Pedidos desde la web</div></a>
</div>

<div class="card">
    <h2>Visitas por {{ $days > 90 ? 'semana' : 'día' }}</h2>
    @php($max = max(1, max($series ?: [0])))
    <div class="bars" role="img" aria-label="Visitas por {{ $days > 90 ? 'semana' : 'día' }}">
        @foreach ($series as $date => $n)
            @php($d = \Illuminate\Support\Carbon::parse($date))
            <div class="bar" title="{{ $days > 90 ? 'Semana del '.$d->format('d/m') : $d->translatedFormat('D d/m') }}: {{ $n }} visita{{ $n == 1 ? '' : 's' }}">
                <span style="height:{{ max($n ? 3 : 0, round($n * 100 / $max)) }}%"></span>
            </div>
        @endforeach
    </div>
    <div class="bars-axis muted"><span>{{ $from->format('d/m') }}</span><span>máx. {{ $max }}</span><span>hoy</span></div>
</div>

<div class="grid g2">
    <div class="card">
        <h2>Del interés al pedido</h2>
        <table>
            @foreach ($funnel as $i => [$label, $n])
                <tr><td>{{ $label }}</td>
                    <td style="width:45%"><div class="meter"><span style="width:{{ $pct($n, $funnel[0][1]) }}%"></span></div></td>
                    <td class="num">{{ $n }}</td>
                    <td class="num muted">{{ $i ? $pct($n, $funnel[0][1]).' %' : '' }}</td></tr>
            @endforeach
        </table>
    </div>
    <div class="card">
        <h2>De dónde llegan</h2>
        <table>
            @php($totalSrc = max(1, $sources->sum('n')))
            @forelse ($sources as $s)
                <tr><td>{{ $s->source ?? 'Directo' }}</td>
                    <td style="width:45%"><div class="meter"><span style="width:{{ $pct($s->n, $totalSrc) }}%"></span></div></td>
                    <td class="num">{{ $s->n }}</td></tr>
            @empty
                <tr><td class="empty">Aún no hay datos.</td></tr>
            @endforelse
        </table>
        @if ($devices->isNotEmpty())
            @php($totalDev = max(1, $devices->sum()))
            <p class="muted" style="margin:12px 0 0">Dispositivo:
                @foreach (['movil' => 'móvil', 'ordenador' => 'ordenador', 'tablet' => 'tablet'] as $k => $label)
                    @if ($devices[$k] ?? 0)<b>{{ $pct($devices[$k], $totalDev) }} %</b> {{ $label }}@if (! $loop->last) · @endif @endif
                @endforeach
            </p>
        @endif
    </div>
</div>

<div class="card table-wrap">
    <h2>Artículos más vistos</h2>
    <table>
        <thead><tr><th>Artículo</th><th class="num">Vistas</th><th class="num">Personas</th><th class="num">Añadido al pedido</th></tr></thead>
        @forelse ($topArticles as $r)
            @php($a = $articles[$r->article_id] ?? null)
            <tr><td>@if ($a)<a href="{{ route('articles.show', $a) }}">{{ $a->name }}</a> <span class="muted mono">{{ $a->reference }}</span>@else <span class="muted">Artículo borrado</span>@endif</td>
                <td class="num">{{ $r->views }}</td><td class="num">{{ $r->people }}</td><td class="num">{{ $r->adds ?: '—' }}</td></tr>
        @empty
            <tr><td class="empty" colspan="4">Aún no hay datos.</td></tr>
        @endforelse
    </table>
</div>

<div class="grid g2">
    <div class="card">
        <h2>Qué buscan</h2>
        <table>
            @forelse ($searches as $s)
                <tr><td>«{{ $s->term }}»</td><td class="num">{{ $s->n }}×</td>
                    <td class="num">@if ((int) $s->results === 0)<span class="badge red">sin resultados</span>@else<span class="muted">{{ $s->results }}+ result.</span>@endif</td></tr>
            @empty
                <tr><td class="empty">Nadie ha buscado nada todavía.</td></tr>
            @endforelse
        </table>
    </div>
    <div class="card">
        <h2>Búsquedas sin resultado</h2>
        <p class="muted" style="margin-top:-4px">Lo que piden y no encuentran: ideas para nuevos artículos o para usar sus palabras en las descripciones.</p>
        <table>
            @forelse ($noResults as $s)
                <tr><td>«{{ $s->term }}»</td><td class="num">{{ $s->n }}×</td><td class="num muted">{{ \Illuminate\Support\Carbon::parse($s->last)->format('d/m') }}</td></tr>
            @empty
                <tr><td class="empty">Ninguna. Bien.</td></tr>
            @endforelse
        </table>
    </div>
</div>

<div class="grid g2">
    <div class="card">
        <h2>Colecciones más visitadas</h2>
        <table>
            @php($totalCat = max(1, $topCategories->max('people')))
            @forelse ($topCategories as $c)
                <tr><td>{{ $categories[$c->category_id] ?? 'Categoría borrada' }}</td>
                    <td style="width:45%"><div class="meter"><span style="width:{{ $pct($c->people, $totalCat) }}%"></span></div></td>
                    <td class="num">{{ $c->people }}</td></tr>
            @empty
                <tr><td class="empty">Aún no hay datos.</td></tr>
            @endforelse
        </table>
    </div>
    <div class="card">
        <h2>Pedidos sin terminar</h2>
        <p class="muted" style="margin-top:-4px">Clientes con cuenta que añadieron artículos y no solicitaron el pedido. Buena ocasión para llamarles.</p>
        <table>
            @forelse ($abandoned as $r)
                @php($c = $abandonedClients[$r->client_id] ?? null)
                <tr><td>@if ($c)<a href="{{ route('clients.show', $c) }}">{{ $c->trade_name ?: $c->name }}</a>@endif</td>
                    <td class="muted">{{ $r->items }} artículo{{ $r->items == 1 ? '' : 's' }}</td>
                    <td class="num muted">{{ \Illuminate\Support\Carbon::parse($r->at)->format('d/m H:i') }}</td></tr>
            @empty
                <tr><td class="empty">Ninguno.</td></tr>
            @endforelse
        </table>
    </div>
</div>

<div class="card">
    <h2>Clientes aprobados sin ningún pedido</h2>
    <table>
        @forelse ($idle as $c)
            <tr><td><a href="{{ route('clients.show', $c) }}">{{ $c->trade_name ?: $c->name }}</a></td>
                <td class="muted">{{ $c->phone }}</td>
                <td class="num muted">aprobado el {{ $c->approved_at->format('d/m/Y') }}</td></tr>
        @empty
            <tr><td class="empty">Todos los clientes aprobados han hecho algún pedido.</td></tr>
        @endforelse
    </table>
</div>
<p class="muted">Los datos se guardan {{ \App\Models\WebEvent::RETENTION_MONTHS }} meses y después se borran solos. No se guardan direcciones IP.</p>
@endsection
