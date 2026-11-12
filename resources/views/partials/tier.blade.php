{{-- Distintivo del tipo de cliente con su icono. Uso: @include('partials.tier', ['category' => $c, 'discount' => true]) --}}
@if ($category)
@php($icons = [
    'esparto' => '<path d="M8 3c0 3 8 3 8 6s-8 3-8 6 8 3 8 6"/><path d="M16 3c0 3-8 3-8 6s8 3 8 6-8 3-8 6"/>',
    'mimbre' => '<path d="M8 10a4 4 0 0 1 8 0"/><path d="M3.5 10h17l-1.7 9.3A2 2 0 0 1 16.8 21H7.2a2 2 0 0 1-2-1.7z"/><path d="M5 14.5h14"/><path d="M9 10v11"/><path d="M15 10v11"/>',
    'ratan' => '<path d="M5 13a7 7 0 0 1 14 0"/><path d="M8.5 7.8V13"/><path d="M12 6v7"/><path d="M15.5 7.8V13"/><path d="M3.5 13h17v2.5a2.5 2.5 0 0 1-2.5 2.5H6a2.5 2.5 0 0 1-2.5-2.5z"/><path d="m7 18-1 3"/><path d="m17 18 1 3"/>',
    'hoja' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/><path d="M2 21c0-3 1.9-5.4 5.1-6"/>',
])
<span class="tier tier-{{ $category->icon }}" title="{{ $category->name }} · {{ rtrim(rtrim(number_format($category->discount_percent, 2, ',', ''), '0'), ',') }} % de descuento">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$category->icon] ?? $icons['esparto'] !!}</svg>
    {{ $category->name }}@if (! empty($discount)) <b>{{ rtrim(rtrim(number_format($category->discount_percent, 2, ',', ''), '0'), ',') }} %</b>@endif
</span>
@endif
