{{-- Desglose de importes de un pedido. Parámetros: $order, $staff (backoffice: muestra «a mano») --}}
@php
    $eur = fn ($v) => \App\Support\Money::eur($v);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $staff ??= false;
@endphp
@if (! $staff && $order->discount_percent > 0)
    {{-- Para el cliente, su descuento / comisión destacado y aparte; el material ya sale con él aplicado --}}
    <div style="background:var(--sage-deep);color:#fff;border-radius:10px;padding:12px 16px;margin-bottom:14px">
        <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.85">{{ $order->discountLabel() }}</div>
        <div style="font-size:26px;line-height:1.2"><b>{{ $pct($order->discount_percent) }} %</b> · {{ $eur($order->discountAmount()) }}</div>
        <div style="font-size:13px;opacity:.85">Aplicado al material (tarifa {{ $eur($order->subtotal()) }}).</div>
    </div>
@endif
<table class="totals-tbl">
    @if (! $staff && $order->discount_percent > 0)
        <tr><td>Material <span class="muted">(con {{ mb_strtolower($order->discountLabel()) }})</span></td><td class="num">{{ $eur($order->subtotal() - $order->discountAmount()) }}</td></tr>
    @else
        <tr><td>Material</td><td class="num">{{ $eur($order->subtotal()) }}</td></tr>
        @if ($order->discount_percent > 0)
            <tr><td>Descuento / comisión {{ $pct($order->discount_percent) }} %</td><td class="num">− {{ $eur($order->discountAmount()) }}</td></tr>
        @endif
    @endif
    <tr><td>Transporte @if ($staff && $order->transport_manual)<span class="muted">(a mano)</span>@endif</td>
        <td class="num">{{ ! $staff && $order->transport_amount <= 0 ? 'Pendiente' : $eur($order->transport_amount) }}</td></tr>
    @if ($staff || $order->assembly_amount > 0)
        <tr><td>Montaje</td><td class="num">{{ $eur($order->assembly_amount) }}</td></tr>
    @endif
    <tr class="sub"><td>Base imponible</td><td class="num">{{ $eur($order->taxableBase()) }}</td></tr>
    <tr><td>IVA {{ $pct($order->vat_rate) }} %</td><td class="num">+ {{ $eur($order->vatAmount()) }}</td></tr>
    @if ($order->withholding_rate > 0)
        <tr><td>Retención IRPF {{ $pct($order->withholding_rate) }} %</td><td class="num">− {{ $eur($order->withholdingAmount()) }}</td></tr>
    @endif
    <tr class="sub"><td>Total proforma</td><td class="num">{{ $eur($order->total()) }}</td></tr>
    @if ($order->deposit_amount > 0)
        <tr><td>Fianza</td><td class="num">+ {{ $eur($order->deposit_amount) }}</td></tr>
    @endif
    <tr class="grand"><td>Total a pagar</td><td class="num">{{ $eur($order->amountDue()) }}</td></tr>
</table>
