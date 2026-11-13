{{-- Lista que se refresca tras cada escaneo --}}
@php($out = $mode === 'salida')
<div class="card table-wrap" style="padding:14px">
    <h2 style="margin-bottom:6px">{{ $out ? 'Material del pedido' : 'Material fuera' }}</h2>
    <table class="no-stack">
        <thead><tr><th></th><th>Artículo</th><th class="num">Lleva</th><th class="num">{{ $out ? 'Escaneadas' : 'Vueltas' }}</th></tr></thead>
        <tbody>
        @foreach ($expected as $e)
            @php($done = $out ? $e['scanned'] : $e['returned'])
            @php($goal = $out ? $e['needed'] : $e['scanned'])
            @continue(! $out && ! $e['scanned'])
            <tr>
                <td style="width:44px">@if ($p = $e['article']->photoUrl())<img class="thumb" src="{{ $p }}" alt="">@else<div class="thumb"></div>@endif</td>
                <td><span class="mono muted" style="font-size:12px">{{ $e['article']->reference }}</span><br>{{ $e['article']->name }}</td>
                <td class="num">{{ $goal }}</td>
                <td class="num">
                    @if ($out && ! $e['needed'])<span class="badge amber">{{ $done }} · no está en el pedido</span>
                    @elseif ($done > $goal)<span class="badge amber">{{ $done }} · sobra {{ $done - $goal }}</span>
                    @elseif ($done === $goal)<span class="badge green">{{ $done }} ✓</span>
                    @else<span class="badge red">{{ $done }} · faltan {{ $goal - $done }}</span>@endif
                </td>
            </tr>
        @endforeach
        @if (! $out && $units->isEmpty())
            <tr><td colspan="4" class="empty">No salió ninguna unidad escaneada con este pedido.</td></tr>
        @endif
        </tbody>
    </table>
</div>

<div class="card table-wrap" style="padding:14px">
    <h2 style="margin-bottom:6px">Unidades {{ $out ? 'escaneadas' : 'del pedido' }} ({{ $units->count() }})</h2>
    <table class="no-stack">
        <tbody>
        @forelse ($units->sortByDesc($out ? 'out_at' : 'in_at') as $u)
            <tr>
                <td><span class="mono">{{ $u->unit->code }}</span><br><span class="muted" style="font-size:13px">{{ $u->unit->article->name }}</span></td>
                <td style="text-align:right">
                    @if ($out)
                        @if ($u->isOut())
                            <button type="button" class="btn sec sm" data-unit="{{ $u->unit_id }}">Quitar</button>
                        @else
                            <span class="badge {{ $u->return_status === 'ok' ? 'green' : 'amber' }}">{{ \App\Models\OrderUnit::RETURN_LABELS[$u->return_status] }}</span>
                        @endif
                    @else
                        <div class="actions" style="justify-content:flex-end;gap:6px">
                            @foreach (['ok' => 'Devuelta', 'damaged' => 'Rota', 'lost' => 'Perdida'] as $st => $txt)
                                <button type="button" @class(['btn sm', 'sec' => $u->return_status !== $st, 'danger' => $st !== 'ok' && $u->return_status !== $st]) data-unit="{{ $u->unit_id }}" data-status="{{ $u->return_status === $st ? 'pending' : $st }}"
                                    title="{{ $u->return_status === $st ? 'Volver a pendiente' : '' }}">{{ $txt }}</button>
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="2" class="empty">{{ $out ? 'Aún no has escaneado ninguna unidad.' : '—' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
