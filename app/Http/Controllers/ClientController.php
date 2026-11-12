<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientCategory;
use App\Notifications\ClientApproved;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::query()
            ->with('category')
            ->when($request->boolean('pendientes'), fn ($q) => $q->whereNotNull('password')->whereNotNull('email_verified_at')->whereNull('approved_at'))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('tax_id', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.form', ['client' => new Client(['type' => 'company']), 'categories' => ClientCategory::orderBy('sort')->get()]);
    }

    public function store(Request $request)
    {
        $client = Client::create($this->validated($request));

        return redirect()->route('clients.show', $client)->with('status', 'Cliente creado.');
    }

    public function show(Client $client)
    {
        return view('clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        return view('clients.form', ['client' => $client, 'categories' => ClientCategory::orderBy('sort')->get()]);
    }

    public function update(Request $request, Client $client)
    {
        $client->update($this->validated($request, $client));

        return redirect()->route('clients.show', $client)->with('status', 'Cliente actualizado.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Cliente eliminado.');
    }

    /** Da acceso a la web de clientes (ver precios y pedir) y le avisa por email. */
    public function approve(Request $request, Client $client)
    {
        abort_unless($client->hasPortalAccess(), 422);
        $data = $request->validate(['client_category_id' => ['nullable', Rule::exists('client_categories', 'id')]]);
        $client->forceFill([
            'approved_at' => now(),
            'email_verified_at' => $client->email_verified_at ?? now(),
            'client_category_id' => $data['client_category_id'] ?? $client->client_category_id,
        ])->save();
        $sent = rescue(fn () => $client->notify(new ClientApproved) ?? true, false, report: true);

        return back()->with('status', 'Acceso aprobado.'.($sent ? ' Le hemos avisado por email.' : ' No se ha podido enviar el email de aviso.'));
    }

    public function revoke(Client $client)
    {
        $client->forceFill(['approved_at' => null])->save();

        return back()->with('status', 'Acceso a pedidos web retirado. El cliente puede entrar pero no hacer pedidos.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Client::TYPES))],
            'client_category_id' => ['nullable', Rule::exists('client_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:20', Rule::unique('clients')->ignore($client)->whereNull('deleted_at')],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clients')->ignore($client)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! empty($data['tax_id'])) {
            $data['tax_id'] = strtoupper(preg_replace('/[\s.-]/', '', $data['tax_id']));
        }

        return $data;
    }
}
