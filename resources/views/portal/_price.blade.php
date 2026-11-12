{{-- Precio por unidad para el cliente identificado y aprobado; si no, invitación a entrar --}}
@php($me = auth('client')->user())
@if ($me?->isApproved())
    @php($d = $me->discountPercent())
    <span class="pt-price">{{ \App\Support\Money::eur($article->rental_price * (1 - $d / 100)) }}<small> / ud. + IVA</small></span>
    @if ($d > 0)<s class="muted" style="font-size:13px">{{ \App\Support\Money::eur($article->rental_price) }}</s>@endif
@elseif ($me)
    <span class="muted" style="font-size:13px">Precio visible al aprobar tu cuenta</span>
@else
    <a class="muted" style="font-size:13px" href="{{ route('portal.login') }}">Entra para ver precios</a>
@endif
