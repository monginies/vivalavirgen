{{-- Precio por unidad para el cliente identificado y aprobado; si no, invitación a entrar --}}
@php($me = auth('client')->user())
<span class="pt-price-wrap">
@if ($me?->isApproved())
    @php($d = $me->discountPercent())
    <span class="pt-price-label">Alquiler</span>
    <span class="pt-price">{{ \App\Support\Money::eur($article->rental_price * (1 - $d / 100)) }}</span>
    @if ($d > 0)<s class="pt-price-old">{{ \App\Support\Money::eur($article->rental_price) }}</s>@endif
@elseif ($me)
    <span class="pt-price-label">Precio al activar tu cuenta</span>
@else
    <a class="pt-price-label" href="{{ route('portal.login') }}">Ingresa para ver precios</a>
@endif
</span>
