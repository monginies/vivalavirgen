{{-- Desglose de importes de un pedido. Parámetros: $order, $staff (backoffice: muestra «a mano») --}}
@php
    $eur = fn ($v) => \App\Support\Money::eur($v);
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $staff ??= false;
@endphp
<table class="totals-tbl">
    <tr><td>Material</td><td class="num">{{ $eur($order->subtotal()) }}</td></tr>
    @if ($order->discount_percent > 0)
        <tr><td>Descuento {{ $pct($order->discount_percent) }} %</td><td class="num">− {{ $eur($order->discountAmount()) }}</td></tr>
    @endif
    <tr><td>Transporte @if ($staff && $order->transport_manual)<span class="muted">(a mano)</span>@endif</td>
        <td class="num">{{ ! $staff && $order->transport_amount <= 0 ? 'Pendiente' : $eur($order->transport_amount) }}</td></tr>
    <tr class="sub"><td>Base imponible</td><td class="num">{{ $eur($order->taxableBase()) }}</td></tr>
    <tr><td>IVA {{ $pct($order->vat_rate) }} %</td><td class="num">+ {{ $eur($order->vatAmount()) }}</td></tr>
    @if ($order->withholding_rate > 0)
        <tr><td>Retención IRPF {{ $pct($order->withholding_rate) }} %</td><td class="num">− {{ $eur($order->withholdingAmount()) }}</td></tr>
    @endif
    <tr class="sub"><td>Total proforma</td><td class="num">{{ $eur($order->total()) }}</td></tr>
    @if ($order->deposit_amount > 0)
        <tr><td>Fianza <span class="muted">(se devuelve)</span></td><td class="num">+ {{ $eur($order->deposit_amount) }}</td></tr>
    @endif
    <tr class="grand"><td>Total a pagar</td><td class="num">{{ $eur($order->amountDue()) }}</td></tr>
</table>
