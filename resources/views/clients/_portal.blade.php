{{-- Acceso del cliente a la web de reservas --}}
@php([$label, $color] = $client->portalStatus())
<div class="card">
    <h2>Web de clientes <span class="badge {{ $color }}" style="font-family:var(--sans);vertical-align:middle">{{ $label }}</span></h2>
    @if (! $client->hasPortalAccess())
        <p class="muted">Este cliente no tiene cuenta en la web. Puede crearla él mismo en {{ route('portal.register') }}@if ($client->email) con el email {{ $client->email }}@endif.</p>
    @else
        <dl class="props" style="margin-bottom:14px">
            <dt>Alta</dt><dd>{{ $client->source === 'web' ? 'Registrado en la web' : 'Creado en el backoffice' }} · {{ $client->created_at->format('d/m/Y') }}</dd>
            <dt>Email</dt><dd>{{ $client->email }} @if ($client->hasVerifiedEmail())<span class="badge green">confirmado</span>@else<span class="badge amber">sin confirmar</span>@endif</dd>
            @if ($client->privacy_accepted_at)<dt>Privacidad</dt><dd>Aceptada el {{ $client->privacy_accepted_at->format('d/m/Y H:i') }}</dd>@endif
            @if ($client->approved_at)<dt>Aprobado</dt><dd>{{ $client->approved_at->format('d/m/Y H:i') }}</dd>@endif
        </dl>
        @if (! $client->approved_at)
            <form method="post" action="{{ route('clients.approve', $client) }}" class="actions" style="align-items:flex-end">
                @csrf
                <div style="flex:1;min-width:200px">
                    <label for="f-approve-cat">Tipo de cliente (descuento)</label>
                    <select id="f-approve-cat" name="client_category_id">
                        <option value="">Sin tipo (precio base)</option>
                        @foreach (\App\Models\ClientCategory::orderBy('sort')->get() as $cat)
                            <option value="{{ $cat->id }}" @selected($client->client_category_id === $cat->id)>{{ $cat->name }} · {{ rtrim(rtrim(number_format($cat->discount_percent, 2, ',', ''), '0'), ',') }} %</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn">Aprobar acceso</button>
            </form>
            <p class="hint">Al aprobarlo podrá ver sus precios y enviar pedidos, y le llegará un email de aviso.@unless ($client->hasVerifiedEmail()) Aún no ha confirmado su email; al aprobarlo se da por confirmado.@endunless</p>
        @else
            <form method="post" action="{{ route('clients.revoke', $client) }}" onsubmit="return confirm('¿Retirar el acceso a pedidos web de este cliente?')">@csrf<button class="btn danger sm">Retirar acceso</button></form>
        @endif
    @endif
</div>
